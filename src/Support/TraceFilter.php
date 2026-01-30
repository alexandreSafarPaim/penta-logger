<?php

namespace PentaLogger\Support;

class TraceFilter
{
    protected static array $excludePatterns = [
        '/vendor/',
        '/bootstrap/',
        '/artisan',
    ];

    protected static array $includeVendorPatterns = [
        '/penta-logger/',
    ];

    public static function filter(array $trace, int $limit = 15): array
    {
        $filtered = [];

        foreach ($trace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }

            $file = $frame['file'];

            if (self::shouldExclude($file)) {
                continue;
            }

            $filtered[] = [
                'file' => self::getRelativePath($file),
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    protected static function shouldExclude(string $file): bool
    {
        foreach (self::$includeVendorPatterns as $pattern) {
            if (str_contains($file, $pattern)) {
                return false;
            }
        }

        foreach (self::$excludePatterns as $pattern) {
            if (str_contains($file, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected static function getRelativePath(string $path): string
    {
        $basePath = base_path();
        if (str_starts_with($path, $basePath)) {
            return substr($path, strlen($basePath) + 1);
        }
        return $path;
    }
}
