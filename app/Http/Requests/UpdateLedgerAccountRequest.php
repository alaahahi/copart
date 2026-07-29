<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateLedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        $ownerId = (int) Auth::user()->owner_id;
        $accountId = (int) $this->input('id');

        return [
            'id' => ['required', 'integer', 'min:1'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-_\/.]{0,30}$/',
                Rule::unique('ledger_accounts', 'code')
                    ->where(fn ($q) => $q->where('owner_id', $ownerId))
                    ->ignore($accountId),
            ],
            'type' => ['nullable', 'in:asset,liability,equity,income,expense'],
            'currency' => ['nullable', 'in:$,IQD,multi'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('ledger_accounts', 'id')->where(fn ($q) => $q->where('owner_id', $ownerId)->where('is_active', true)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف الحساب مطلوب.',
            'name_ar.required' => 'الاسم العربي للحساب مطلوب.',
            'name_ar.max' => 'الاسم العربي طويل جداً.',
            'name.max' => 'الاسم الإنجليزي طويل جداً.',
            'code.unique' => 'رمز الحساب مستخدم مسبقاً.',
            'code.regex' => 'رمز الحساب غير صالح.',
            'type.in' => 'نوع الحساب غير صالح.',
            'currency.in' => 'العملة غير صالحة.',
            'parent_id.integer' => 'معرف الحساب الأب يجب أن يكون رقماً صحيحاً.',
            'parent_id.exists' => 'الحساب الأب غير موجود.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }

        if ($this->input('currency') === 'multi' || $this->input('currency') === '') {
            $this->merge(['currency' => null]);
        }

        $parentId = $this->input('parent_id');
        if (
            $parentId === ''
            || $parentId === '0'
            || $parentId === 0
            || $parentId === false
            || is_array($parentId)
            || is_object($parentId)
            || (is_string($parentId) && ! ctype_digit(trim($parentId)))
        ) {
            $this->merge(['parent_id' => null]);
        } elseif ($parentId !== null && is_numeric($parentId)) {
            $this->merge(['parent_id' => (int) $parentId]);
        }
    }
}
