<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeleteAuctionRequest extends FormRequest
{
    /**
     * Tenant ownership is enforced by scoping the `exists` rule to the
     * current user's owner_id, mirroring DeleteCarRequest's convention.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $ownerId = Auth::user()->owner_id;

        return [
            'id' => ['required', 'integer', 'min:1', Rule::exists('auctions', 'id')->where('owner_id', $ownerId)],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف المزاد مطلوب.',
            'id.exists' => 'المزاد غير موجود.',
        ];
    }
}
