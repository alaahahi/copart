<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinstackImportRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'owner_id',
        'vin',
        'vinstack_vehicle_id',
        'status',
        'payload',
        'dealer_phone',
        'dealer_name',
        'dealer_company',
        'make',
        'model',
        'year',
        'car_id',
        'client_id',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'reviewed_at' => 'datetime',
        'vinstack_vehicle_id' => 'integer',
        'car_id' => 'integer',
        'client_id' => 'integer',
        'reviewed_by' => 'integer',
        'owner_id' => 'integer',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
