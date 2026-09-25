<?php

namespace App\Services\RemoteServices;

use App\Enums\FieldType;
use App\Models\RemoteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DynamicInputValidator
{
    public function validate(RemoteService $service, array $input): array
    {
        $service->loadMissing('inputFields');
        $rules = [];
        $attributes = [];

        foreach ($service->inputFields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable', ...$this->typeRules($field->type)];
            foreach ((array) $field->validation_rules as $rule) {
                if ($this->isSafeRule($rule)) {
                    $fieldRules[] = $rule;
                }
            }
            if ($field->type === FieldType::Select && filled($field->options)) {
                $fieldRules[] = Rule::in((array) $field->options);
            }
            $rules[$field->key] = $fieldRules;
            $attributes[$field->key] = $field->label;
        }

        $validator = Validator::make($input, $rules, [], $attributes);
        $validated = $validator->validate();

        $imageFields = $service->inputFields->where('type', FieldType::Image);
        $totalImageBytes = $imageFields->sum(fn ($field) => ($validated[$field->key] ?? null) instanceof UploadedFile
            ? $validated[$field->key]->getSize() : 0);
        $maxTotalImageKilobytes = max(1, (int) config('remote_services.max_total_image_kilobytes', 4096));
        if ($totalImageBytes > $maxTotalImageKilobytes * 1024) {
            throw ValidationException::withMessages([
                $imageFields->first()->key => 'مجموع حجم تصاویر این درخواست نباید از '.number_format($maxTotalImageKilobytes).' کیلوبایت بیشتر باشد.',
            ]);
        }

        $normalized = [];
        foreach ($service->inputFields as $field) {
            if (array_key_exists($field->key, $validated)) {
                $normalized[$field->key] = $this->normalize($field->type, $validated[$field->key]);
            } elseif ($field->type !== FieldType::Image && $field->default_value !== null) {
                $normalized[$field->key] = $this->normalize($field->type, $field->default_value);
            }
        }

        return $normalized;
    }

    private function typeRules(FieldType $type): array
    {
        return match ($type) {
            FieldType::Number => ['numeric'],
            FieldType::Boolean => ['boolean'],
            FieldType::Date => ['date'],
            FieldType::Text, FieldType::Select => ['string', 'max:10000'],
            FieldType::Image => ['file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.max(1, (int) config('remote_services.max_image_kilobytes', 2048))],
            FieldType::Array => ['array'],
            FieldType::Object => ['array'],
        };
    }

    private function normalize(FieldType $type, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ($type) {
            FieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            FieldType::Number => str_contains((string) $value, '.') ? (float) $value : (int) $value,
            FieldType::Text, FieldType::Date, FieldType::Select => (string) $value,
            FieldType::Image => $this->encodeImage($value),
            FieldType::Array => (array) $value,
            FieldType::Object => (array) $value,
        };
    }

    private function encodeImage(UploadedFile $image): string
    {
        return base64_encode($image->getContent());
    }

    private function isSafeRule(mixed $rule): bool
    {
        if (! is_string($rule) || strlen($rule) > 180) {
            return false;
        }

        return (bool) preg_match('/^(email|url|uuid|alpha|alpha_num|integer|numeric|string|boolean|date|nullable|required|max:\d+|min:\d+|size:\d+|digits:\d+|digits_between:\d+,\d+|between:\d+,\d+|in:[^|]{1,120})$/', $rule);
    }
}
