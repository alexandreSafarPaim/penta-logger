<?php

namespace PentaLogger\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PentaLogger\LogCollector;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    protected LogCollector $collector;

    public function __construct(LogCollector $collector)
    {
        $this->collector = $collector;
    }

    public function stream(Request $request): StreamedResponse
    {
        $since = $request->query('since');

        return new StreamedResponse(function () use ($since) {
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set last timestamp
            $lastTimestamp = $since;

            // Keep connection alive
            $lastPing = time();

            while (true) {
                // Check for new logs
                $logs = $this->collector->getLogsSince($lastTimestamp ?? '');

                foreach ($logs as $log) {
                    echo "event: log\n";
                    echo "data: " . json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

                    if ($log['timestamp'] > ($lastTimestamp ?? '')) {
                        $lastTimestamp = $log['timestamp'];
                    }
                }

                // Send ping every 30 seconds to keep connection alive
                if (time() - $lastPing >= 30) {
                    echo "event: ping\n";
                    echo "data: " . time() . "\n\n";
                    $lastPing = time();
                }

                // Flush output
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }

                // Wait before checking again
                usleep(500000); // 500ms
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $logs = $this->collector->getLogs($type);

        return response()->json($logs);
    }

    public function clear(): JsonResponse
    {
        $this->collector->clear();

        return response()->json(['success' => true]);
    }
}
