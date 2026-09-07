<?php

namespace Tests\Feature;

use App\Enums\PayloadMode;
use App\Enums\ResponseFormat;
use App\Enums\ServiceHttpMethod;
use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WalletReservation;
use App\Models\WalletTransaction;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('remote_services.async', false);
        config()->set('remote_services.enforce_dns_resolution', false);
        config()->set('remote_services.require_https_in_production', false);

        SystemSetting::query()->create([
            'key' => SystemSetting::REMOTE_SERVICE_TOKEN,
            'value' => 'wallet-test-token',
        ]);
    }

    public function test_admin_can_set_user_wallet_balance_and_adjustments_are_ledgered(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => 1,
            'wallet_balance' => 500000,
            'service_ids' => [],
        ])->assertRedirect(route('admin.users.index'));

        $wallet = $user->fresh()->wallet()->firstOrFail();
        $this->assertSame(500000, $wallet->balance);
        $this->assertSame(0, $wallet->reserved_balance);

        $transaction = $wallet->transactions()->firstOrFail();
        $this->assertSame(WalletTransaction::TYPE_ADMIN_CREDIT, $transaction->type);
        $this->assertSame(500000, $transaction->amount);
        $this->assertSame($admin->id, $transaction->performed_by);
    }

    public function test_successful_inquiry_captures_reserved_amount_and_debits_wallet_once(): void
    {
        Http::fake(['https://api.example.test/*' => Http::response(['ok' => true], 200)]);
        $user = User::factory()->create();
        $service = $this->makeService(12000);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);
        app(WalletService::class)->setBalance($user, 50000);

        $this->actingAs($user)->post(route('requests.store', $service))->assertRedirect();

        $wallet = $user->fresh()->wallet()->firstOrFail();
        $request = ServiceRequest::query()->firstOrFail();
        $attempt = $request->attempts()->firstOrFail();

        $this->assertSame(38000, $wallet->balance);
        $this->assertSame(0, $wallet->reserved_balance);
        $this->assertSame(12000, $attempt->price_amount);
        $this->assertSame('succeeded', $attempt->status->value);
        $this->assertDatabaseHas('wallet_reservations', [
            'service_request_id' => $request->id,
            'amount' => 12000,
            'status' => WalletReservation::STATUS_CAPTURED,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'service_request_attempt_id' => $attempt->id,
            'type' => WalletTransaction::TYPE_INQUIRY_DEBIT,
            'amount' => 12000,
            'balance_after' => 38000,
        ]);
    }

    public function test_failed_inquiry_releases_reservation_without_debiting_wallet(): void
    {
        Http::fake(['https://api.example.test/*' => Http::response(['error' => true], 500)]);
        $user = User::factory()->create();
        $service = $this->makeService(12000);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);
        app(WalletService::class)->setBalance($user, 50000);

        $this->actingAs($user)->post(route('requests.store', $service))->assertRedirect();

        $wallet = $user->fresh()->wallet()->firstOrFail();
        $this->assertSame(50000, $wallet->balance);
        $this->assertSame(0, $wallet->reserved_balance);
        $this->assertDatabaseHas('wallet_reservations', ['amount' => 12000, 'status' => WalletReservation::STATUS_RELEASED]);
        $this->assertDatabaseMissing('wallet_transactions', ['type' => WalletTransaction::TYPE_INQUIRY_DEBIT]);
    }

    public function test_insufficient_wallet_balance_blocks_remote_call(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $service = $this->makeService(60000);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);
        app(WalletService::class)->setBalance($user, 50000);

        $this->actingAs($user)->post(route('requests.store', $service))->assertRedirect();

        Http::assertNothingSent();
        $wallet = $user->fresh()->wallet()->firstOrFail();
        $this->assertSame(50000, $wallet->balance);
        $this->assertSame(0, $wallet->reserved_balance);
        $this->assertSame('failed', ServiceRequest::query()->firstOrFail()->status->value);
        $this->assertDatabaseCount('wallet_reservations', 0);
    }

    public function test_service_page_disables_submit_when_available_balance_is_insufficient(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $service = $this->makeService(60000);
        $user->services()->attach($service->id, ['is_active' => true, 'assigned_at' => now()]);
        app(WalletService::class)->setBalance($user, 50000);

        $this->actingAs($user)
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('موجودی کیف پول کافی نیست')
            ->assertSee('data-inquiry-submit', false)
            ->assertSee('disabled', false);
    }

    private function makeService(int $price): RemoteService
    {
        return RemoteService::query()->create([
            'name' => 'Wallet Inquiry',
            'slug' => 'wallet-inquiry-'.uniqid(),
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
            'price_amount' => $price,
            'allow_resubmit' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
