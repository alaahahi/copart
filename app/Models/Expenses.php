<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Expenses extends Model
{
    public $timestamps = false;
    use HasFactory, SoftDeletes;
    protected $table = 'expenses';
    protected $fillable = [
        'id',
        'user_id',
        'reason',
        'amount',
        'note',
        'factor',
        'expenses_type_id',
        'transaction_id',
        'created_at',
        'updated_at',
        'year_date'
    ];

    protected $dates = ['deleted_at']; // Define the deleted_at column as a date
    
    public function morphed()
    {
        return $this->morphTo();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
  }