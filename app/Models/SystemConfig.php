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

    /** Production table has no created_at / updated_at columns. */
    public $timestamps = false;

    protected $fillable = [
        'id',
        'first_title_ar',
        'first_title_kr',
        'second_title_ar',
        'second_title_kr',
        'third_title_ar',
        'third_title_kr',
        'receipt_template',
        'receipt_phone',
        'receipt_address',
        'receipt_website',
        'receipt_logo_left_1',
        'receipt_logo_left_2',
        'receipt_logo_left_3',
        'receipt_logo_haulf',
        'receipt_logo_main',
        'app_logo',
        'app_cover',
        'wa_enabled',
        'wa_base_host',
        'wa_tenant',
        'wa_source',
        'wa_created_by',
        'wa_notify_debt',
        'wa_notify_car_created',
        'wa_notify_payment',
    ];

    protected $casts = [
        'wa_enabled' => 'boolean',
        'wa_notify_debt' => 'boolean',
        'wa_notify_car_created' => 'boolean',
        'wa_notify_payment' => 'boolean',
    ];
}
