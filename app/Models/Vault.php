<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Independent cash box / قاصة — not a merchant (تاجر).
 * Balances stay on linked Wallet (+ ledger via legacy_user_id) in Phase 1.
 */
class Vault extends Model
{
    use SoftDeletes;

    public const TYPE_CASH = 'cash';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_COMMISSION = 'commission';

    public const TYPE_COMPANY = 'company';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_CONTRACTS = 'contracts';

    protected $fillable = [
        'owner_id',
        'name',
        'code',
        'type',
        'currency_default',
        'is_active',
        'show_in_accounting',
        'wallet_id',
        'legacy_user_id',
        'ledger_account_id',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_accounting' => 'boolean',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function legacyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'legacy_user_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeAccountingShortcuts($query)
    {
        return $query->where('is_active', true)
            ->where('show_in_accounting', true)
            ->whereNotNull('legacy_user_id');
    }
}
