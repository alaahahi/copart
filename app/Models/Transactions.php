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
        'details'
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
}
