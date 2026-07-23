<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateClientRequest extends FormRequest
{
    /**
     * Tenant ownership is re-checked in the controller (client must belong
     * to the authenticated user's owner_id) — this only gates that the
     * caller is authenticated.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            // عرض بالمحاسبة (قاسة) — editable from the edit modal.
            'show_in_dashboard' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف التاجر مطلوب.',
            'id.exists' => 'التاجر غير موجود.',
            'name.required' => 'اسم التاجر مطلوب.',
            'show_in_dashboard.boolean' => 'قيمة عرض بالمحاسبة غير صحيحة.',
        ];
    }
}
