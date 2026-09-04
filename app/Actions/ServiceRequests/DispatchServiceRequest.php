<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Jobs\ExecuteRemoteServiceRequest;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Services\RemoteServices\ServiceRequestRateLimiter;
use Illuminate\Support\Str;

class DispatchServiceRequest
{
    public function __construct(
        private readonly ExecuteServiceRequest $executor,
        private readonly ServiceRequestRateLimiter $rateLimiter,
    ) {}

    public function dispatch(ServiceRequest $serviceRequest): ?ServiceRequestAttempt
    {
        $this->rateLimiter->consume($serviceRequest);

        if (! config('remote_services.async', true)) {
            return $this->executor->execute($serviceRequest);
        }

        $token = (string) Str::uuid();
        $serviceRequest->forceFill([
            'status' => ServiceRequestStatus::Pending,
            'execution_token' => $token,
            'last_error' => null,
        ])->save();

        ExecuteRemoteServiceRequest::dispatch($serviceRequest->getKey(), $token)
            ->onQueue((string) config('remote_services.queue', 'remote-services'));

        $serviceRequest->refresh();

        return $serviceRequest->status === ServiceRequestStatus::Pending
            ? null
            : $serviceRequest->attempts()->first();
    }
}
