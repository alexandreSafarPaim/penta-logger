<?php

namespace PentaLogger\Listeners;

use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use PentaLogger\LogCollector;

class HttpClientListener
{
    protected LogCollector $collector;
    protected array $pendingRequests = [];

    public function __construct(LogCollector $collector)
    {
        $this->collector = $collector;
    }

    public function handleRequestSending(RequestSending $event): void
    {
        $request = $event->request;
        $requestId = spl_object_id($request);

        $this->pendingRequests[$requestId] = [
            'start_time' => microtime(true),
            'request' => $this->captureRequest($request),
        ];
    }

    public function handleResponseReceived(ResponseReceived $event): void
    {
        $request = $event->request;
        $response = $event->response;
        $requestId = spl_object_id($request);

        $pendingData = $this->pendingRequests[$requestId] ?? null;
        unset($this->pendingRequests[$requestId]);

        $startTime = $pendingData['start_time'] ?? microtime(true);
        $requestData = $pendingData['request'] ?? $this->captureRequest($request);
        $duration = (microtime(true) - $startTime) * 1000;

        $this->collector->logExternalApi([
            'method' => $requestData['method'],
            'url' => $requestData['url'],
            'base_url' => $this->extractBaseUrl($requestData['url']),
            'endpoint' => $this->extractEndpoint($requestData['url']),
            'request' => [
                'headers' => $requestData['headers'],
                'body' => $requestData['body'],
            ],
            'response' => $this->captureResponse($response),
            'status' => $response->status(),
            'duration_ms' => round($duration, 2),
            'successful' => $response->successful(),
        ]);
    }

    protected function captureRequest(Request $request): array
    {
        $body = $request->body();
        $headers = $this->collector->maskHeaders($request->headers());

        if ($this->isJson($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $body = $decoded;
            }
        }

        return [
            'method' => $request->method(),
            'url' => $request->url(),
            'headers' => $headers,
            'body' => $body,
        ];
    }

    protected function captureResponse(Response $response): array
    {
        $body = $response->body();
        $headers = $this->collector->maskHeaders($response->headers());

        if ($this->isJson($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $body = $decoded;
            }
        }

        if (is_string($body) && strlen($body) > 10000) {
            $body = substr($body, 0, 10000) . '... [truncated]';
        }

        return [
            'headers' => $headers,
            'body' => $body,
            'size' => strlen($response->body()),
        ];
    }

    protected function isJson(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        $firstChar = $string[0];
        return $firstChar === '{' || $firstChar === '[';
    }

    protected function extractBaseUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (!$parsed) {
            return $url;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    protected function extractEndpoint(string $url): string
    {
        $parsed = parse_url($url);
        if (!$parsed) {
            return $url;
        }

        $path = $parsed['path'] ?? '/';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return $path . $query;
    }
}
