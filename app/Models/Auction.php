<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-scoped auction house (Copart, IAAI, Manheim, ...) that a car can
 * be linked to. Managed from Settings ("المزادات") via a chip/tag UI and
 * selected on the car add/edit forms ("المزاد").
 */
class Auction extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function cars()
    {
        return $this->hasMany(Car::class, 'auction_id');
    }
}
