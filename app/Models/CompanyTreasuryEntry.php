<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyTreasuryEntry extends Model
{
    use SoftDeletes;
    protected $table = 'company_treasury_entries';

    protected $fillable = [
        'owner_id',
        'user_id',
        'entry_date',
        'description',
        'tag',
        'currency',
        'debit',
        'credit',
        'balance',
        'is_settled',
        'journal_entry_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'is_settled' => 'boolean',
        'debit' => 'float',
        'credit' => 'float',
        'balance' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
