# Penta Logger

Real-time log streaming dashboard for Laravel applications. Monitor requests, errors, external APIs, queued jobs, and scheduled tasks - all in one place with zero configuration.

## Features

- **Request Logs**: HTTP method, endpoint, headers, request/response body, status code, duration
- **Error Logs**: Exception details with optimized stack trace (only your application code)
- **External API Logs**: Track all HTTP client calls with full request/response data
- **Job Logs**: Monitor queued jobs - status, duration, attempts, payload, and exceptions
- **Schedule Logs**: Track scheduled tasks - command, cron expression, duration, output
- **Real-time Dashboard**: Server-Sent Events (SSE) for instant updates
- **Advanced Filters**: Filter by method, status, endpoint, IP, and date range
- **Zero Config**: Works out of the box, no database or setup needed
- **Secure**: Disabled in production by default, automatic sensitive data masking

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require your-vendor/penta-logger --dev
```

That's it! Visit `http://your-app.test/_penta-logger` to see the dashboard.

## What Gets Logged

### Requests
All HTTP requests to your application with:
- IP address, HTTP method, URL, and path
- Request headers and body
- Response headers and body
- Status code and duration

### Errors
All exceptions with:
- Exception class and message
- File and line number
- Filtered stack trace (only your code, not vendor)
- Request context

### External APIs
All HTTP client calls (`Http::get()`, etc.) with:
- URL and method
- Request headers and body
- Response status, headers, and body
- Duration

### Jobs
All queued jobs with:
- Job class name and ID
- Queue name and connection
- Attempt number and max tries
- Job payload/data
- Duration and status (completed/failed)
- Exception details if failed

### Scheduled Tasks
All scheduled commands with:
- Command or closure
- Cron expression
- Duration and status
- Output (if available)
- Exception details if failed

## Configuration (Optional)

Publish the config file to customize:

```bash
php artisan vendor:publish --tag=penta-logger-config
```

### Environment Variables

```env
PENTA_LOGGER_ENABLED=true
PENTA_LOGGER_USER=admin
PENTA_LOGGER_PASSWORD=secret
PENTA_LOGGER_ROUTE_PREFIX=_penta-logger
PENTA_LOGGER_MAX_LOGS=500
PENTA_LOGGER_ALLOW_PRODUCTION=false
```

### Config Options

```php
// config/penta-logger.php

return [
    // Enable/disable the package
    'enabled' => env('PENTA_LOGGER_ENABLED', true),

    // Dashboard authentication
    'auth' => [
        'user' => env('PENTA_LOGGER_USER'),
        'password' => env('PENTA_LOGGER_PASSWORD'),
    ],

    // Dashboard URL prefix
    'route_prefix' => '_penta-logger',

    // Route middleware
    'middleware' => ['web'],

    // Max logs to keep in storage
    'max_logs' => 500,

    // Enable in production (requires auth)
    'allow_production' => false,

    // Paths to ignore (supports wildcards)
    'ignore_paths' => [
        '_penta-logger/*',
        'telescope/*',
        'horizon/*',
    ],

    // Headers to mask
    'mask_headers' => [
        'Authorization',
        'Cookie',
        'X-API-Key',
    ],

    // Fields to mask (partial match)
    'mask_fields' => [
        'password',
        'credit_card',
        'cvv',
        'token',
        'secret',
    ],
];
```

## Production Usage

By default, Penta Logger is **disabled in production**. To enable it safely:

1. Set authentication credentials:

```env
PENTA_LOGGER_USER=admin
PENTA_LOGGER_PASSWORD=your-secure-password
```

2. Enable production mode:

```env
PENTA_LOGGER_ALLOW_PRODUCTION=true
```

## Security

- Disabled in production by default
- Optional basic authentication for dashboard
- Sensitive headers (Authorization, Cookie, etc.) are masked
- Sensitive fields (password, credit_card, token, etc.) are masked
- Stack traces only show your application files (not vendor)
- Large response bodies are truncated

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Your Laravel App                        │
├─────────────────────────────────────────────────────────────┤
│  Middleware          │  Exception Handler  │  Event Listeners│
│  (Requests)          │  (Errors)           │  (APIs/Jobs)    │
└──────────┬───────────┴─────────┬───────────┴────────┬────────┘
           │                     │                    │
           └─────────────────────┼────────────────────┘
                                 ▼
                    ┌────────────────────────┐
                    │     LogCollector       │
                    │   (JSON Lines File)    │
                    └───────────┬────────────┘
                                │
                    ┌───────────┴────────────┐
                    │                        │
              ┌─────▼─────┐          ┌───────▼───────┐
              │ Dashboard │◄────SSE──│ StreamController│
              │  (HTML)   │          │   (Real-time)  │
              └───────────┘          └────────────────┘
```

## License

MIT
