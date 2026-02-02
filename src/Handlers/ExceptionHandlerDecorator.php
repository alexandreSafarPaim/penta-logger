<?php

namespace PentaLogger\Handlers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use PentaLogger\LogCollector;
use PentaLogger\Support\TraceFilter;
use Throwable;

class ExceptionHandlerDecorator implements ExceptionHandler
{
    protected ExceptionHandler $handler;
    protected LogCollector $collector;
    protected static bool $isLogging = false;

    public function __construct(ExceptionHandler $handler, LogCollector $collector)
    {
        $this->handler = $handler;
        $this->collector = $collector;
    }

    public function report(Throwable $e): void
    {
        // Prevent infinite loop if logging itself throws an exception
        if (!self::$isLogging) {
            self::$isLogging = true;
            try {
                $this->logException($e);
            } catch (Throwable $loggingException) {
                // Silently ignore logging errors to prevent loops
            } finally {
                self::$isLogging = false;
            }
        }

        $this->handler->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->handler->shouldReport($e);
    }

    public function render($request, Throwable $e)
    {
        return $this->handler->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->handler->renderForConsole($output, $e);
    }

    protected function logException(Throwable $e): void
    {
        $trace = TraceFilter::filter($e->getTrace());

        $request = request();
        $requestContext = null;

        if ($request) {
            $requestContext = [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
            ];
        }

        $this->collector->logError([
            'request_id' => $this->collector->getCurrentRequestId(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $this->getRelativePath($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $trace,
            'previous' => $e->getPrevious() ? [
                'exception' => get_class($e->getPrevious()),
                'message' => $e->getPrevious()->getMessage(),
                'file' => $this->getRelativePath($e->getPrevious()->getFile()),
                'line' => $e->getPrevious()->getLine(),
            ] : null,
            'request' => $requestContext,
        ]);
    }

    protected function getRelativePath(string $path): string
    {
        $basePath = base_path();
        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath) + 1);
        }
        return $path;
    }
}
