<?php

namespace Database\Seeders;

use App\Models\RemoteService;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->services() as $position => $definition) {
            $definition['sort_order'] = ($position + 1) * 10;
            $fields = $definition['fields'];
            unset($definition['fields']);

            $catalogData = collect($definition)->only([
                'name', 'description', 'category', 'icon', 'sort_order',
            ])->all();

            $service = RemoteService::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                $definition + [
                    'endpoint_url' => 'https://inquiries.invalid/v1/'.$definition['slug'],
                    'http_method' => 'POST',
                    'payload_mode' => 'json',
                    'response_format' => 'json',
                    'headers' => ['Accept' => 'application/json'],
                    'timeout_seconds' => 15,
                    'connect_timeout_seconds' => 5,
                    'retry_times' => 1,
                    'retry_delay_ms' => 200,
                    'rate_limit_per_minute' => 60,
                    'allow_resubmit' => true,
                    'is_active' => true,
                ],
            );

            // Catalog copy may evolve, but operational settings configured by an admin must survive reseeding.
            $service->update($catalogData);

            foreach ($fields as $fieldPosition => $field) {
                $service->fields()->updateOrCreate(
                    ['direction' => 'input', 'key' => $field['key']],
                    $field + ['direction' => 'input', 'sort_order' => $fieldPosition],
                );
            }
        }
    }

    private function services(): array
    {
        return [
            $this->service('تطبیق موبایل و کدملی', 'mobile-national-id-match', 'احراز هویت', 'bi-phone', 'تطبیق مالکیت شماره موبایل با کد ملی برای احراز هویت سریع و مطمئن.', [
                $this->field('mobile', 'شماره موبایل', 'digits:11'),
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
            $this->service('تطبیق کدملی و تاریخ تولد', 'national-id-birthdate-match', 'احراز هویت', 'bi-calendar2-check', 'بررسی هم‌خوانی کد ملی و تاریخ تولد ثبت‌شده در مراجع هویتی.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
                $this->field('birth_date', 'تاریخ تولد', 'date', 'date'),
            ]),
            $this->service('استعلام اطلاعات هویتی', 'identity-inquiry', 'احراز هویت', 'bi-person-vcard', 'دریافت اطلاعات پایه هویتی شخص حقیقی بر اساس کد ملی.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
            $this->service('استعلام کد پستی و نشانی', 'postal-address-inquiry', 'اسناد و سوابق', 'bi-geo-alt', 'دریافت نشانی استاندارد و مشخصات مکانی متناظر با کد پستی.', [
                $this->field('postal_code', 'کد پستی', 'digits:10'),
            ]),
            $this->service('تطبیق کارت/شبا با کدملی', 'bank-account-national-id-match', 'بانکی و مالی', 'bi-bank', 'بررسی تعلق شماره کارت یا شبا به صاحب کد ملی اعلام‌شده.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
                $this->field('account_number', 'شماره کارت یا شبا', 'string|max:26'),
            ]),
            $this->service('استعلام شماره شبا', 'iban-inquiry', 'بانکی و مالی', 'bi-credit-card-2-front', 'اعتبارسنجی شماره شبا و دریافت مشخصات بانک و صاحب حساب.', [
                $this->field('iban', 'شماره شبا', 'string|size:26'),
            ]),
            $this->service('تصویر کارت ملی', 'national-id-card-image', 'احراز هویت', 'bi-card-image', 'دریافت امن تصویر کارت ملی برای فرایندهای مجاز احراز هویت.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
            $this->service('استعلام چک برگشتی', 'bounced-cheque-inquiry', 'حقوقی و اعتباری', 'bi-receipt-cutoff', 'بررسی سوابق چک برگشتی شخص حقیقی یا حقوقی برای ارزیابی اعتباری.', [
                $this->field('national_id', 'کد ملی یا شناسه ملی', 'digits_between:10,11'),
            ]),
            $this->service('استعلام اشخاص حقوقی', 'legal-entity-inquiry', 'حقوقی و اعتباری', 'bi-buildings', 'دریافت وضعیت ثبتی و اطلاعات رسمی شرکت‌ها و مؤسسات حقوقی.', [
                $this->field('national_identifier', 'شناسه ملی', 'digits:11'),
            ]),
            $this->service('سوابق بیمه', 'insurance-history', 'اسناد و سوابق', 'bi-shield-check', 'دریافت سوابق بیمه‌ای فرد در چارچوب دسترسی و مجوزهای سازمانی.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
            $this->service('استعلام مدارک تحصیلی', 'education-certificate-inquiry', 'اسناد و سوابق', 'bi-mortarboard', 'بررسی اصالت و وضعیت مدارک تحصیلی ثبت‌شده برای متقاضی.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
            $this->service('استعلام رتبه معاملاتی', 'transaction-rating', 'حقوقی و اعتباری', 'bi-graph-up-arrow', 'دریافت رتبه و شاخص‌های معاملاتی برای تصمیم‌گیری تجاری آگاهانه.', [
                $this->field('national_identifier', 'کد ملی یا شناسه ملی', 'digits_between:10,11'),
            ]),
            $this->service('استعلام پلاک‌های فعال', 'active-vehicle-plates', 'خودرو', 'bi-car-front', 'مشاهده پلاک‌های فعال منتسب به شخص بر اساس کد ملی.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
            $this->service('دریافت وضعیت گذرنامه', 'passport-status', 'اسناد و سوابق', 'bi-passport', 'بررسی آخرین وضعیت اعتبار گذرنامه و اطلاعات مرتبط با آن.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
                $this->field('passport_number', 'شماره گذرنامه', 'string|max:20'),
            ]),
            $this->service('استعلام املاک', 'property-inquiry', 'املاک', 'bi-house-check', 'دریافت اطلاعات املاک مرتبط با شخص در محدوده دسترسی سازمانی.', [
                $this->field('national_id', 'کد ملی', 'digits:10'),
            ]),
        ];
    }

    private function service(string $name, string $slug, string $category, string $icon, string $description, array $fields): array
    {
        return compact('name', 'slug', 'category', 'icon', 'description', 'fields');
    }

    private function field(string $key, string $label, string $rules, string $type = 'text'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'is_required' => true,
            'validation_rules' => explode('|', $rules),
            'is_sensitive' => $type === 'text',
        ];
    }
}
