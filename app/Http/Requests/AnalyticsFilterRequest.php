<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'currency' => ['nullable', 'in:$,IQD,USD'],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'results' => ['nullable', 'integer', 'in:0,1,2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->input('currency');
        if ($currency === 'USD') {
            $this->merge(['currency' => '$']);
        }

        if (!$this->filled('from')) {
            $this->merge(['from' => now()->startOfMonth()->toDateString()]);
        }
        if (!$this->filled('to')) {
            $this->merge(['to' => now()->toDateString()]);
        }
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية.',
            'currency.in' => 'العملة غير صالحة.',
        ];
    }
}
