<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarContract extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'car_contract';

    protected $fillable = [
        'id',
        'name_seller',
        'phone_seller',
        'address_seller',
        'name_buyer',
        'phone_buyer',
        'address_buyer',
        'vin',
        'car_name',
        'modal',
        'color',
        'size',
        'no',
        'note',
        'system_note',
        'car_price',
        'car_paid',
        'tex_seller',
        'tex_seller_dinar',
        'tex_buyer',
        'tex_buyer_dinar',
        'tex_seller_paid',
        'tex_seller_dinar_paid',
        'tex_buyer_paid',
        'tex_buyer_dinar_paid',
        'no_s',
        'car_name_s',
        'modal_s',
        'size_s',
        'color_s',
        'vin_s',
        'created',
        'user_id',
        'owner_id',
        'year_date',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    // public function car()
    // {
    //     // Define a one-to-one relationship with the Car model
    //     return $this->hasOne(Car::class, 'id', 'car_id');
    // }
    
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    // public function car()
    // {
    //     return $this->belongsTo(Car::class);
    // }
}
