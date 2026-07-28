<?php

namespace App\Http\Requests;

use App\Models\Vault;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vault|null $vault */
        $vault = $this->route('vault');

        return $vault instanceof Vault
            && ($this->user()?->can('update', $vault) ?? false);
    }

    public function rules(): array
    {
        /** @var Vault|null $vault */
        $vault = $this->route('vault');
        $ownerId = (int) ($vault?->owner_id ?? $this->user()?->owner_id ?? 0);
        $vaultId = (int) ($vault?->id ?? 0);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                Rule::unique('vaults', 'code')
                    ->where(fn ($q) => $q->where('owner_id', $ownerId)->whereNull('deleted_at'))
                    ->ignore($vaultId),
            ],
            'type' => ['sometimes', 'string', Rule::in([
                'cash', 'system', 'commission', 'company', 'expense', 'supplier', 'contracts',
            ])],
            'currency_default' => ['nullable', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_accounting' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
