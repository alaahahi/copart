<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    use HasFactory;
    protected $table = 'user_type';
    protected $fillable = [
        'id',
        'name',
    ];
    public function users() {
        return $this->hasMany(User::class,'type_id');
    }

}