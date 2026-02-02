<?php

namespace PentaLogger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use PentaLogger\LogCollector;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CaptureRequestLog
{
    protected LogCollector $collector;
    protected float $startTime;
    protected string $requestId;

    public function __construct(LogCollector $collector)
    {
        $this->collector = $collector;
    }

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $this->startTime = microtime(true);

        if ($this->collector->shouldIgnorePath($request->path())) {
            return $next($request);
        }

        // Generate unique request ID and store it
        $this->requestId = Str::uuid()->toString();
        $this->collector->setCurrentRequestId($this->requestId);

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
        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'headers' => $this->collector->maskHeaders($this->normalizeHeaders($request->headers->all())),
            'query' => $request->query(),
            'body' => $this->getRequestBody($request),
        ];
    }

    protected function getRequestBody(Request $request): mixed
    {
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

        $this->collector->logRequest($logData);
        $this->collector->clearCurrentRequestId();
    }

    protected function captureResponse(SymfonyResponse $response): array
    {
        $headers = $this->normalizeHeaders($response->headers->all());

        // Skip body capture for streaming responses
        if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return [
                'headers' => $this->collector->maskHeaders($headers),
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
            'headers' => $this->collector->maskHeaders($headers),
            'body' => $body,
            'size' => strlen($content),
        ];
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
