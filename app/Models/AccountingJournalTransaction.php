<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccountingJournalTransaction extends Model
{
    use SoftDeletes,HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'transaction_group',
        'journal_id',
        'debit',
        'credit',
        'currency',
        'memo',
        'tags',
        'ref_class',
        'ref_class_id',
        'post_date',
    ];

    protected $dates = ['post_date'];

    // Other model methods or relationships can be defined here
}