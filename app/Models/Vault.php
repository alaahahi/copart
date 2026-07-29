<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cash box / قاصة نقدية — balances live on ledger_account_id (COA), not Wallet.
 * Historical non-cash types remain for legacy rows but are not offered on create.
 */
class Vault extends Model
{
    use SoftDeletes;

    public const TYPE_CASH = 'cash';

    public const TYPE_BANK = 'bank';

    public const TYPE_SAFE = 'safe';

    /** @deprecated Historical — do not offer on create */
    public const TYPE_SYSTEM = 'system';

    /** @deprecated Historical — do not offer on create */
    public const TYPE_COMMISSION = 'commission';

    /** @deprecated Historical — do not offer on create */
    public const TYPE_COMPANY = 'company';

    /** @deprecated Historical — do not offer on create */
    public const TYPE_EXPENSE = 'expense';

    /** @deprecated Historical — do not offer on create */
    public const TYPE_SUPPLIER = 'supplier';

    /** @deprecated Historical — do not offer on create */
    public const TYPE_CONTRACTS = 'contracts';

    /**
     * Types treated as cash boxes for filtering / transfers / receipts.
     * mainBox (type system + code mainBox) is also treated as cash via isCashBox().
     *
     * @var list<string>
     */
    public const CASH_TYPES = [
        self::TYPE_CASH,
        self::TYPE_BANK,
        self::TYPE_SAFE,
    ];

    protected $fillable = [
        'owner_id',
        'name',
        'code',
        'type',
        'currency_default',
        'is_active',
        'show_in_accounting',
        'legacy_user_id',
        'ledger_account_id',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_accounting' => 'boolean',
    ];

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

    /**
     * Active cash boxes: type in CASH_TYPES, or legacy system mainBox.
     */
    public function scopeCashBoxes(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('type', self::CASH_TYPES)
                ->orWhere(function (Builder $inner) {
                    $inner->where('type', self::TYPE_SYSTEM)
                        ->whereRaw('LOWER(code) = ?', ['mainbox']);
                });
        });
    }

    public function isCashBox(): bool
    {
        if (in_array((string) $this->type, self::CASH_TYPES, true)) {
            return true;
        }

        return strcasecmp((string) $this->type, self::TYPE_SYSTEM) === 0
            && strcasecmp((string) $this->code, 'mainBox') === 0;
    }
}
