<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-scoped shipping route/method that a car can be linked to.
 * Managed from Settings ("طرق الشحن") via a chip/tag UI and selected on
 * the car add/edit forms ("طريق الشحن").
 */
class ShippingRoute extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function cars()
    {
        return $this->hasMany(Car::class, 'shipping_route_id');
    }
}
