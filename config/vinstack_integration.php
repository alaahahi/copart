<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vinstack → Copart integration
    |--------------------------------------------------------------------------
    |
    | Shared secret for server-to-server vehicle export. Set the same token
    | in vinstack-lite (COPART_API_TOKEN / accounting export settings).
    |
    */

    'token' => env('VINSTACK_INTEGRATION_TOKEN'),

    /*
    | Tenant owner_id used when creating clients/cars without a Sanctum user.
    | Typically the main company user id in this ERP.
    */
    'owner_id' => (int) env('VINSTACK_INTEGRATION_OWNER_ID', 0),

    'max_images' => (int) env('VINSTACK_INTEGRATION_MAX_IMAGES', 15),

];
