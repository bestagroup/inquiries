<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $id = is_object($target) ? $target->getKey() : $target;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($id)],
            'password' => ['nullable', 'string', 'min:10', 'max:128', 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'wallet_balance' => ['nullable', 'integer', 'min:0', 'max:9000000000000000'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
        ];
    }
}
