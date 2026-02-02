<?php

namespace PentaLogger\Listeners;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use PentaLogger\LogCollector;

class ScheduleListener
{
    protected LogCollector $collector;
    protected array $taskStartTimes = [];

    public function __construct(LogCollector $collector)
    {
        $this->collector = $collector;
    }

    public function handleScheduledTaskStarting(ScheduledTaskStarting $event): void
    {
        $taskId = $this->getTaskId($event->task);
        $this->taskStartTimes[$taskId] = microtime(true);
    }

    public function handleScheduledTaskFinished(ScheduledTaskFinished $event): void
    {
        // Check exit code to determine if the task actually failed
        // In Laravel 8, ScheduledTaskFailed event is not always fired for subprocess failures
        $exitCode = $event->task->exitCode;

        // exitCode of 0 means success, any other value is treated as failure
        // null means the exit code wasn't captured (treat as completed for backward compatibility)
        $status = ($exitCode === 0 || $exitCode === null) ? 'completed' : 'failed';

        $this->logSchedule($event->task, $status, null, $event->runtime ?? null, $exitCode);
    }

    public function handleScheduledTaskFailed(ScheduledTaskFailed $event): void
    {
        $exitCode = $event->task->exitCode ?? 1;
        $this->logSchedule($event->task, 'failed', $event->exception, null, $exitCode);
    }

    public function handleScheduledTaskSkipped(ScheduledTaskSkipped $event): void
    {
        $this->logSchedule($event->task, 'skipped');
    }

    protected function logSchedule(ScheduledEvent $task, string $status, ?\Throwable $exception = null, ?float $runtime = null, ?int $exitCode = null): void
    {
        $taskId = $this->getTaskId($task);

        // Calculate duration
        if ($runtime !== null) {
            $duration = round($runtime * 1000, 2);
        } elseif (isset($this->taskStartTimes[$taskId])) {
            $duration = round((microtime(true) - $this->taskStartTimes[$taskId]) * 1000, 2);
        } else {
            $duration = 0;
        }
        unset($this->taskStartTimes[$taskId]);

        $data = [
            'task_id' => $taskId,
            'command' => $this->getTaskCommand($task),
            'description' => $task->description ?? null,
            'expression' => $task->expression,
            'timezone' => $task->timezone ?? config('app.timezone'),
            'status' => $status,
            'duration_ms' => $duration,
            'without_overlapping' => $task->withoutOverlapping ?? false,
            'run_in_background' => $task->runInBackground ?? false,
            'even_in_maintenance_mode' => $task->evenInMaintenanceMode ?? false,
        ];

        // Include exit code if available
        if ($exitCode !== null) {
            $data['exit_code'] = $exitCode;
        }

        // Try to get output if available
        if (property_exists($task, 'output') && $task->output && file_exists($task->output)) {
            $output = @file_get_contents($task->output);
            if ($output !== false && strlen($output) > 0) {
                $data['output'] = mb_substr($output, 0, 5000); // Limit output size
            }
        }

        if ($exception) {
            $exceptionData = $this->getExceptionChain($exception);

            // Try to extract real exception from output (for subprocess commands)
            if (isset($data['output']) && !empty($data['output'])) {
                $parsedException = $this->parseExceptionFromOutput($data['output']);
                if ($parsedException) {
                    $exceptionData['real_exception'] = $parsedException;
                }
            }

            $data['exception'] = $exceptionData;
        } elseif ($status === 'failed' && isset($data['output']) && !empty($data['output'])) {
            // For subprocess failures without exception object, try to parse from output
            $parsedException = $this->parseExceptionFromOutput($data['output']);
            if ($parsedException) {
                $data['exception'] = $parsedException;
            } else {
                // Provide a generic exception info based on exit code
                $data['exception'] = [
                    'class' => 'ProcessFailedException',
                    'message' => "Command exited with code {$exitCode}",
                    'file' => null,
                    'line' => null,
                ];
            }
        } elseif ($status === 'failed') {
            // No output available but task failed
            $data['exception'] = [
                'class' => 'ProcessFailedException',
                'message' => "Command exited with code " . ($exitCode ?? 'unknown'),
                'file' => null,
                'line' => null,
            ];
        }

        $this->collector->logSchedule($data);
    }

    protected function parseExceptionFromOutput(string $output): ?array
    {
        // Remove ANSI escape codes
        $output = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

        // Try to extract exception info from artisan command output
        // Laravel format: "   ExceptionClass \n\n  Message\n\n  at file:line"
        if (preg_match('/^\s*([A-Za-z\\\\]*(Exception|Error))\s*$/m', $output, $classMatch)) {
            $class = trim($classMatch[1]);

            // Extract message - look for lines between exception class and "at file:line"
            $pattern = '/' . preg_quote($class, '/') . '\s*\n+\s*(.+?)\n+\s*at\s+/s';
            if (preg_match($pattern, $output, $msgMatch)) {
                $message = trim($msgMatch[1]);

                // Extract file and line
                $file = null;
                $line = null;
                if (preg_match('/\bat\s+([^\s:]+):(\d+)/m', $output, $locMatch)) {
                    $file = $locMatch[1];
                    $line = (int) $locMatch[2];
                }

                return [
                    'class' => $class,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                ];
            }
        }

        return null;
    }

    protected function getTaskCommand(ScheduledEvent $task): string
    {
        if (property_exists($task, 'command') && $task->command) {
            // Clean up the command string
            $command = $task->command;
            // Remove PHP binary path and artisan if present
            $command = preg_replace('/^.*?artisan\s+/i', 'artisan ', $command);
            return $command ?: 'artisan (unknown)';
        }

        if (property_exists($task, 'callback') && $task->callback) {
            if (is_string($task->callback)) {
                return "Closure: {$task->callback}";
            }
            return 'Closure';
        }

        return 'Unknown';
    }

    protected function getExceptionChain(\Throwable $exception): array
    {
        $data = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // Check for previous exception (the real cause)
        if ($previous = $exception->getPrevious()) {
            $data['previous'] = [
                'class' => get_class($previous),
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ];
        }

        return $data;
    }

    protected function getTaskId(ScheduledEvent $task): string
    {
        return md5($task->expression . $this->getTaskCommand($task));
    }
}
