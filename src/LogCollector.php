<?php

namespace PentaLogger;

use Illuminate\Support\Str;

class LogCollector
{
    protected array $config;
    protected string $logFile;
    protected array $maskFields;
    protected array $maskHeaders;
    protected ?string $currentRequestId = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logFile = $this->getLogFilePath();
        $this->maskFields = $config['mask_fields'] ?? [
            'password',
            'password_confirmation',
            'credit_card',
            'cvv',
            'secret',
            'token',
            'api_key',
        ];
        $this->maskHeaders = $config['mask_headers'] ?? [
            'Authorization',
            'Cookie',
            'X-API-Key',
            'X-Auth-Token',
        ];

        $this->ensureLogFileExists();
    }

    protected function getLogFilePath(): string
    {
        $storagePath = storage_path('penta-logger');

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        return $storagePath . '/logs.jsonl';
    }

    protected function ensureLogFileExists(): void
    {
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }
    }

    public function log(string $type, array $data): void
    {
        $entry = [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->maskSensitiveData($data),
        ];

        $this->writeLog($entry);
    }

    public function logRequest(array $data): void
    {
        $this->log('request', $data);
    }

    public function setCurrentRequestId(string $requestId): void
    {
        $this->currentRequestId = $requestId;
    }

    public function getCurrentRequestId(): ?string
    {
        return $this->currentRequestId;
    }

    public function clearCurrentRequestId(): void
    {
        $this->currentRequestId = null;
    }

    public function logError(array $data): void
    {
        $this->log('error', $data);
    }

    public function logExternalApi(array $data): void
    {
        $this->log('external_api', $data);
    }

    public function logJob(array $data): void
    {
        $this->log('job', $data);
    }

    public function logSchedule(array $data): void
    {
        $this->log('schedule', $data);
    }

    protected function writeLog(array $entry): void
    {
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        $handle = fopen($this->logFile, 'a');
        if ($handle) {
            flock($handle, LOCK_EX);
            fwrite($handle, $line);
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->trimLogs();
    }

    protected function trimLogs(): void
    {
        $maxLogs = $this->config['max_logs'] ?? 500;

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (count($lines) > $maxLogs) {
            $lines = array_slice($lines, -$maxLogs);
            file_put_contents($this->logFile, implode("\n", $lines) . "\n", LOCK_EX);
        }
    }

    public function getLogs(?string $type = null, ?string $since = null): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry) {
                continue;
            }

            if ($type && $entry['type'] !== $type) {
                continue;
            }

            if ($since && $entry['timestamp'] <= $since) {
                continue;
            }

            $logs[] = $entry;
        }

        // Return newest first
        return array_reverse($logs);
    }

    public function getLogsSince(string $timestamp): array
    {
        return $this->getLogs(null, $timestamp);
    }

    public function clear(): void
    {
        file_put_contents($this->logFile, '', LOCK_EX);
    }

    protected function maskSensitiveData(array $data): array
    {
        return $this->recursiveMask($data);
    }

    protected function recursiveMask(mixed $data, string $parentKey = ''): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->recursiveMask($value, strtolower((string) $key));
            }
            return $result;
        }

        if ($this->shouldMaskField($parentKey)) {
            return '******';
        }

        return $data;
    }

    protected function shouldMaskField(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->maskFields as $field) {
            if (str_contains($key, strtolower($field))) {
                return true;
            }
        }

        return false;
    }

    public function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $key => $value) {
            if ($this->shouldMaskHeader($key)) {
                $masked[$key] = '******';
            } else {
                $masked[$key] = $value;
            }
        }
        return $masked;
    }

    protected function shouldMaskHeader(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->maskHeaders as $header) {
            if (strtolower($header) === $key) {
                return true;
            }
        }

        return false;
    }

    public function shouldIgnorePath(string $path): bool
    {
        $ignorePaths = $this->config['ignore_paths'] ?? [
            '_penta-logger/*',
            'telescope/*',
            'horizon/*',
        ];

        foreach ($ignorePaths as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
