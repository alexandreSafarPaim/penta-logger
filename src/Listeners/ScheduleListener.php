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
        $this->logSchedule($event->task, 'completed', null, $event->runtime ?? null);
    }

    public function handleScheduledTaskFailed(ScheduledTaskFailed $event): void
    {
        $this->logSchedule($event->task, 'failed', $event->exception);
    }

    public function handleScheduledTaskSkipped(ScheduledTaskSkipped $event): void
    {
        $this->logSchedule($event->task, 'skipped');
    }

    protected function logSchedule(ScheduledEvent $task, string $status, ?\Throwable $exception = null, ?float $runtime = null): void
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

        // Try to get output if available
        if (property_exists($task, 'output') && $task->output && file_exists($task->output)) {
            $output = @file_get_contents($task->output);
            if ($output !== false && strlen($output) > 0) {
                $data['output'] = mb_substr($output, 0, 5000); // Limit output size
            }
        }

        if ($exception) {
            $data['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $this->collector->logSchedule($data);
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

    protected function getTaskId(ScheduledEvent $task): string
    {
        return md5($task->expression . $this->getTaskCommand($task));
    }
}
