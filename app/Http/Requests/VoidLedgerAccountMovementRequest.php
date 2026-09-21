<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class VoidLedgerAccountMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && in_array((int) Auth::user()->type_id, [1, 6], true);
    }

    public function rules(): array
    {
        $ownerId = (int) Auth::user()->owner_id;

        return [
            'journal_entry_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('journal_entries', 'id')->where(
                    fn ($q) => $q->where('owner_id', $ownerId)->whereNull('deleted_at')
                ),
            ],
            'ledger_account_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('ledger_accounts', 'id')->where(
                    fn ($q) => $q->where('owner_id', $ownerId)
                        ->whereIn('type', ['expense', 'income'])
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'journal_entry_id.required' => 'معرف القيد مطلوب.',
            'journal_entry_id.exists' => 'القيد غير موجود.',
            'ledger_account_id.required' => 'معرف الحساب مطلوب.',
            'ledger_account_id.exists' => 'الحساب غير موجود.',
        ];
    }
}
