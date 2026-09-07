<?php

namespace App\Services\RemoteServices;

use InvalidArgumentException;

class EndpointSecurityPolicy
{
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('آدرس سرویس معتبر نیست.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('قرار دادن نام کاربری یا رمز عبور در آدرس سرویس مجاز نیست.');
        }

        if (app()->environment('production') && config('remote_services.require_https_in_production') && $scheme !== 'https') {
            throw new InvalidArgumentException('در محیط Production فقط HTTPS مجاز است.');
        }

        $allowedHosts = config('remote_services.allowed_hosts', []);
        if (app()->environment('production') && $allowedHosts === []) {
            throw new InvalidArgumentException('فهرست دامنه‌های مجاز سرویس‌ها در محیط Production پیکربندی نشده است.');
        }

        if ($allowedHosts !== [] && ! $this->matchesAllowList($host, $allowedHosts)) {
            throw new InvalidArgumentException('دامنه مقصد در فهرست مجاز سرویس‌ها نیست.');
        }

        if (! config('remote_services.enforce_dns_resolution', true)) {
            return;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveAddresses($host);
        if ($ips === []) {
            throw new InvalidArgumentException('دامنه مقصد قابل Resolve نیست.');
        }

        if (! config('remote_services.allow_private_networks', false)) {
            foreach ($ips as $ip) {
                $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($isPublic === false) {
                    throw new InvalidArgumentException('ارسال درخواست به شبکه خصوصی یا رزروشده مجاز نیست.');
                }
            }
        }
    }

    private function resolveAddresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        ))));
    }

    private function matchesAllowList(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === $host) {
                return true;
            }
            if (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1))) {
                return true;
            }
        }

        return false;
    }
}
