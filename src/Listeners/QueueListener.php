<?php

namespace PentaLogger\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobExceptionOccurred;
use PentaLogger\LogCollector;

class QueueListener
{
    protected LogCollector $collector;
    protected array $jobStartTimes = [];

    public function __construct(LogCollector $collector)
    {
        $this->collector = $collector;
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $event->job->getJobId();
        $this->jobStartTimes[$jobId] = microtime(true);
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $this->logJob($event, 'completed');
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $this->logJob($event, 'failed', $event->exception);
    }

    public function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        // This is logged but job might retry, so we mark it as 'exception'
        $this->logJob($event, 'exception', $event->exception);
    }

    protected function logJob(object $event, string $status, ?\Throwable $exception = null): void
    {
        $job = $event->job;
        $jobId = $job->getJobId();

        $startTime = $this->jobStartTimes[$jobId] ?? microtime(true);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        unset($this->jobStartTimes[$jobId]);

        $payload = $job->payload();
        $jobName = $payload['displayName'] ?? $job->resolveName();

        // Extract job data safely
        $jobData = [];
        if (isset($payload['data']['command'])) {
            $command = @unserialize($payload['data']['command']);
            if ($command !== false) {
                $jobData = $this->extractJobProperties($command);
            }
        }

        $data = [
            'job_id' => $jobId,
            'name' => $jobName,
            'queue' => $job->getQueue() ?? 'default',
            'connection' => $event->connectionName,
            'status' => $status,
            'attempt' => $job->attempts(),
            'max_tries' => $payload['maxTries'] ?? null,
            'timeout' => $payload['timeout'] ?? null,
            'duration_ms' => $duration,
            'data' => $jobData,
        ];

        if ($exception) {
            $data['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $this->collector->logJob($data);
    }

    protected function extractJobProperties(object $job): array
    {
        $properties = [];
        $reflection = new \ReflectionClass($job);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $name = $property->getName();

            // Skip internal Laravel properties
            if (in_array($name, ['job', 'connection', 'queue', 'chainConnection', 'chainQueue', 'chainCatchCallbacks', 'chained', 'delay', 'afterCommit', 'middleware', 'backoff'])) {
                continue;
            }

            try {
                $value = $property->getValue($job);

                // Convert objects to string representation
                if (is_object($value)) {
                    if (method_exists($value, 'getKey')) {
                        $value = get_class($value) . ':' . $value->getKey();
                    } elseif (method_exists($value, '__toString')) {
                        $value = (string) $value;
                    } else {
                        $value = get_class($value);
                    }
                }

                $properties[$name] = $value;
            } catch (\Throwable $e) {
                $properties[$name] = '[unable to read]';
            }
        }

        return $properties;
    }
}
