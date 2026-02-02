<?php

namespace PentaLogger;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use PentaLogger\Http\Controllers\DashboardController;
use PentaLogger\Http\Controllers\LoginController;
use PentaLogger\Http\Controllers\StreamController;
use PentaLogger\Http\Middleware\Authenticate as PentaLoggerAuthenticate;
use PentaLogger\Listeners\HttpClientListener;
use PentaLogger\Listeners\QueueListener;
use PentaLogger\Listeners\ScheduleListener;
use PentaLogger\Middleware\CaptureRequestLog;

class PentaLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/penta-logger.php', 'penta-logger');

        $this->app->singleton(LogCollector::class, function ($app) {
            return new LogCollector($app['config']['penta-logger']);
        });
    }

    public function boot(): void
    {
        if (!$this->shouldEnable()) {
            return;
        }

        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerExceptionHandling();
        $this->registerHttpClientListeners();
        $this->registerQueueListeners();
        $this->registerScheduleListeners();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/penta-logger.php' => config_path('penta-logger.php'),
            ], 'penta-logger-config');
        }
    }

    protected function shouldEnable(): bool
    {
        if (!config('penta-logger.enabled', true)) {
            return false;
        }

        if ($this->app->environment('production') && !config('penta-logger.allow_production', false)) {
            return false;
        }

        return true;
    }

    protected function registerRoutes(): void
    {
        $prefix = config('penta-logger.route_prefix', '_penta-logger');
        $middleware = config('penta-logger.middleware', ['web']);

        // Public routes (login)
        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () {
                Route::get('/login', [LoginController::class, 'showLoginForm'])->name('penta-logger.login');
                Route::post('/login', [LoginController::class, 'login'])->name('penta-logger.login.submit');
                Route::post('/logout', [LoginController::class, 'logout'])->name('penta-logger.logout');
            });

        // Protected routes (dashboard)
        Route::prefix($prefix)
            ->middleware(array_merge($middleware, [PentaLoggerAuthenticate::class]))
            ->group(function () {
                Route::get('/', [DashboardController::class, 'index'])->name('penta-logger.dashboard');
                Route::get('/stream', [StreamController::class, 'stream'])->name('penta-logger.stream');
                Route::get('/logs', [StreamController::class, 'logs'])->name('penta-logger.logs');
                Route::post('/clear', [StreamController::class, 'clear'])->name('penta-logger.clear');
            });
    }

    protected function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(CaptureRequestLog::class);
        }
    }

    protected function registerExceptionHandling(): void
    {
        $this->app->extend(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            function ($handler, $app) {
                return new Handlers\ExceptionHandlerDecorator($handler, $app->make(LogCollector::class));
            }
        );
    }

    protected function registerHttpClientListeners(): void
    {
        $listener = new HttpClientListener($this->app->make(LogCollector::class));

        Event::listen(RequestSending::class, [$listener, 'handleRequestSending']);
        Event::listen(ResponseReceived::class, [$listener, 'handleResponseReceived']);
    }

    protected function registerQueueListeners(): void
    {
        $listener = new QueueListener($this->app->make(LogCollector::class));

        Event::listen(JobProcessing::class, [$listener, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [$listener, 'handleJobProcessed']);
        Event::listen(JobFailed::class, [$listener, 'handleJobFailed']);
        Event::listen(JobExceptionOccurred::class, [$listener, 'handleJobExceptionOccurred']);
    }

    protected function registerScheduleListeners(): void
    {
        // Schedule events were added in Laravel 8.65
        if (!class_exists(ScheduledTaskStarting::class)) {
            return;
        }

        $listener = new ScheduleListener($this->app->make(LogCollector::class));

        Event::listen(ScheduledTaskStarting::class, [$listener, 'handleScheduledTaskStarting']);
        Event::listen(ScheduledTaskFinished::class, [$listener, 'handleScheduledTaskFinished']);
        Event::listen(ScheduledTaskFailed::class, [$listener, 'handleScheduledTaskFailed']);
        Event::listen(ScheduledTaskSkipped::class, [$listener, 'handleScheduledTaskSkipped']);
    }
}
