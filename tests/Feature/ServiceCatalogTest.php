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
            ->assertSee('service-search', false)
            ->assertSee('سرویس در دسترس شماست');
    }

    public function test_admin_can_open_the_service_management_and_guided_editor_pages(): void
    {
        $this->withoutVite();
        $this->seed(ServiceCatalogSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('تعریف سرویس جدید')
            ->assertSee('کل سرویس‌ها');

        $this->actingAs($admin)
            ->get(route('admin.services.create'))
            ->assertOk()
            ->assertSee('مراحل تعریف سرویس')
            ->assertSee('مشخصات اصلی')
            ->assertSee('اتصال به API')
            ->assertSee('افزودن ورودی');
    }

    public function test_user_can_open_the_clear_service_execution_form(): void
    {
        $this->withoutVite();
        $this->seed(ServiceCatalogSeeder::class);
        $user = User::factory()->create();
        $service = RemoteService::query()->where('slug', 'mobile-national-id-match')->firstOrFail();
        $user->services()->attach($service, ['is_active' => true, 'assigned_at' => now()]);

        $this->actingAs($user)
            ->get(route('services.show', $service))
            ->assertOk()
            ->assertSee('اطلاعات مورد نیاز')
            ->assertSee('شماره موبایل')
            ->assertSee('ثبت و اجرای استعلام')
            ->assertSee('ارسال امن اطلاعات');
    }
}
