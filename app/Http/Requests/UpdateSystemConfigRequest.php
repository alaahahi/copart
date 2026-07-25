<?php

namespace App\Http\Requests;

use App\Services\WhatsAppQueueService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateSystemConfigRequest extends FormRequest
{
    /**
     * Settings (receipt + branding + WA Queue) — admin only (type_id = 1).
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && (int) $user->type_id === 1;
    }

    public function rules(): array
    {
        return [
            'receipt_template' => 'required|in:default,mkl_usd',
            'receipt_phone' => 'nullable|string|max:255',
            'receipt_address' => 'nullable|string|max:500',
            'receipt_website' => 'nullable|string|max:255',
            'first_title_ar' => 'nullable|string|max:255',
            'second_title_ar' => 'nullable|string|max:255',

            'app_logo' => 'nullable|file|mimes:jpeg,jpg,png,webp,svg|max:2048',
            'app_cover' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_app_logo' => 'sometimes|boolean',
            'remove_app_cover' => 'sometimes|boolean',

            'receipt_logo_left_1' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:4096',
            'receipt_logo_left_2' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:4096',
            'receipt_logo_left_3' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:4096',
            'receipt_logo_haulf' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:4096',
            'receipt_logo_main' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,svg|max:4096',

            'wa_enabled' => 'sometimes|boolean',
            'wa_base_host' => 'nullable|string|max:255|url',
            'wa_tenant' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9._-]+$/',
            'wa_source' => ['nullable', 'string', Rule::in(WhatsAppQueueService::SOURCES)],
            'wa_created_by' => 'nullable|string|max:100',
            'wa_notify_debt' => 'sometimes|boolean',
            'wa_notify_car_created' => 'sometimes|boolean',
            'wa_notify_payment' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $bools = [
            'wa_enabled',
            'wa_notify_debt',
            'wa_notify_car_created',
            'wa_notify_payment',
            'remove_app_logo',
            'remove_app_cover',
        ];

        $merged = [];
        foreach ($bools as $key) {
            if ($this->has($key)) {
                $merged[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (['wa_base_host', 'wa_tenant', 'wa_source', 'wa_created_by'] as $key) {
            if ($this->has($key) && $this->input($key) === '') {
                $merged[$key] = null;
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    public function messages(): array
    {
        return [
            'wa_tenant.regex' => 'معرّف المستأجر (Tenant) يجب أن يكون أحرفاً/أرقاماً فقط مثل kaml-kamal.',
            'wa_base_host.url' => 'رابط المضيف غير صالح.',
            'wa_source.in' => 'مصدر الرسالة (source) غير مدعوم.',
            'app_logo.mimes' => 'الشعار يجب أن يكون jpeg أو png أو webp أو svg.',
            'app_logo.max' => 'حجم الشعار يجب ألا يتجاوز 2 ميغابايت.',
            'app_cover.mimes' => 'الغلاف يجب أن يكون jpeg أو png أو webp.',
            'app_cover.max' => 'حجم الغلاف يجب ألا يتجاوز 5 ميغابايت.',
        ];
    }
}
