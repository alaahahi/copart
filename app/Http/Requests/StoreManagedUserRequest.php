<?php

namespace App\Http\Requests;

use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && (int) $user->type_id === 1;
    }

    public function rules(): array
    {
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
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
            'type_id' => ['required', 'integer', Rule::in($assignableTypeIds)],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'اسم المستخدم مطلوب.',
            'email.unique' => 'اسم المستخدم هذا غير متاح.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'type_id.required' => 'الصلاحية مطلوبة.',
            'type_id.in' => 'نوع المستخدم غير مسموح.',
        ];
    }
}
