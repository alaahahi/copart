<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Name extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'name';
    protected $fillable = [
        'id',
        'name',
        'name_en',
        'company_id',
        'status',
        'created_at',
        'updated_at'
    ];
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    protected $dates = ['deleted_at']; // Define the deleted_at column as a date

  }