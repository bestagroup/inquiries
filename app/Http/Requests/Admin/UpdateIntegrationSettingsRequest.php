<?php

namespace App\Http\Requests\Admin;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('service_token')) {
            $this->merge(['service_token' => trim((string) $this->input('service_token'))]);
        }
    }

    public function rules(): array
    {
        return [
            'service_token' => [
                Rule::requiredIf(fn (): bool => SystemSetting::remoteServiceToken() === null),
                'nullable',
                'string',
                'max:4096',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'service_token.required' => 'برای استفاده از سرویس‌ها باید توکن مشترک را وارد کنید.',
            'service_token.max' => 'طول توکن مشترک نمی‌تواند بیشتر از ۴۰۹۶ نویسه باشد.',
        ];
    }
}
