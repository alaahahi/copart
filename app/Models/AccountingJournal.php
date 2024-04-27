<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingJournal extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'ledger_id',
        'balance',
        'currency',
        'morphed_type',
        'morphed_id',
    ];

    // Other model methods or relationships can be defined here
}