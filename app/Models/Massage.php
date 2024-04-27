<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Massage extends Model
{
    use HasFactory;
    protected $table = 'massage';
    protected $fillable = [
        'id',
        'image',
        'voice',
        'text',
        'sender_id',
        'receiver_id',
        'lng',
        'lat',
        'aes',
        'is_download',
        'is_read'
    ];
    protected $hidden = [
        'is_download',
        'is_read'
    ];
}