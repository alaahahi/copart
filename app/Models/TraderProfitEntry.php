<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TraderProfitEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'type',
        'client_id',
        'period_from',
        'period_to',
        'amount',
        'currency',
        'memo',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'amount' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
