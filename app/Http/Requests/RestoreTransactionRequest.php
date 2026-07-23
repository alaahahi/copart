<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RestoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
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
            'id.required' => 'معرف الحركة مطلوب.',
            'id.integer' => 'معرف الحركة غير صالح.',
        ];
    }

    /**
     * Accept id from query string (same pattern as delTransactions) or body.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('id') && $this->query('id')) {
            $this->merge(['id' => $this->query('id')]);
        }
    }
}
