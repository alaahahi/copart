<?php

namespace App\Http\Requests;

use App\Models\NejumPaymentLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreNejumPaymentLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'line_type' => ['required', Rule::in([NejumPaymentLine::TYPE_CAR, NejumPaymentLine::TYPE_SHIPPING])],
            'pr_date' => ['nullable', 'date'],
            'car_info' => ['nullable', 'string', 'max:255'],
            'vin' => ['nullable', 'string', 'max:64'],
            'lot' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
            'for_nejum' => ['nullable', 'numeric'],
            'nejum_shipping' => ['nullable', 'numeric'],
            'nejum_payments' => ['nullable', 'numeric'],
            'nejum_payment_date' => ['nullable', 'date'],
            'nejum_note' => ['nullable', 'string', 'max:255'],
            'transaction_fee' => ['nullable', 'numeric'],
            'commission_3mula' => ['nullable', 'numeric'],
            'kaml_must_pay' => ['nullable', 'numeric'],
            'kaml_payment' => ['nullable', 'numeric'],
            'kaml_payment_date' => ['nullable', 'date'],
            'us_fee' => ['nullable', 'numeric'],
            'customer_commission_3mula' => ['nullable', 'numeric'],
            'customer_must_pay' => ['nullable', 'numeric'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_payment_date' => ['nullable', 'date'],
        ];
    }
}
