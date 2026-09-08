<?php

namespace Tests\Feature;

use App\Enums\FieldDirection;
use App\Enums\FieldType;
use App\Models\RemoteService;
use App\Models\User;
use App\Services\RemoteServices\ResponseMapper;
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

    public function test_invalid_service_form_keeps_deliberately_empty_field_lists(): void
    {
        $this->withoutVite();
        config()->set('remote_services.enforce_dns_resolution', false);
        $this->seed(ServiceCatalogSeeder::class);
        $admin = User::factory()->admin()->create();
        $service = RemoteService::query()->where('slug', 'identity-inquiry')->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('admin.services.edit', $service))
            ->put(route('admin.services.update', $service), $this->servicePayload($service, ['name' => '']));

        $response->assertRedirect(route('admin.services.edit', $service))->assertSessionHasErrors('name');

        $this->get(route('admin.services.edit', $service))
            ->assertOk()
            ->assertSee('عنوان فارسی سرویس را وارد کنید.')
            ->assertSee('هنوز ورودی تعریف نشده است');
    }

    public function test_dynamic_service_fields_have_clear_validation_messages_and_keep_input(): void
    {
        config()->set('remote_services.enforce_dns_resolution', false);
        $this->seed(ServiceCatalogSeeder::class);
        $admin = User::factory()->admin()->create();
        $service = RemoteService::query()->where('slug', 'identity-inquiry')->firstOrFail();
        $payload = $this->servicePayload($service, [
            'inputs' => [[
                'label' => '', 'key' => '', 'type' => 'text', 'validation_rules' => '',
                'default_value' => '', 'options' => '', 'is_required' => 1, 'is_sensitive' => 0,
            ]],
        ]);

        $response = $this->actingAs($admin)->put(route('admin.services.update', $service), $payload);

        $response
            ->assertSessionHasErrors([
                'inputs.0.label' => 'عنوان نمایشی ورودی شماره 1 را وارد کنید.',
                'inputs.0.key' => 'کلید API ورودی شماره 1 را وارد کنید.',
            ]);

        $this->get(route('admin.services.edit', $service))
            ->assertOk()
            ->assertSee('عنوان نمایشی ورودی شماره 1 را وارد کنید.')
            ->assertSee('کلید API ورودی شماره 1 را وارد کنید.');
    }

    public function test_admin_can_define_an_array_output_and_mapper_preserves_its_value(): void
    {
        config()->set('remote_services.enforce_dns_resolution', false);
        $this->seed(ServiceCatalogSeeder::class);
        $admin = User::factory()->admin()->create();
        $service = RemoteService::query()->where('slug', 'identity-inquiry')->firstOrFail();
        $payload = $this->servicePayload($service, [
            'outputs' => [[
                'label' => 'سوابق',
                'key' => 'records',
                'type' => FieldType::Array->value,
                'json_path' => 'data.records',
            ]],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), $payload)
            ->assertRedirect(route('admin.services.index'));

        $field = $service->fresh()->fields()->where('direction', FieldDirection::Output)->firstOrFail();
        $this->assertSame(FieldType::Array, $field->type);

        $records = [['id' => 1, 'status' => 'active'], ['id' => 2, 'status' => 'inactive']];
        $mapped = app(ResponseMapper::class)->map($service->fresh(), ['data' => ['records' => $records]]);

        $this->assertSame($records, $mapped[0]['value']);
    }

    public function test_array_type_cannot_be_used_for_an_input_field(): void
    {
        config()->set('remote_services.enforce_dns_resolution', false);
        $this->seed(ServiceCatalogSeeder::class);
        $admin = User::factory()->admin()->create();
        $service = RemoteService::query()->where('slug', 'identity-inquiry')->firstOrFail();
        $payload = $this->servicePayload($service, [
            'inputs' => [[
                'label' => 'ورودی آرایه‌ای',
                'key' => 'records',
                'type' => FieldType::Array->value,
                'validation_rules' => '',
                'default_value' => '',
                'options' => '',
                'is_required' => 0,
                'is_sensitive' => 0,
            ]],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), $payload)
            ->assertSessionHasErrors('inputs.0.type');
    }

    private function servicePayload(RemoteService $service, array $overrides = []): array
    {
        return array_replace([
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'category' => $service->category,
            'icon' => $service->icon,
            'endpoint_url' => $service->endpoint_url,
            'http_method' => $service->http_method->value,
            'payload_mode' => $service->payload_mode->value,
            'response_format' => $service->response_format->value,
            'headers_json' => '{}',
            'timeout_seconds' => $service->timeout_seconds,
            'connect_timeout_seconds' => $service->connect_timeout_seconds,
            'retry_times' => $service->retry_times,
            'retry_delay_ms' => $service->retry_delay_ms,
            'rate_limit_per_minute' => $service->rate_limit_per_minute,
            'allow_resubmit' => (int) $service->allow_resubmit,
            'is_active' => (int) $service->is_active,
            'sort_order' => $service->sort_order,
            'inputs_present' => 1,
            'outputs_present' => 1,
        ], $overrides);
    }
}
