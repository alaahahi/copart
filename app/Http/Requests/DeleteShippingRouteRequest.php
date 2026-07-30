<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DeleteShippingRouteRequest extends FormRequest
{
    /**
     * Tenant ownership is enforced by scoping the `exists` rule to the
     * current user's owner_id, mirroring DeleteAuctionRequest.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $ownerId = Auth::user()->owner_id;

        return [
            'id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('shipping_routes', 'id')->where('owner_id', $ownerId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'معرف طريق الشحن مطلوب.',
            'id.exists' => 'طريق الشحن غير موجود.',
        ];
    }
}
