<?php

namespace PentaLogger;

use Illuminate\Support\Str;

class LogCollector
{
    protected const LOG_TYPES = ['request', 'error', 'external_api', 'job', 'schedule'];

    protected array $config;
    protected string $storagePath;
    protected array $maskFields;
    protected array $maskHeaders;
    protected ?string $currentRequestId = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->storagePath = $this->getStoragePath();
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

        $this->ensureStorageExists();
    }

    protected function getStoragePath(): string
    {
        return storage_path('penta-logger');
    }

    protected function getLogFileForType(string $type): string
    {
        return $this->storagePath . '/' . $type . '.jsonl';
    }

    protected function ensureStorageExists(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function log(string $type, array $data): void
    {
        // Check if this log type is disabled (limit = 0)
        if ($this->getMaxLogsForType($type) === 0) {
            return;
        }

        $entry = [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->maskSensitiveData($data),
        ];

        $this->writeLog($type, $entry);
    }

    protected function getMaxLogsForType(string $type): int
    {
        $maxLogs = $this->config['max_logs'] ?? [];

        // Support legacy single value config
        if (!is_array($maxLogs)) {
            return (int) $maxLogs;
        }

        return (int) ($maxLogs[$type] ?? 500);
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

    protected function writeLog(string $type, array $entry): void
    {
        $logFile = $this->getLogFileForType($type);
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        $handle = fopen($logFile, 'a');
        if ($handle) {
            flock($handle, LOCK_EX);
            fwrite($handle, $line);
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->trimLogsForType($type);
    }

    protected function trimLogsForType(string $type): void
    {
        $logFile = $this->getLogFileForType($type);

        if (!file_exists($logFile)) {
            return;
        }

        $maxLogs = $this->getMaxLogsForType($type);
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (count($lines) > $maxLogs) {
            $lines = array_slice($lines, -$maxLogs);
            file_put_contents($logFile, implode("\n", $lines) . "\n", LOCK_EX);
        }
    }

    public function getLogs(?string $type = null, ?string $since = null): array
    {
        $logs = [];
        $typesToRead = $type ? [$type] : self::LOG_TYPES;

        foreach ($typesToRead as $logType) {
            $logFile = $this->getLogFileForType($logType);

            if (!file_exists($logFile)) {
                continue;
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry) {
                    continue;
                }

                if ($since && $entry['timestamp'] <= $since) {
                    continue;
                }

                $logs[] = $entry;
            }
        }

        // Sort by timestamp descending (newest first)
        usort($logs, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $logs;
    }

    public function getLogsSince(string $timestamp): array
    {
        return $this->getLogs(null, $timestamp);
    }

    public function clear(?string $type = null): void
    {
        $typesToClear = $type ? [$type] : self::LOG_TYPES;

        foreach ($typesToClear as $logType) {
            $logFile = $this->getLogFileForType($logType);
            if (file_exists($logFile)) {
                file_put_contents($logFile, '', LOCK_EX);
            }
        }
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
