<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarImages extends Model
{
    use HasFactory;
    protected $table = 'car_images';
    protected $fillable = [
        'id',
        'name',
        'car_id',
        'created_at',
        'updated_at'
    ];
  }