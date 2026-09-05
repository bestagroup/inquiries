<?php

namespace Tests\Feature;

use App\Models\RemoteService;
use App\Models\User;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_is_idempotent_and_preserves_operational_configuration(): void
    {
        $this->seed(ServiceCatalogSeeder::class);

        $service = RemoteService::query()->where('slug', 'identity-inquiry')->firstOrFail();
        $fieldCount = $service->fields()->count();
        $service->update(['endpoint_url' => 'https://configured.example.test/identity', 'is_active' => false]);

        $this->seed(ServiceCatalogSeeder::class);

        $this->assertDatabaseCount('services', 15);
        $this->assertSame($fieldCount, $service->fresh()->fields()->count());
        $this->assertSame('https://configured.example.test/identity', $service->fresh()->endpoint_url);
        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_assigned_services_are_rendered_in_the_rtl_catalog(): void
    {
        $this->withoutVite();
        $this->seed(ServiceCatalogSeeder::class);
        $user = User::factory()->create();
        $user->services()->attach(
            RemoteService::query()->pluck('id')->mapWithKeys(fn (int $id) => [
                $id => ['is_active' => true, 'assigned_at' => now()],
            ])->all()
        );

        $this->actingAs($user)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('سرویس‌های استعلام')
            ->assertSee('تطبیق موبایل و کدملی')
            ->assertSee('استعلام املاک')
            ->assertSee('15 سرویس');
    }
}
