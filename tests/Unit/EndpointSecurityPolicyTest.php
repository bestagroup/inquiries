<?php

namespace Tests\Unit;

use App\Services\RemoteServices\EndpointSecurityPolicy;
use InvalidArgumentException;
use Tests\TestCase;

class EndpointSecurityPolicyTest extends TestCase
{
    public function test_disallowed_host_is_rejected_by_allowlist(): void
    {
        config()->set('remote_services.allowed_hosts', ['api.allowed.test']);
        config()->set('remote_services.enforce_dns_resolution', false);

        $this->expectException(InvalidArgumentException::class);
        app(EndpointSecurityPolicy::class)->assertAllowed('https://api.other.test/inquiry');
    }

    public function test_allowlisted_host_is_accepted_when_dns_check_is_disabled(): void
    {
        config()->set('remote_services.allowed_hosts', ['*.allowed.test']);
        config()->set('remote_services.enforce_dns_resolution', false);

        app(EndpointSecurityPolicy::class)->assertAllowed('https://v1.allowed.test/inquiry');
        $this->assertTrue(true);
    }

    public function test_credentials_are_not_allowed_inside_endpoint_url(): void
    {
        config()->set('remote_services.enforce_dns_resolution', false);

        $this->expectException(InvalidArgumentException::class);
        app(EndpointSecurityPolicy::class)->assertAllowed('https://user:secret@api.allowed.test/inquiry');
    }
}
