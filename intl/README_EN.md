# Penta Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)
[![License](https://img.shields.io/packagist/l/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)
[![PHP Version](https://img.shields.io/packagist/php-v/alexandresafarpaim/penta-logger.svg?style=flat-square)](https://packagist.org/packages/alexandresafarpaim/penta-logger)

> **[Leia em Português](../README.md)**

Real-time log streaming dashboard for Laravel applications. Monitor requests, errors, external APIs, queued jobs, and scheduled tasks - all in one place with zero configuration.

## Features

- **Request Logs**: HTTP method, endpoint, headers, request/response body, status code, duration
- **Error Logs**: Exception details with optimized stack trace (only your application code)
- **External API Logs**: Track all HTTP client calls with full request/response data
- **Job Logs**: Monitor queued jobs - status, duration, attempts, payload, and exceptions
- **Schedule Logs**: Track scheduled tasks - command, cron expression, duration, output
- **Real-time Dashboard**: Server-Sent Events (SSE) for instant updates
- **Advanced Filters**: Filter by method, status, endpoint, IP, body content, job name, schedule command, and date range
- **Zero Config**: Works out of the box, no database or setup needed
- **Secure**: Disabled in production by default, automatic sensitive data masking

## Requirements

- PHP 8.0+
- Laravel 8.0+ or 9

> **Laravel 10+?** Use the latest version: `composer require alexandresafarpaim/penta-logger:^1.0`

## Installation

```bash
composer require alexandresafarpaim/penta-logger:^0.2
```

> **Tip**: Use `--dev` if you only want it in development. For staging/production usage, install without the flag.

> **Note**: Schedule logs (scheduled tasks) require Laravel 8.65+

### Versions

| Version | Laravel | PHP |
|---------|---------|-----|
| `^1.0` | 10, 11, 12 | ^8.1 |
| `^0.2` (legacy) | 8.0+, 9 | ^8.0 |

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
# General settings
PENTA_LOGGER_ENABLED=true
PENTA_LOGGER_ROUTE_PREFIX=_penta-logger
PENTA_LOGGER_ALLOW_PRODUCTION=false

# Authentication (optional)
PENTA_LOGGER_USER=admin
PENTA_LOGGER_PASSWORD=secret

# Max logs per type (optional, default: 500)
PENTA_LOGGER_MAX_REQUESTS=500
PENTA_LOGGER_MAX_ERRORS=500
PENTA_LOGGER_MAX_EXTERNAL_API=500
PENTA_LOGGER_MAX_JOBS=500
PENTA_LOGGER_MAX_SCHEDULES=500
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

    // Max logs per type (use 0 to disable a type)
    'max_logs' => [
        'request' => env('PENTA_LOGGER_MAX_REQUESTS', 500),
        'error' => env('PENTA_LOGGER_MAX_ERRORS', 500),
        'external_api' => env('PENTA_LOGGER_MAX_EXTERNAL_API', 500),
        'job' => env('PENTA_LOGGER_MAX_JOBS', 500),
        'schedule' => env('PENTA_LOGGER_MAX_SCHEDULES', 500),
    ],

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
