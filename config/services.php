<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | WA Queue (https://wa.intellij-app.com/{tenant}/api/v1/queue)
    | Runtime settings live in system_config; env values are defaults only.
    */
    'wa_queue' => [
        'base_host' => env('WA_QUEUE_BASE_HOST', 'https://wa.intellij-app.com'),
        'tenant' => env('WA_QUEUE_TENANT'),
        'source' => env('WA_QUEUE_SOURCE', 'sales'),
        'created_by' => env('WA_QUEUE_CREATED_BY', 'copart-erp'),
    ],

];
