<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class SystemConfig extends Model
{
    use HasFactory;
   // use Searchable;
    protected $table = 'system_config';
    protected $fillable = [
        'id',
        'first_title_ar',
        'first_title_kr',
        'second_title_ar',
        'second_title_kr',
        'third_title_ar',
        'third_title_kr',
    ];
}
