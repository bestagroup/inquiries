<?php

namespace Tests\Feature;

use App\Actions\ServiceRequests\DispatchServiceRequest;
use App\Enums\FieldDirection;
use App\Enums\FieldType;
use App\Enums\PayloadMode;
use App\Enums\ResponseFormat;
use App\Enums\ServiceHttpMethod;
use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\RemoteServices\EndpointSecurityPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('remote_services.enforce_dns_resolution', false);
        config()->set('remote_services.require_https_in_production', false);
    }

    public function test_session_payloads_are_encrypted_by_default(): void
    {
        $this->assertTrue((bool) config('session.encrypt'));
    }

    public function test_sensitive_dynamic_input_is_not_flashed_to_session_after_validation_failure(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->fields()->create([
            'direction' => FieldDirection::Input,
            'key' => 'national_id',
            'label' => 'کد ملی',
            'type' => FieldType::Text,
            'is_required' => true,
            'validation_rules' => ['digits:10'],
            'is_sensitive' => true,
        ]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $response = $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => ['national_id' => '123456789'],
        ]);

        $response->assertSessionHasErrors('national_id');
        $response->assertSessionHas('_old_input', function ($oldInput): bool {
            return is_array($oldInput) && ! array_key_exists('input', $oldInput);
        });
    }

    public function test_production_remote_requests_fail_closed_without_a_host_allowlist(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');
        config()->set('remote_services.allowed_hosts', []);
        config()->set('remote_services.enforce_dns_resolution', false);

        try {
            (new EndpointSecurityPolicy())->assertAllowed('https://api.example.test/inquiry');
            $this->fail('Production endpoint policy must reject an empty host allowlist.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('فهرست دامنه‌های مجاز', $exception->getMessage());
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    public function test_dispatcher_rejects_a_second_execution_while_an_execution_lease_is_active(): void
    {
        Queue::fake();
        config()->set('remote_services.async', true);

        $user = User::factory()->create();
        $service = $this->makeService();
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);
        $request = ServiceRequest::query()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'input_payload' => ['code' => 'ABC'],
            'status' => 'pending',
            'attempt_count' => 0,
            'execution_token' => 'existing-execution-lease',
        ]);

        try {
            app(DispatchServiceRequest::class)->dispatch($request);
            $this->fail('A second execution must not be dispatched while the request is pending.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('اجرای هم‌زمان', $exception->getMessage());
        }

        Queue::assertNothingPushed();
        $this->assertSame('existing-execution-lease', $request->fresh()->execution_token);
    }

    public function test_database_seeding_does_not_reset_or_reactivate_an_existing_admin(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $admin = User::factory()->admin()->create([
            'email' => $email,
            'name' => 'Existing Administrator',
            'password' => 'ExistingStrongPass!123',
            'is_active' => false,
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertFalse($admin->is_active);
        $this->assertSame('Existing Administrator', $admin->name);
        $this->assertTrue(Hash::check('ExistingStrongPass!123', $admin->password));
    }

    public function test_authenticated_responses_disable_browser_caching_and_send_baseline_csp(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("object-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    private function makeService(): RemoteService
    {
        return RemoteService::query()->create([
            'name' => 'Hardening Test Inquiry',
            'slug' => 'hardening-test-'.uniqid(),
            'endpoint_url' => 'https://api.example.test/inquiry',
            'http_method' => ServiceHttpMethod::Post,
            'payload_mode' => PayloadMode::Json,
            'response_format' => ResponseFormat::Json,
            'headers' => ['Accept' => 'application/json'],
            'timeout_seconds' => 10,
            'connect_timeout_seconds' => 3,
            'retry_times' => 0,
            'retry_delay_ms' => 0,
            'rate_limit_per_minute' => 60,
            'allow_resubmit' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
