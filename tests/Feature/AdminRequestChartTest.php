<?php

namespace Tests\Feature;

use App\Models\RemoteService;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminRequestChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_stacks_exact_http_codes_by_request_day_and_keeps_requests_without_a_response(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00'));
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $service = RemoteService::query()->create([
            'name' => 'Chart Service',
            'slug' => 'chart-service',
            'endpoint_url' => 'https://api.example.test/inquiry',
        ]);

        $this->createRequest($user, $service, 200, now());
        $this->createRequest($user, $service, 200, now());
        $this->createRequest($user, $service, 404, now());
        $this->createRequest($user, $service, null, now());
        $this->createRequest($user, $service, 500, now()->subDay());
        $this->createRequest($user, $service, 201, now()->subDays(7));

        $this->actingAs($admin)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('وضعیت HTTP درخواست‌ها')
            ->assertSee('توزیع روزانه پاسخ‌ها')
            ->assertViewHas('statuses', fn (array $statuses) => array_column($statuses, 'total', 'key') === ['200' => 2, '404' => 1, '500' => 1, 'none' => 1])
            ->assertViewHas('chartSummary', ['total' => 5, 'success' => 2, 'without_http' => 1])
            ->assertViewHas('chartDays', function (array $days): bool {
                $today = $days[6];
                $yesterday = $days[5];

                return count($days) === 7
                    && $today['date'] === '2026-09-25'
                    && $today['total'] === 4
                    && array_column($today['segments'], 'count', 'key') === ['200' => 2, '404' => 1, 'none' => 1]
                    && $yesterday['total'] === 1
                    && array_column($yesterday['segments'], 'count', 'key') === ['500' => 1];
            })
            ->assertViewHas('chartMax', 4);
    }

    public function test_chart_has_an_empty_state(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('در ۷ روز اخیر درخواستی ثبت نشده است.');
    }

    private function createRequest(User $user, RemoteService $service, ?int $httpStatus, Carbon $createdAt): void
    {
        $request = ServiceRequest::query()->create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'input_payload' => [],
            'status' => 'pending',
            'last_http_status' => $httpStatus,
        ]);
        $request->forceFill(['created_at' => $createdAt])->save();
    }
}
