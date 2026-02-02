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

        // Capture request headers
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = is_array($values) ? implode(', ', $values) : $values;
        }

        // Capture request body
        $requestBody = null;
        try {
            $contentType = $request->header('Content-Type', '');
            if (str_contains($contentType, 'application/json')) {
                $requestBody = $request->json()->all();
            } elseif (str_contains($contentType, 'multipart/form-data')) {
                $requestBody = $request->all();
                foreach ($request->allFiles() as $key => $file) {
                    if (is_array($file)) {
                        $requestBody[$key] = array_map(fn($f) => "[File: {$f->getClientOriginalName()}]", $file);
                    } else {
                        $requestBody[$key] = "[File: {$file->getClientOriginalName()}]";
                    }
                }
            } else {
                $requestBody = $request->all();
            }
        } catch (Throwable $e) {
            $requestBody = null;
        }

        $requestData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'headers' => $collector->maskHeaders($headers),
            'query' => $request->query(),
            'body' => $requestBody,
        ];

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $collector->clearCurrentRequestId();
            throw $e;
        }

        // Capture response and log
        try {
            $responseData = null;

            // Check for StreamedResponse
            if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
                $responseData = [
                    'headers' => [],
                    'body' => '[Streamed Response]',
                    'size' => 0,
                ];
            } else {
                $responseHeaders = [];
                foreach ($response->headers->all() as $key => $values) {
                    $responseHeaders[$key] = is_array($values) ? implode(', ', $values) : $values;
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
                    'headers' => $collector->maskHeaders($responseHeaders),
                    'body' => $body,
                    'size' => strlen($content),
                ];
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
                'status' => $response->getStatusCode(),
                'duration_ms' => round($duration, 2),
                'has_error' => $response->getStatusCode() >= 400,
            ];

            $collector->logRequest($logData);
        } catch (Throwable $e) {
            // Ignore logging errors
        }

        $collector->clearCurrentRequestId();
        return $response;
    }
}
