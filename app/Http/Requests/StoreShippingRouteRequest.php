<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreShippingRouteRequest extends FormRequest
{
    /**
     * Any authenticated tenant user may add a shipping route to their own
     * tenant's list (owner_id is derived server-side, never trusted from
     * the request) — mirrors StoreAuctionRequest.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $ownerId = Auth::user()->owner_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('shipping_routes', 'name')->where('owner_id', $ownerId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم طريق الشحن مطلوب.',
            'name.unique' => 'يوجد طريق شحن بهذا الاسم مسبقاً.',
        ];
    }
}
