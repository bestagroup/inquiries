<?php

namespace App\Jobs;

use App\Actions\ServiceRequests\ExecuteServiceRequest;
use App\Enums\AttemptStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Services\Billing\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteRemoteServiceRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 840;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $serviceRequestId,
        public readonly string $executionToken,
    ) {}

    public function handle(ExecuteServiceRequest $executor): void
    {
        $serviceRequest = ServiceRequest::query()
            ->whereKey($this->serviceRequestId)
            ->where('execution_token', $this->executionToken)
            ->where('status', ServiceRequestStatus::Pending->value)
            ->first();

        if (! $serviceRequest) {
            return;
        }

        try {
            $executor->execute($serviceRequest);
        } catch (Throwable $exception) {
            report($exception);
            $this->markAsFailed($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAsFailed($exception ?? new \RuntimeException('اجرای سرویس به صورت غیرمنتظره متوقف شد.'));
    }

    private function markAsFailed(Throwable $exception): void
    {
        app(WalletService::class)->release($this->executionToken);

        $message = mb_substr($exception->getMessage() ?: 'اجرای سرویس با خطا متوقف شد.', 0, 1000);
        $serviceRequest = ServiceRequest::query()
            ->whereKey($this->serviceRequestId)
            ->where('execution_token', $this->executionToken)
            ->where('status', ServiceRequestStatus::Pending->value)
            ->first();

        if (! $serviceRequest) {
            return;
        }

        $updated = ServiceRequest::query()
            ->whereKey($serviceRequest->id)
            ->where('execution_token', $this->executionToken)
            ->where('status', ServiceRequestStatus::Pending->value)
            ->update([
                'status' => ServiceRequestStatus::Failed->value,
                'last_responded_at' => now(),
                'last_error' => $message,
            ]);

        if ($updated !== 1) {
            return;
        }

        ServiceRequestAttempt::query()
            ->where('service_request_id', $serviceRequest->id)
            ->where('sequence', $serviceRequest->attempt_count)
            ->where('status', AttemptStatus::Running->value)
            ->update([
                'status' => AttemptStatus::Failed->value,
                'error_code' => class_basename($exception),
                'error_message' => $message,
                'completed_at' => now(),
            ]);
    }
}
