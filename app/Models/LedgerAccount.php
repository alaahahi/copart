<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerAccount extends Model
{
    protected $fillable = [
        'owner_id',
        'code',
        'name',
        'name_ar',
        'type',
        'currency',
        'party_type',
        'party_id',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'ledger_account_id');
    }

    public function party(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'party_type', 'party_id');
    }

    public function balance(?string $currency = null): float
    {
        $query = $this->lines()->whereHas('entry');
        if ($currency) {
            $query->where('currency', $currency);
        }

        $debit = (float) (clone $query)->sum('debit');
        $credit = (float) (clone $query)->sum('credit');

        // Asset / Expense: debit balance. Liability / Equity / Income: credit balance.
        if (in_array($this->type, ['liability', 'equity', 'income'], true)) {
            return round($credit - $debit, 2);
        }

        return round($debit - $credit, 2);
    }
}
