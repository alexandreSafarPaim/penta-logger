<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable PentaLogger
    |--------------------------------------------------------------------------
    |
    | This option controls whether PentaLogger is enabled. When disabled, no
    | logs will be captured and the dashboard will not be accessible.
    |
    */
    'enabled' => env('PENTA_LOGGER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication for the PentaLogger dashboard. When both user and
    | password are set, authentication will be required to access the dashboard.
    | Leave empty to disable authentication.
    |
    */
    'auth' => [
        'user' => env('PENTA_LOGGER_USER'),
        'password' => env('PENTA_LOGGER_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for accessing the PentaLogger dashboard. By default, the
    | dashboard will be available at /_penta-logger
    |
    */
    'route_prefix' => env('PENTA_LOGGER_ROUTE_PREFIX', '_penta-logger'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware that should be applied to the PentaLogger routes. You can
    | add authentication middleware here to protect the dashboard.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Maximum Logs
    |--------------------------------------------------------------------------
    |
    | The maximum number of logs to keep in storage. Older logs will be
    | automatically removed when this limit is exceeded.
    |
    */
    'max_logs' => env('PENTA_LOGGER_MAX_LOGS', 500),

    /*
    |--------------------------------------------------------------------------
    | Allow in Production
    |--------------------------------------------------------------------------
    |
    | By default, PentaLogger is disabled in production environments for
    | security reasons. Set this to true to enable it in production.
    |
    | WARNING: Only enable in production if you have proper authentication
    | middleware configured.
    |
    */
    'allow_production' => env('PENTA_LOGGER_ALLOW_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed IPs
    |--------------------------------------------------------------------------
    |
    | If set, only requests from these IP addresses will be able to access
    | the PentaLogger dashboard. Leave empty to allow all IPs (in non-production).
    |
    */
    'allowed_ips' => [],

    /*
    |--------------------------------------------------------------------------
    | Ignore Paths
    |--------------------------------------------------------------------------
    |
    | Requests to these paths will not be logged. Supports wildcard patterns.
    |
    */
    'ignore_paths' => [
        '_penta-logger/*',
        '_penta-logger',
        'telescope/*',
        'horizon/*',
        '_debugbar/*',
        'livewire/*',
        'sanctum/*',
        '_ignition/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mask Headers
    |--------------------------------------------------------------------------
    |
    | These request/response headers will be masked in the logs for security.
    |
    */
    'mask_headers' => [
        'Authorization',
        'Cookie',
        'Set-Cookie',
        'X-API-Key',
        'X-Auth-Token',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mask Fields
    |--------------------------------------------------------------------------
    |
    | Fields containing these strings in their names will be masked in the
    | logs. The matching is case-insensitive and partial.
    |
    */
    'mask_fields' => [
        'password',
        'password_confirmation',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'access_token',
        'refresh_token',
    ],
];
