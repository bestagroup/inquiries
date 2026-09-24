<?php

namespace App\Services\RemoteServices;

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class ServiceRequestRateLimiter
{
    public function consume(ServiceRequest $request): void
    {
        $request->loadMissing(['service', 'user']);

        if (! $request->service || $request->service->trashed() || ! $request->service->is_active) {
            throw new RuntimeException('این سرویس غیرفعال است.');
        }

        if (! $request->user?->is_active) {
            throw new RuntimeException('حساب کاربری درخواست‌دهنده غیرفعال است.');
        }

        if (! $request->user->canUseService($request->service)) {
            throw new RuntimeException('دسترسی کاربر به این سرویس لغو شده است.');
        }

        $assignment = $request->user->isAdmin() ? null : $request->user->services()->whereKey($request->service_id)->first();
        $limit = (int) ($assignment?->pivot?->rate_limit_per_minute ?: $request->service->rate_limit_per_minute);
        $key = 'remote-service:'.$request->user_id.':'.$request->service_id;

        if (RateLimiter::tooManyAttempts($key, max(1, $limit))) {
            throw new RuntimeException('تعداد درخواست‌های شما برای این سرویس بیش از حد مجاز است.');
        }

        RateLimiter::hit($key, 60);
    }
}
