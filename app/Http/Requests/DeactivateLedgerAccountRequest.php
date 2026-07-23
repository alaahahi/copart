<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DeactivateLedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف الحساب مطلوب.',
        ];
    }
}
