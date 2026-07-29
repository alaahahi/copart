<?php

namespace App\Http\Requests;

use App\Models\LedgerAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ToggleLedgerAccountAccountingRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->type_id, [1, 6], true)) {
            return false;
        }

        $account = $this->route('account');
        if (! $account instanceof LedgerAccount) {
            return false;
        }

        return (int) $account->owner_id === (int) Auth::user()->owner_id;
    }

    public function rules(): array
    {
        return [
            'show_in_accounting' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'show_in_accounting.boolean' => 'قيمة عرض بالمحاسبة غير صالحة.',
        ];
    }
}
