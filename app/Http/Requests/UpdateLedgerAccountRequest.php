<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateLedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف الحساب مطلوب.',
            'name_ar.required' => 'الاسم العربي للحساب مطلوب.',
            'name_ar.max' => 'الاسم العربي طويل جداً.',
            'name.max' => 'الاسم الإنجليزي طويل جداً.',
        ];
    }
}
