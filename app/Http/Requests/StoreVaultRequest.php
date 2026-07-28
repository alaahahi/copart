<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Vault::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge(['code' => trim($this->code)]);
        }
        if ($this->has('name') && is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }

    public function rules(): array
    {
        $ownerId = (int) ($this->user()?->owner_id ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('vaults', 'code')->where(fn ($q) => $q->where('owner_id', $ownerId)),
            ],
            'type' => ['required', 'string', Rule::in([
                'cash', 'system', 'commission', 'company', 'expense', 'supplier', 'contracts',
            ])],
            'currency_default' => ['nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_accounting' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم القاصة مطلوب.',
            'type.required' => 'نوع القاصة مطلوب.',
            'type.in' => 'نوع القاصة غير صالح.',
            'code.unique' => 'رمز القاصة مستخدم مسبقاً.',
            'code.regex' => 'رمز القاصة يجب أن يحتوي أحرفاً وأرقاماً و _ و - فقط.',
        ];
    }
}
