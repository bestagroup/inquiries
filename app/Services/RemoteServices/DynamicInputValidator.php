<?php

namespace App\Services\RemoteServices;

use App\Enums\FieldType;
use App\Models\RemoteService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        $normalized = [];
        foreach ($service->inputFields as $field) {
            if (array_key_exists($field->key, $validated)) {
                $normalized[$field->key] = $this->normalize($field->type, $validated[$field->key]);
            } elseif ($field->default_value !== null) {
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
        };
    }

    private function isSafeRule(mixed $rule): bool
    {
        if (! is_string($rule) || strlen($rule) > 180) {
            return false;
        }

        return (bool) preg_match('/^(email|url|uuid|alpha|alpha_num|integer|numeric|string|boolean|date|nullable|required|max:\d+|min:\d+|size:\d+|digits:\d+|digits_between:\d+,\d+|between:\d+,\d+|in:[^|]{1,120})$/', $rule);
    }
}
