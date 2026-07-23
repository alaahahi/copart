<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PostTraderProfitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'in:$,IQD'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'entry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'اختيار التاجر مطلوب.',
            'amount.required' => 'مبلغ الأرباح المراد ترحيلها مطلوب.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'currency.required' => 'العملة مطلوبة.',
            'currency.in' => 'العملة غير صالحة.',
            'period_to.after_or_equal' => 'نهاية الفترة يجب أن تكون بعد أو تساوي بدايتها.',
        ];
    }
}
