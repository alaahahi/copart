<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'company';
    
    protected $fillable = [
        'id',
        'name',
        'name_en',
        'status',
        'created_at',
        'updated_at'
    ];
    
    protected $dates = ['deleted_at']; // Define the deleted_at column as a date

    // Rest of your model code...
}