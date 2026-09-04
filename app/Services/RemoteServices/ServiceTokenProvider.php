<?php

namespace App\Services\RemoteServices;

use App\Models\SystemSetting;
use RuntimeException;

class ServiceTokenProvider
{
    public function authorizationHeader(): string
    {
        $token = SystemSetting::remoteServiceToken();

        if ($token === null) {
            throw new RuntimeException('توکن مشترک سرویس‌ها هنوز توسط مدیر سامانه تعریف نشده است.');
        }

        return 'Bearer '.$token;
    }
}
