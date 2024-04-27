<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExitCar extends Model
{
    use HasFactory;
    protected $table = 'exit_car';

    protected $fillable = [
        'user_id',
        'car_id',
        'phone',
        'note',
        'created',
        'owner_id'
    ];

    
    public function user()
    {
        // Define a one-to-one relationship with the User model
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function car()
    {
        // Define a one-to-one relationship with the Car model
        return $this->hasOne(Car::class, 'id', 'car_id');
    }
    
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    // public function car()
    // {
    //     return $this->belongsTo(Car::class);
    // }
}
