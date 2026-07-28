<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class ResetSystemRequest extends FormRequest
{
    /**
     * System wipe — admin only (type_id = 1).
     * Dedicated SYSTEM_RESET_PASSWORD verified in withValidator (timing-safe).
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && (int) $user->type_id === 1;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = Auth::user();
            if (! $user) {
                $validator->errors()->add('password', 'يجب تسجيل الدخول.');

                return;
            }

            $expected = (string) config('app.system_reset_password', '');
            $provided = (string) $this->input('password', '');

            if ($expected === '' || ! hash_equals($expected, $provided)) {
                $validator->errors()->add('password', 'كلمة مرور التصفير غير صحيحة.');
            }

            $confirm = trim((string) $this->input('confirmation', ''));
            $normalized = mb_strtoupper($confirm, 'UTF-8');
            if ($confirm !== 'تصفير' && $normalized !== 'RESET') {
                $validator->errors()->add(
                    'confirmation',
                    'اكتب كلمة التأكيد «تصفير» أو RESET للمتابعة.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'password.required' => 'أدخل كلمة مرور التصفير.',
            'confirmation.required' => 'أدخل كلمة التأكيد.',
        ];
    }
}
