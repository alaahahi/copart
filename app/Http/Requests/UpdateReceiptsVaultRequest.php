<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateReceiptsVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && (int) $user->type_id === 1;
    }

    public function rules(): array
    {
        $ownerId = (int) (Auth::user()?->owner_id ?? 0);

        return [
            'default_receipts_vault_id' => [
                'nullable',
                'integer',
                Rule::exists('vaults', 'id')->where(function ($q) use ($ownerId) {
                    $q->where('owner_id', $ownerId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->whereNotNull('legacy_user_id');
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'default_receipts_vault_id.exists' => 'القاصة المحددة غير صالحة لاستلام دفعات الزبائن.',
        ];
    }
}
