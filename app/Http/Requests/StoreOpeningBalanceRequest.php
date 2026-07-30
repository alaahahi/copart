<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        $ownerId = (int) Auth::user()->owner_id;

        return [
            'ledger_account_id' => [
                'required',
                'integer',
                Rule::exists('ledger_accounts', 'id')->where(
                    fn ($q) => $q->where('owner_id', $ownerId)->where('is_active', true)
                ),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'in:$,IQD'],
            'entry_date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'ledger_account_id.required' => 'الحساب مطلوب.',
            'ledger_account_id.exists' => 'الحساب غير صالح.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'currency.required' => 'العملة مطلوبة.',
            'currency.in' => 'العملة غير صالحة.',
        ];
    }
}
