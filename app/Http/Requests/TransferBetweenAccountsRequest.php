<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TransferBetweenAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        return [
            'from_user_id' => ['required', 'integer', 'min:1', 'different:to_user_id'],
            'to_user_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'in:$,IQD'],
            'entry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_user_id.required' => 'الحساب المرسل مطلوب.',
            'to_user_id.required' => 'الحساب المستقبل مطلوب.',
            'from_user_id.different' => 'لا يمكن التحويل من وإلى نفس الحساب.',
            'amount.required' => 'المبلغ مطلوب.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'currency.required' => 'العملة مطلوبة.',
            'currency.in' => 'العملة غير صالحة.',
        ];
    }
}
