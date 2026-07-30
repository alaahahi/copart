<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        $ownerId = (int) Auth::user()->owner_id;
        $accountRule = Rule::exists('ledger_accounts', 'id')->where(
            fn ($q) => $q->where('owner_id', $ownerId)->where('is_active', true)
        );

        return [
            'debit_account_id' => ['required', 'integer', $accountRule],
            'credit_account_id' => ['required', 'integer', 'different:debit_account_id', $accountRule],
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', 'in:$,IQD'],
            'entry_date' => ['nullable', 'date'],
            'memo' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'debit_account_id.required' => 'الحساب المدين مطلوب.',
            'debit_account_id.exists' => 'الحساب المدين غير صالح.',
            'credit_account_id.required' => 'الحساب الدائن مطلوب.',
            'credit_account_id.exists' => 'الحساب الدائن غير صالح.',
            'credit_account_id.different' => 'يجب اختيار حسابين مختلفين للمدين والدائن.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'currency.required' => 'العملة مطلوبة.',
            'currency.in' => 'العملة غير صالحة.',
            'memo.required' => 'البيان مطلوب.',
        ];
    }
}
