<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionsContract extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'transactions_contract';

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
        's_amount',
        'b_amount',
        'parent_id'
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
