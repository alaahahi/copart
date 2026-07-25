<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QA / Playwright monitoring
    |--------------------------------------------------------------------------
    |
    | HTTP panel + run APIs (/qa/e2e*) stay off unless explicitly enabled.
    | Production/live: leave QA_E2E_ENABLED=false (or unset).
    | Local re-enable: QA_E2E_ENABLED=true
    |
    */

    'enabled' => filter_var(env('QA_E2E_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'admin_type_ids' => array_map('intval', array_filter(explode(',', env('QA_ADMIN_TYPE_IDS', '1')))),

    'base_url' => env('E2E_BASE_URL', env('APP_URL')),

    // Symfony Process timeout per chunk (seconds). Default 15 minutes.
    'timeout_seconds' => (int) env('QA_E2E_TIMEOUT', 900),

    // PHP max_execution_time for the HTTP/Artisan runner. 0 = unlimited.
    'php_max_execution_seconds' => (int) env('QA_E2E_PHP_TIMEOUT', 0),

    'max_log_chars' => (int) env('QA_E2E_MAX_LOG', 200000),

];
