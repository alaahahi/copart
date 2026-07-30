<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReceiveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        $ownerId = (int) Auth::user()->owner_id;

        return [
            'expense_ledger_account_id' => [
                'required',
                'integer',
                Rule::exists('ledger_accounts', 'id')->where(
                    fn ($q) => $q->where('owner_id', $ownerId)
                        ->whereIn('type', ['expense', 'income'])
                        ->where('is_active', true)
                ),
            ],
            'cash_vault_id' => [
                'required',
                'integer',
                Rule::exists('vaults', 'id')->where(
                    fn ($q) => $q->where('owner_id', $ownerId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                ),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'in:$,IQD'],
            'memo' => ['required', 'string', 'max:500'],
            'entry_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_ledger_account_id.required' => 'الحساب مطلوب.',
            'expense_ledger_account_id.exists' => 'الحساب غير صالح.',
            'cash_vault_id.required' => 'القاصة النقدية مطلوبة.',
            'cash_vault_id.exists' => 'القاصة النقدية غير صالحة.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'memo.required' => 'البيان مطلوب.',
        ];
    }
}
