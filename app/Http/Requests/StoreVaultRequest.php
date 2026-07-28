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

    public function rules(): array
    {
        $ownerId = (int) ($this->user()?->owner_id ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('vaults', 'code')->where(fn ($q) => $q->where('owner_id', $ownerId)->whereNull('deleted_at')),
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
}
