<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreClientRequest extends FormRequest
{
    /**
     * Any authenticated tenant user may create a client/trader for their
     * own tenant (owner_id is derived server-side in the controller, never
     * trusted from the request).
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'phone' => ['nullable', 'string', 'max:30'],
            // عرض بالمحاسبة (قاسة) — defaults to false/hidden when creating a new trader.
            'show_in_dashboard' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم التاجر مطلوب.',
            'name.unique' => 'يوجد تاجر بهذا الاسم مسبقاً.',
            'show_in_dashboard.boolean' => 'قيمة عرض بالمحاسبة غير صحيحة.',
        ];
    }
}
