<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractImg extends Model
{
    use HasFactory;
    protected $table = 'contract_img';
    protected $fillable = [
        'id',
        'name',
        'car_id',
        'created_at',
        'updated_at'
    ];
  }