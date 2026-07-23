<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeleteCarRequest extends FormRequest
{
    /**
     * Coarse role gate (mirrors the UI's delete-button visibility and the
     * DeleteTraderProfitEntryRequest convention). Tenant ownership + the
     * fine-grained delete rule are enforced by CarPolicy in the controller.
     */
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        return [
            // Must reference an existing, not-already-deleted car.
            'id' => ['required', 'integer', 'min:1', Rule::exists('car', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف السيارة مطلوب.',
            'id.exists' => 'السيارة غير موجودة أو محذوفة مسبقاً.',
        ];
    }
}
