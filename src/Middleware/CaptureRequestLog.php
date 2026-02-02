<?php

namespace PentaLogger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PentaLogger\LogCollector;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CaptureRequestLog
{
    protected ?LogCollector $collector = null;
    protected float $startTime;
    protected string $requestId;

    // No constructor injection - lazy resolve to avoid boot timing issues in Laravel 8

    protected function getCollector(): LogCollector
    {
        if ($this->collector === null) {
            $this->collector = app(LogCollector::class);
        }
        return $this->collector;
    }

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $this->startTime = microtime(true);

        // Early return for ignored paths
        try {
            if ($this->getCollector()->shouldIgnorePath($request->path())) {
                return $next($request);
            }
        } catch (Throwable $e) {
            // If collector fails, just pass through
            return $next($request);
        }

        // Generate unique request ID
        $this->requestId = Str::uuid()->toString();

        try {
            $this->getCollector()->setCurrentRequestId($this->requestId);
        } catch (Throwable $e) {
            // Ignore errors
        }

        $requestData = $this->captureRequest($request);

        try {
            /** @var SymfonyResponse $response */
            $response = $next($request);

            try {
                $this->logRequest($request, $response, $requestData);
            } catch (Throwable $loggingException) {
                // Silently ignore logging errors to prevent blocking requests
            }

            return $response;
        } catch (Throwable $e) {
            try {
                $this->logRequest($request, null, $requestData, $e);
            } catch (Throwable $loggingException) {
                // Silently ignore logging errors
            }
            throw $e;
        }
    }

    protected function captureRequest(Request $request): array
    {
        try {
            return [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'headers' => $this->getCollector()->maskHeaders($this->normalizeHeaders($request->headers->all())),
                'query' => $request->query(),
                'body' => $this->getRequestBody($request),
            ];
        } catch (Throwable $e) {
            return [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'headers' => [],
                'query' => [],
                'body' => null,
            ];
        }
    }

    protected function getRequestBody(Request $request): mixed
    {
        try {
            $contentType = $request->header('Content-Type', '');

            if (str_contains($contentType, 'application/json')) {
                return $request->json()->all();
            }

            if (str_contains($contentType, 'multipart/form-data')) {
                $data = $request->all();
                foreach ($request->allFiles() as $key => $file) {
                    if (is_array($file)) {
                        $data[$key] = array_map(fn($f) => "[File: {$f->getClientOriginalName()}]", $file);
                    } else {
                        $data[$key] = "[File: {$file->getClientOriginalName()}]";
                    }
                }
                return $data;
            }

            return $request->all();
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function logRequest(
        Request $request,
        ?SymfonyResponse $response,
        array $requestData,
        ?Throwable $exception = null
    ): void {
        $duration = (microtime(true) - $this->startTime) * 1000;

        $logData = [
            'request_id' => $this->requestId,
            'ip' => $request->ip(),
            'method' => $requestData['method'],
            'url' => $requestData['url'],
            'path' => $requestData['path'],
            'request' => [
                'headers' => $requestData['headers'],
                'query' => $requestData['query'],
                'body' => $requestData['body'],
            ],
            'response' => $response ? $this->captureResponse($response) : null,
            'status' => $response ? $response->getStatusCode() : 500,
            'duration_ms' => round($duration, 2),
            'has_error' => $exception !== null || ($response && $response->getStatusCode() >= 400),
        ];

        $this->getCollector()->logRequest($logData);
        $this->getCollector()->clearCurrentRequestId();
    }

    protected function captureResponse(SymfonyResponse $response): array
    {
        try {
            $headers = $this->normalizeHeaders($response->headers->all());

            // Skip body capture for streaming responses
            if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                return [
                    'headers' => $this->getCollector()->maskHeaders($headers),
                    'body' => '[Streamed Response]',
                    'size' => 0,
                ];
            }

            $content = $response->getContent();
            if ($content === false) {
                $content = '';
            }

            $body = $content;
            $contentType = $response->headers->get('Content-Type', '');

            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $body = $decoded;
                }
            }

            if (is_string($body) && strlen($body) > 10000) {
                $body = substr($body, 0, 10000) . '... [truncated]';
            }

            return [
                'headers' => $this->getCollector()->maskHeaders($headers),
                'body' => $body,
                'size' => strlen($content),
            ];
        } catch (Throwable $e) {
            return [
                'headers' => [],
                'body' => '[Error capturing response]',
                'size' => 0,
            ];
        }
    }

    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $values) {
            $normalized[$key] = is_array($values) ? implode(', ', $values) : $values;
        }
        return $normalized;
    }
}
