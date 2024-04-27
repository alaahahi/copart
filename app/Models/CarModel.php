<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarModel extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'car_model';
    protected $fillable = [
        'id',
        'name',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $dates = ['deleted_at']; // Define the deleted_at column as a date

  }