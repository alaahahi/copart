<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionsImages extends Model
{
    use HasFactory;
    protected $table = 'transactions_images';
    protected $fillable = [
        'id',
        'name',
        'transactions_id',
        'created_at',
        'updated_at'
    ];
  }