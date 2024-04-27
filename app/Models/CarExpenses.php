<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarExpenses extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'car_expenses';
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'user_id',
        'car_id',
        'note',
        'amount_dinar',
        'amount_dollar',
        'reason_id',
        'created',
        'owner_id'
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function cars()
    {
        return $this->hasMany(Car::class, 'user_id', 'id');
    }


    
}