<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpensesType extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'expenses_type';
    protected $fillable = [
        'id',
        'name_en',
        'name_ar',
        'name_kr',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $dates = ['deleted_at']; // Define the deleted_at column as a date

  }