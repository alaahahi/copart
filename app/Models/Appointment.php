<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;
    protected $table = 'appointment';
    protected $fillable = [
        'id',
        'card_id',
        'user_id',
        'start',
        'end',
        'is_come',
        'created_at',
        'updated_at'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function profile()
    {
        return $this->belongsTo(Profile::class,'card_id');
    }

  }