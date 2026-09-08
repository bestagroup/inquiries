<?php

namespace App\Http\Requests\Admin;

use App\Enums\FieldType;
use App\Enums\PayloadMode;
use App\Enums\ResponseFormat;
use App\Enums\ServiceHttpMethod;
use App\Services\RemoteServices\EndpointSecurityPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SaveServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'is_active' => $this->boolean('is_active'),
            'allow_resubmit' => $this->boolean('allow_resubmit'),
        ];

        // An empty dynamic field list has no browser inputs. Preserve that deliberate empty state
        // so a validation redirect does not repopulate the list from the database.
        if ($this->exists('inputs_present')) {
            $normalized['inputs'] = $this->input('inputs', []);
        }
        if ($this->exists('outputs_present')) {
            $normalized['outputs'] = $this->input('outputs', []);
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $target = $this->route('service');
        $id = is_object($target) ? $target->getKey() : $target;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('services', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'regex:/^bi-[a-z0-9-]+$/', 'max:64'],
            'endpoint_url' => ['required', 'url:http,https', 'max:2048'],
            'http_method' => ['required', Rule::enum(ServiceHttpMethod::class)],
            'payload_mode' => ['required', Rule::enum(PayloadMode::class)],
            'response_format' => ['required', Rule::enum(ResponseFormat::class)],
            'headers_json' => ['nullable', 'json'],
            'timeout_seconds' => ['required', 'integer', 'between:1,120'],
            'connect_timeout_seconds' => ['required', 'integer', 'between:1,30'],
            'retry_times' => ['required', 'integer', 'between:0,5'],
            'retry_delay_ms' => ['required', 'integer', 'between:0,10000'],
            'rate_limit_per_minute' => ['required', 'integer', 'between:1,10000'],
            'allow_resubmit' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,100000'],

            'inputs' => ['nullable', 'array', 'max:50'],
            'inputs.*.key' => ['required', 'distinct', 'regex:/^[A-Za-z_][A-Za-z0-9_-]{0,127}$/'],
            'inputs.*.label' => ['required', 'string', 'max:255'],
            'inputs.*.type' => ['required', Rule::in([
                FieldType::Text->value,
                FieldType::Number->value,
                FieldType::Boolean->value,
                FieldType::Date->value,
                FieldType::Select->value,
            ])],
            'inputs.*.is_required' => ['nullable', 'boolean'],
            'inputs.*.validation_rules' => ['nullable', 'string', 'max:500'],
            'inputs.*.default_value' => ['nullable', 'string', 'max:2000'],
            'inputs.*.options' => ['nullable', 'string', 'max:5000'],
            'inputs.*.is_sensitive' => ['nullable', 'boolean'],

            'outputs' => ['nullable', 'array', 'max:100'],
            'outputs.*.key' => ['required', 'distinct', 'regex:/^[A-Za-z_][A-Za-z0-9_-]{0,127}$/'],
            'outputs.*.label' => ['required', 'string', 'max:255'],
            'outputs.*.type' => ['required', Rule::enum(FieldType::class)],
            'outputs.*.json_path' => ['nullable', 'string', 'max:512'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('http_method') === 'GET' && $this->input('payload_mode') !== 'query') {
                $validator->errors()->add('payload_mode', 'برای متد GET نحوه ارسال باید Query String باشد.');
            }

            if (! $validator->errors()->has('endpoint_url')) {
                try {
                    app(EndpointSecurityPolicy::class)->assertAllowed((string) $this->input('endpoint_url'));
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('endpoint_url', $exception->getMessage());
                }
            }

            foreach ((array) $this->input('inputs', []) as $index => $input) {
                if (($input['type'] ?? null) === FieldType::Select->value) {
                    $options = preg_split('/\r\n|\r|\n|,/', (string) ($input['options'] ?? ''));
                    if (count(array_filter(array_map('trim', $options ?: []))) === 0) {
                        $validator->errors()->add("inputs.{$index}.options", 'برای ورودی Select حداقل یک گزینه تعریف کنید.');
                    }
                }

                foreach (explode('|', (string) ($input['validation_rules'] ?? '')) as $rule) {
                    $rule = trim($rule);
                    if ($rule !== '' && preg_match('/^(email|url|uuid|alpha|alpha_num|integer|numeric|string|boolean|date|nullable|required|max:\d+|min:\d+|size:\d+|digits:\d+|digits_between:\d+,\d+|between:\d+,\d+|in:[^|]{1,120})$/', $rule) !== 1) {
                        $validator->errors()->add("inputs.{$index}.validation_rules", "قانون اعتبارسنجی «{$rule}» پشتیبانی نمی‌شود.");
                    }
                }
            }

            $headersJson = trim((string) $this->input('headers_json', ''));
            if ($headersJson === '') {
                return;
            }

            $decoded = json_decode($headersJson);
            if (! is_object($decoded)) {
                $validator->errors()->add('headers_json', 'Headers باید یک JSON Object باشد.');

                return;
            }

            $blockedHeaders = ['host', 'content-length', 'transfer-encoding', 'connection', 'authorization'];
            foreach (get_object_vars($decoded) as $key => $value) {
                if (! is_string($key) || preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $key) !== 1 || (! is_scalar($value) && ! is_null($value))) {
                    $validator->errors()->add('headers_json', 'کلید و مقدار Header باید ساده و معتبر باشند.');
                    break;
                }
                if (in_array(strtolower($key), $blockedHeaders, true)) {
                    $validator->errors()->add('headers_json', "تعریف Header با نام {$key} مجاز نیست.");
                    break;
                }
                if (str_contains((string) $value, "\r") || str_contains((string) $value, "\n")) {
                    $validator->errors()->add('headers_json', 'مقدار Header نباید شامل خط جدید باشد.');
                    break;
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'عنوان فارسی سرویس را وارد کنید.',
            'slug.required' => 'کد یکتای سرویس را وارد کنید.',
            'slug.alpha_dash' => 'کد سرویس فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.',
            'slug.unique' => 'این کد سرویس قبلاً استفاده شده است؛ یک کد یکتای دیگر وارد کنید.',
            'endpoint_url.required' => 'نشانی Endpoint سرویس را وارد کنید.',
            'endpoint_url.url' => 'نشانی Endpoint باید یک آدرس کامل و معتبر با http یا https باشد.',
            'headers_json.json' => 'هدرهای اختصاصی باید به‌صورت JSON معتبر وارد شوند؛ برای حالت خالی از {} استفاده کنید.',
            'inputs.*.label.required' => 'عنوان نمایشی ورودی شماره :position را وارد کنید.',
            'inputs.*.key.required' => 'کلید API ورودی شماره :position را وارد کنید.',
            'inputs.*.key.distinct' => 'کلید API ورودی شماره :position تکراری است.',
            'inputs.*.key.regex' => 'کلید API ورودی شماره :position باید با حرف انگلیسی یا زیرخط شروع شود و فاصله نداشته باشد.',
            'inputs.*.type.required' => 'نوع ورودی شماره :position را انتخاب کنید.',
            'outputs.*.label.required' => 'عنوان نمایشی خروجی شماره :position را وارد کنید.',
            'outputs.*.key.required' => 'کلید داخلی خروجی شماره :position را وارد کنید.',
            'outputs.*.key.distinct' => 'کلید داخلی خروجی شماره :position تکراری است.',
            'outputs.*.key.regex' => 'کلید داخلی خروجی شماره :position باید با حرف انگلیسی یا زیرخط شروع شود و فاصله نداشته باشد.',
            'outputs.*.type.required' => 'نوع خروجی شماره :position را انتخاب کنید.',
        ];
    }

    public function attributes(): array
    {
        return [
            'timeout_seconds' => 'مهلت پاسخ',
            'connect_timeout_seconds' => 'مهلت اتصال',
            'retry_times' => 'تعداد تلاش مجدد',
            'retry_delay_ms' => 'فاصله تلاش‌ها',
            'rate_limit_per_minute' => 'محدودیت درخواست در دقیقه',
            'sort_order' => 'ترتیب نمایش',
        ];
    }
}
