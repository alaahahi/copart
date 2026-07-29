<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'vin' => [
                'required',
                'string',
                'max:255',
                // Soft-deleted rows must not block re-add; only active cars count.
                Rule::unique('car', 'vin')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'vin.required' => 'رقم الشاصي مطلوب.',
            'vin.unique' => 'رقم الشاصي مستخدم مسبقاً',
        ];
    }
}
