<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class QueueDebtNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|integer|exists:users,id',
            'balance' => 'nullable|numeric',
        ];
    }
}
