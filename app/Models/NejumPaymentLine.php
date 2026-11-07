<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NejumPaymentLine extends Model
{
    use SoftDeletes;

    public const TYPE_CAR = 'car';

    public const TYPE_SHIPPING = 'shipping';

    protected $table = 'nejum_payment_lines';

    protected $fillable = [
        'owner_id',
        'line_type',
        'car_id',
        'pr_date',
        'car_info',
        'vin',
        'lot',
        'note',
        'amount',
        'for_nejum',
        'nejum_shipping',
        'nejum_payments',
        'nejum_payment_date',
        'nejum_note',
        'transaction_fee',
        'commission_3mula',
        'kaml_must_pay',
        'kaml_payment',
        'kaml_payment_date',
        'us_fee',
        'customer_commission_3mula',
        'customer_must_pay',
        'customer_name',
        'customer_payment_date',
        'created_by',
    ];

    protected $casts = [
        'pr_date' => 'date',
        'nejum_payment_date' => 'date',
        'kaml_payment_date' => 'date',
        'customer_payment_date' => 'date',
        'amount' => 'float',
        'for_nejum' => 'float',
        'nejum_shipping' => 'float',
        'nejum_payments' => 'float',
        'transaction_fee' => 'float',
        'commission_3mula' => 'float',
        'kaml_must_pay' => 'float',
        'kaml_payment' => 'float',
        'us_fee' => 'float',
        'customer_commission_3mula' => 'float',
        'customer_must_pay' => 'float',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
