<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCarSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:car,id'],
            'damage_compensation' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'checkout_s' => ['nullable', 'numeric', 'min:0'],
            'shipping_dolar_s' => ['nullable', 'numeric', 'min:0'],
            'coc_dolar_s' => ['nullable', 'numeric', 'min:0'],
            'dinar_s' => ['nullable', 'numeric', 'min:0'],
            'commission_s' => ['nullable', 'numeric', 'min:0'],
            'expenses_s' => ['nullable', 'numeric', 'min:0'],
            'erbil_clearance_s' => ['nullable', 'numeric', 'min:0'],
            'erbil_transfer_s' => ['nullable', 'numeric', 'min:0'],
            'erbil_border_repair_s' => ['nullable', 'numeric', 'min:0'],
            'erbil_customs_s' => ['nullable', 'numeric', 'min:0'],
            'dolar_price_s' => ['nullable', 'numeric', 'min:0'],
            'client_id' => ['nullable', 'integer'],
            'auction_id' => ['nullable', 'integer'],
            'shipping_route_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'damage_compensation.min' => 'تعويض الضرر لا يمكن أن يكون سالباً.',
            'id.required' => 'معرف السيارة مطلوب.',
            'id.exists' => 'السيارة غير موجودة.',
        ];
    }
}
