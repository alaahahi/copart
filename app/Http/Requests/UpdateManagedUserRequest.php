<?php

namespace App\Http\Requests;

use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && (int) $user->type_id === 1;
    }

    public function rules(): array
    {
        $managedUser = $this->route('user');
        $userId = is_object($managedUser) ? $managedUser->id : $managedUser;

        $assignableTypeIds = UserType::query()
            ->where('name', '!=', 'client')
            ->pluck('id')
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at'),
            ],
            'type_id' => ['required', 'integer', Rule::in($assignableTypeIds)],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_band' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_band')) {
            $this->merge([
                'is_band' => filter_var($this->input('is_band'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'اسم المستخدم مطلوب.',
            'email.unique' => 'اسم المستخدم هذا غير متاح.',
            'type_id.required' => 'الصلاحية مطلوبة.',
            'type_id.in' => 'نوع المستخدم غير مسموح.',
        ];
    }
}
