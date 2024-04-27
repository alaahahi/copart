<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'warehouse';

    protected $fillable = [
        'car_type',
        'date',
        'car_color',
        'year',
        'note',
        'client_id',
        'car_number',
    ];
    protected $dates = ['deleted_at'];



    public function Client()
    {
        return $this->belongsTo(User::class);
    }
    public function CarImages()
    {
        return $this->hasMany(CarImages::class, 'car_id');
    }

}
