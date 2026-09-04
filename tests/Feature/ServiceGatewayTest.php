<?php

namespace Tests\Feature;

use App\Enums\FieldDirection;
use App\Enums\FieldType;
use App\Enums\PayloadMode;
use App\Enums\ResponseFormat;
use App\Enums\ServiceHttpMethod;
use App\Enums\UserRole;
use App\Jobs\ExecuteRemoteServiceRequest;
use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('remote_services.enforce_dns_resolution', false);
        config()->set('remote_services.require_https_in_production', false);
    }

    public function test_admin_can_create_user_and_assign_service(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->makeService();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'phone' => '09120000000',
            'password' => 'StrongPass!123',
            'password_confirmation' => 'StrongPass!123',
            'is_active' => 1,
            'service_ids' => [$service->id],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->services()->whereKey($service->id)->exists());
    }

    public function test_authenticated_user_is_redirected_from_login_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_user_cannot_execute_unassigned_service(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService();

        $this->actingAs($user)
            ->post(route('requests.store', $service), ['input' => ['national_id' => '0012345678']])
            ->assertForbidden();
    }

    public function test_successful_execution_is_saved_and_request_payload_is_encrypted(): void
    {
        Http::fake(['https://api.example.test/*' => Http::response(['data' => ['name' => 'Ali']], 200)]);
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
        $service->fields()->create([
            'direction' => FieldDirection::Output,
            'key' => 'name',
            'label' => 'نام',
            'type' => FieldType::Text,
            'json_path' => 'data.name',
        ]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $response = $this->actingAs($user)->post(route('requests.store', $service), [
            'input' => ['national_id' => '0012345678'],
        ]);

        $request = ServiceRequest::query()->firstOrFail();
        $response->assertRedirect(route('requests.show', $request));
        $this->assertSame('succeeded', $request->fresh()->status->value);
        $this->assertCount(1, $request->attempts);
        $this->assertSame('Ali', $request->attempts->first()->mapped_response[0]['value']);

        $raw = \DB::table('service_requests')->where('id', $request->id)->value('input_payload');
        $this->assertStringNotContainsString('0012345678', $raw);
    }

    public function test_refresh_keeps_previous_attempt_and_creates_a_new_attempt(): void
    {
        Http::fakeSequence()->push(['result' => 1], 200)->push(['result' => 2], 200);
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->fields()->create([
            'direction' => FieldDirection::Input, 'key' => 'code', 'label' => 'کد',
            'type' => FieldType::Text, 'is_required' => true,
        ]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($user)->post(route('requests.store', $service), ['input' => ['code' => 'ABC']]);
        $request = ServiceRequest::query()->firstOrFail();
        $this->actingAs($user)->post(route('requests.refresh', $request))->assertRedirect(route('requests.show', $request));

        $this->assertSame(2, $request->fresh()->attempt_count);
        $this->assertSame(2, $request->attempts()->count());
    }

    public function test_user_cannot_view_another_users_request(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = $this->makeService();
        $request = ServiceRequest::query()->create([
            'user_id' => $owner->id,
            'service_id' => $service->id,
            'input_payload' => ['x' => 'y'],
            'status' => 'pending',
        ]);

        $this->actingAs($other)->get(route('requests.show', $request))->assertForbidden();
    }

    public function test_select_input_only_accepts_options_defined_by_admin(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->fields()->create([
            'direction' => FieldDirection::Input,
            'key' => 'type',
            'label' => 'نوع استعلام',
            'type' => FieldType::Select,
            'is_required' => true,
            'options' => ['normal', 'full'],
        ]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($user)
            ->post(route('requests.store', $service), ['input' => ['type' => 'unauthorized']])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_async_execution_is_queued_with_a_stale_job_guard_token(): void
    {
        Queue::fake();
        config()->set('remote_services.async', true);
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->fields()->create([
            'direction' => FieldDirection::Input,
            'key' => 'code',
            'label' => 'کد',
            'type' => FieldType::Text,
            'is_required' => true,
        ]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($user)
            ->post(route('requests.store', $service), ['input' => ['code' => 'ABC']])
            ->assertRedirect();

        $request = ServiceRequest::query()->firstOrFail();
        $this->assertSame('pending', $request->status->value);
        $this->assertNotNull($request->execution_token);
        $this->assertDatabaseCount('service_request_attempts', 0);
        Queue::assertPushed(ExecuteRemoteServiceRequest::class, fn ($job) => $job->serviceRequestId === $request->id
            && $job->executionToken === $request->execution_token
            && $job->queue === 'remote-services');
    }

    public function test_service_rate_limit_is_checked_before_a_job_is_queued(): void
    {
        Queue::fake();
        config()->set('remote_services.async', true);
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->update(['rate_limit_per_minute' => 1]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($user)->post(route('requests.store', $service));
        $this->actingAs($user)->post(route('requests.store', $service));

        Queue::assertPushed(ExecuteRemoteServiceRequest::class, 1);
        $this->assertDatabaseCount('service_requests', 2);
        $this->assertSame('failed', ServiceRequest::query()->latest('id')->firstOrFail()->status->value);
    }

    public function test_user_can_open_own_historical_attempt_but_not_another_users_attempt(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $service = $this->makeService();
        $request = ServiceRequest::query()->create([
            'user_id' => $owner->id,
            'service_id' => $service->id,
            'input_payload' => ['code' => 'ABC'],
            'status' => 'succeeded',
            'attempt_count' => 1,
        ]);
        $attempt = ServiceRequestAttempt::query()->create([
            'service_request_id' => $request->id,
            'sequence' => 1,
            'status' => 'succeeded',
            'endpoint_url' => $service->endpoint_url,
            'http_method' => 'POST',
            'request_payload' => ['code' => 'ABC'],
            'mapped_response' => ['result' => true],
            'http_status' => 200,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($owner)->get(route('requests.attempts.show', [$request, $attempt]))->assertOk();
        $this->actingAs($other)->get(route('requests.attempts.show', [$request, $attempt]))->assertForbidden();
    }

    public function test_oversized_remote_response_is_saved_as_a_failed_attempt(): void
    {
        config()->set('remote_services.max_response_bytes', 20);
        Http::fake(['https://api.example.test/*' => Http::response(str_repeat('x', 50), 200)]);
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->update(['response_format' => ResponseFormat::Text]);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($user)->post(route('requests.store', $service));

        $request = ServiceRequest::query()->firstOrFail();
        $this->assertSame('failed', $request->fresh()->status->value);
        $this->assertSame('failed', $request->attempts()->firstOrFail()->status->value);
        $this->assertStringContainsString('حجم پاسخ', (string) $request->last_error);
    }

    private function makeService(): RemoteService
    {
        return RemoteService::query()->create([
            'name' => 'Test Inquiry',
            'slug' => 'test-inquiry-'.uniqid(),
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
