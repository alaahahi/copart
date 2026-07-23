<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transactions extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'description',
        'is_pay',
        'morphed_id',
        'morphed_type',
        'currency',
        'created',
        'discount',
        'parent_id',
        'details',
        'tag',
        'journal_entry_id',
    ];
    protected $casts = [
        'details' => 'array',
    ];

    protected $dates = ['deleted_at'];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
    public function morphed()
    {
        return $this->morphTo('morphed', 'morphed_type', 'morphed_id');
    }
    public function TransactionsImages()
    {
        return $this->hasMany(TransactionsImages::class, 'transactions_id');
    }
    /**
     * The double-entry journal posted for this transaction (source of truth
     * for which real ledger account the money hit).
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
    /**
     * Parent leg (e.g. the main cash-box movement a client/قاسة allocation
     * was split off from). Used to resolve the money account when this row
     * itself carries no journal_entry_id.
     */
    public function parent()
    {
        return $this->belongsTo(Transactions::class, 'parent_id');
    }
}
