<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;
    protected $table = 'card';
    protected $fillable = [
        'id',
        'name',
        'name_en',
        'day',
        'price',
        'created_at',
        'updated_at'
    ];
  }