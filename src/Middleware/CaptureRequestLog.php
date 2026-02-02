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
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $startTime = microtime(true);

        try {
            $collector = app(LogCollector::class);
        } catch (Throwable $e) {
            return $next($request);
        }

        if ($collector->shouldIgnorePath($request->path())) {
            return $next($request);
        }

        $requestId = Str::uuid()->toString();
        $collector->setCurrentRequestId($requestId);

        // Capture request
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? implode(', ', $values) : $values;
        }

        $requestData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'headers' => $collector->maskHeaders($headers),
            'query' => $request->query(),
            'body' => $this->getRequestBody($request),
        ];

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->logRequestData($collector, $requestId, $request, null, $requestData, $startTime);
            $collector->clearCurrentRequestId();
            throw $e;
        }

        try {
            $this->logRequestData($collector, $requestId, $request, $response, $requestData, $startTime);
        } catch (Throwable $e) {
            // Ignore logging errors
        }

        $collector->clearCurrentRequestId();
        return $response;
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

    protected function logRequestData(
        LogCollector $collector,
        string $requestId,
        Request $request,
        ?SymfonyResponse $response,
        array $requestData,
        float $startTime
    ): void {
        $responseData = null;
        if ($response) {
            // Check for StreamedResponse
            if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                $responseData = [
                    'headers' => [],
                    'body' => '[Streamed Response]',
                    'size' => 0,
                ];
            } else {
                $headers = [];
                foreach ($response->headers->all() as $key => $values) {
                    $headers[$key] = is_array($values) ? implode(', ', $values) : $values;
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

                $responseData = [
                    'headers' => $collector->maskHeaders($headers),
                    'body' => $body,
                    'size' => strlen($content),
                ];
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        $logData = [
            'request_id' => $requestId,
            'ip' => $request->ip(),
            'method' => $requestData['method'],
            'url' => $requestData['url'],
            'path' => $requestData['path'],
            'request' => $requestData,
            'response' => $responseData,
            'status' => $response ? $response->getStatusCode() : 500,
            'duration_ms' => round($duration, 2),
            'has_error' => !$response || $response->getStatusCode() >= 400,
        ];

        $collector->logRequest($logData);
    }
}
