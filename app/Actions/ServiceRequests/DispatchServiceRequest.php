<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Jobs\ExecuteRemoteServiceRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Services\Billing\WalletService;
use App\Services\RemoteServices\ServiceRequestRateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DispatchServiceRequest
{
    public function __construct(
        private readonly ExecuteServiceRequest $executor,
        private readonly ServiceRequestRateLimiter $rateLimiter,
        private readonly WalletService $wallets,
    ) {}

    public function dispatch(ServiceRequest $serviceRequest, ?array $inputPayload = null): ?ServiceRequestAttempt
    {
        $token = (string) Str::uuid();
        $this->acquireExecutionLease($serviceRequest, $token, $inputPayload);

        if (! config('remote_services.async', true)) {
            try {
                return $this->executor->execute($serviceRequest->fresh(['service', 'user']));
            } catch (Throwable $exception) {
                $this->markLeaseAsFailed($serviceRequest, $token, $exception);
                throw $exception;
            }
        }

        try {
            ExecuteRemoteServiceRequest::dispatch($serviceRequest->getKey(), $token)
                ->onQueue((string) config('remote_services.queue', 'remote-services'));
        } catch (Throwable $exception) {
            $this->markLeaseAsFailed($serviceRequest, $token, $exception);
            throw $exception;
        }

        $serviceRequest->refresh();

        return $serviceRequest->status === ServiceRequestStatus::Pending
            ? null
            : $serviceRequest->attempts()->first();
    }

    private function acquireExecutionLease(ServiceRequest $serviceRequest, string $token, ?array $inputPayload): void
    {
        DB::transaction(function () use ($serviceRequest, $token, $inputPayload): void {
            $locked = ServiceRequest::query()
                ->whereKey($serviceRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isInitialDispatch = $locked->status === ServiceRequestStatus::Pending
                && (int) $locked->attempt_count === 0
                && ($locked->execution_token === null || $locked->execution_token === '');

            if ($locked->status === ServiceRequestStatus::Pending && ! $isInitialDispatch) {
                throw new RuntimeException('این درخواست در حال پردازش است و اجرای هم‌زمان مجاز نیست.');
            }

            $locked->loadMissing(['service', 'user']);
            $this->rateLimiter->consume($locked);
            $this->wallets->reserveForExecution($locked, $token);

            $values = [
                'status' => ServiceRequestStatus::Pending,
                'execution_token' => $token,
                'last_error' => null,
            ];

            if ($inputPayload !== null) {
                $values['input_payload'] = $inputPayload;
            }

            $locked->forceFill($values)->save();
        }, 3);

        $serviceRequest->refresh();
    }

    private function markLeaseAsFailed(ServiceRequest $serviceRequest, string $token, Throwable $exception): void
    {
        $this->wallets->release($token);

        ServiceRequest::query()
            ->whereKey($serviceRequest->getKey())
            ->where('execution_token', $token)
            ->where('status', ServiceRequestStatus::Pending->value)
            ->update([
                'status' => ServiceRequestStatus::Failed->value,
                'last_responded_at' => now(),
                'last_error' => mb_substr($exception->getMessage() ?: 'اجرای سرویس با خطا متوقف شد.', 0, 1000),
            ]);

        $serviceRequest->refresh();
    }
}
