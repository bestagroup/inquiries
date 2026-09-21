<?php

namespace App\Services\RemoteServices;

use RuntimeException;

class ServiceTokenProvider
{
    public function token(): string
    {
        $token = trim((string) config('remote_services.token', ''));

        if ($token === '') {
            throw new RuntimeException('توکن سرویس‌های استعلام در REMOTE_SERVICE_TOKEN تنظیم نشده است.');
        }

        return $token;
    }
}
