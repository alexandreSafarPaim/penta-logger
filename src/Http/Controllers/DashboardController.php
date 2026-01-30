<?php

namespace PentaLogger\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $html = $this->getDashboardHtml();

        // Inject CSRF token
        $csrfToken = csrf_token();
        $html = str_replace(
            '<input type="hidden" name="_token" id="csrfToken">',
            '<input type="hidden" name="_token" id="csrfToken" value="' . $csrfToken . '">',
            $html
        );

        return response($html)->header('Content-Type', 'text/html');
    }

    protected function getDashboardHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penta Logger - Real-time Logs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #21262d;
            --border-color: #30363d;
            --text-primary: #c9d1d9;
            --text-secondary: #8b949e;
            --text-muted: #6e7681;
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-red: #f85149;
            --accent-yellow: #d29922;
            --accent-purple: #a371f7;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header h1 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header h1 span {
            color: var(--accent-green);
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .btn:hover {
            background: var(--border-color);
        }

        .btn.active {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
        }

        .btn-danger {
            border-color: var(--accent-red);
            color: var(--accent-red);
        }

        .btn-danger:hover {
            background: var(--accent-red);
            color: white;
        }

        .tabs {
            display: flex;
            gap: 4px;
            padding: 12px 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .tab {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s;
        }

        .tab:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .tab.active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .tab-count {
            background: var(--border-color);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .tab.active .tab-count {
            background: var(--accent-blue);
            color: white;
        }

        .content {
            padding: 16px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .log-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .log-entry {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .log-header {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .log-header:hover {
            background: var(--bg-tertiary);
        }

        .log-time {
            color: var(--text-muted);
            font-size: 12px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            min-width: 70px;
        }

        .log-method {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            min-width: 60px;
            text-align: center;
        }

        .method-GET { background: rgba(63, 185, 80, 0.2); color: var(--accent-green); }
        .method-POST { background: rgba(88, 166, 255, 0.2); color: var(--accent-blue); }
        .method-PUT { background: rgba(210, 153, 34, 0.2); color: var(--accent-yellow); }
        .method-PATCH { background: rgba(210, 153, 34, 0.2); color: var(--accent-yellow); }
        .method-DELETE { background: rgba(248, 81, 73, 0.2); color: var(--accent-red); }

        .log-path {
            flex: 1;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 13px;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .log-status {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
        }

        .status-2xx { background: rgba(63, 185, 80, 0.2); color: var(--accent-green); }
        .status-3xx { background: rgba(88, 166, 255, 0.2); color: var(--accent-blue); }
        .status-4xx { background: rgba(210, 153, 34, 0.2); color: var(--accent-yellow); }
        .status-5xx { background: rgba(248, 81, 73, 0.2); color: var(--accent-red); }

        .log-duration {
            color: var(--text-muted);
            font-size: 12px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            min-width: 70px;
            text-align: right;
        }

        .log-ip {
            color: var(--text-muted);
            font-size: 12px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
        }

        .log-details {
            display: none;
            border-top: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .log-entry.expanded .log-details {
            display: block;
        }

        .detail-section {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-section:last-child {
            border-bottom: none;
        }

        .detail-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .copy-btn {
            padding: 2px 8px;
            font-size: 10px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .copy-btn:hover {
            background: var(--border-color);
            color: var(--text-primary);
        }

        pre {
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .json-key { color: var(--accent-purple); }
        .json-string { color: var(--accent-green); }
        .json-number { color: var(--accent-blue); }
        .json-boolean { color: var(--accent-yellow); }
        .json-null { color: var(--accent-red); }

        .error-entry .log-header {
            border-left: 3px solid var(--accent-red);
        }

        .has-error .log-header {
            border-left: 3px solid var(--accent-yellow);
        }

        .btn-view-error {
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid var(--accent-red);
            background: rgba(248, 81, 73, 0.15);
            color: var(--accent-red);
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            margin-left: auto;
        }

        .btn-view-error:hover {
            background: var(--accent-red);
            color: white;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: scale(0.9);
            transition: transform 0.2s;
        }

        .modal-overlay.active .modal {
            transform: scale(1);
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-red);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .error-detail {
            margin-bottom: 20px;
        }

        .error-detail-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .error-detail-value {
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 13px;
            color: var(--text-primary);
            background: var(--bg-primary);
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .error-detail-value.exception {
            color: var(--accent-red);
        }

        .error-detail-value.message {
            white-space: pre-wrap;
            word-break: break-word;
        }

        .error-message {
            color: var(--accent-red);
            font-size: 13px;
            flex: 1;
        }

        .error-file {
            color: var(--text-muted);
            font-size: 12px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
        }

        .trace-item {
            padding: 8px 12px;
            background: var(--bg-secondary);
            border-radius: 4px;
            margin-bottom: 4px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 12px;
        }

        .trace-file {
            color: var(--accent-blue);
        }

        .trace-line {
            color: var(--accent-yellow);
        }

        .trace-function {
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .connection-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-red);
        }

        .status-dot.connected {
            background: var(--accent-green);
        }

        .external-api-entry .log-path {
            color: var(--accent-purple);
        }

        /* Job & Schedule Styles */
        .job-entry .log-header,
        .schedule-entry .log-header {
            border-left: 3px solid var(--accent-purple);
        }

        .job-entry.has-error .log-header,
        .schedule-entry.has-error .log-header {
            border-left-color: var(--accent-red);
        }

        .job-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            min-width: 70px;
            text-align: center;
        }

        .status-completed { background: rgba(63, 185, 80, 0.2); color: var(--accent-green); }
        .status-failed { background: rgba(248, 81, 73, 0.2); color: var(--accent-red); }
        .status-exception { background: rgba(210, 153, 34, 0.2); color: var(--accent-yellow); }
        .status-skipped { background: rgba(139, 148, 158, 0.2); color: var(--text-secondary); }
        .status-pending { background: rgba(88, 166, 255, 0.2); color: var(--accent-blue); }

        .job-name,
        .schedule-command {
            flex: 1;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 13px;
            color: var(--accent-purple);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .job-queue,
        .schedule-expression {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
        }

        .job-attempt {
            color: var(--text-muted);
            font-size: 11px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
        }

        .error-text {
            color: var(--accent-red);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .log-entry.new {
            animation: fadeIn 0.3s ease-out;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .filter-input {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 12px;
            min-width: 100px;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .filter-input::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        select.filter-input {
            cursor: pointer;
            min-width: 120px;
        }

        select.filter-input option {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        #filterEndpoint {
            min-width: 140px;
        }

        #filterIp {
            min-width: 120px;
        }

        .btn-filter {
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-tertiary);
            color: var(--text-primary);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-filter:hover {
            background: var(--border-color);
        }

        .btn-filter.active {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
        }

    </style>
</head>
<body>
    <header class="header">
        <h1>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Penta<span>Logger</span>
        </h1>
        <div class="header-actions">
            <div class="connection-status">
                <div class="status-dot" id="statusDot"></div>
                <span id="statusText">Disconnected</span>
            </div>
            <button class="btn" id="pauseBtn" onclick="togglePause()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="6" y="4" width="4" height="16"/>
                    <rect x="14" y="4" width="4" height="16"/>
                </svg>
                Pause
            </button>
            <button class="btn btn-danger" onclick="clearLogs()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Clear
            </button>
            <form id="logoutForm" method="POST" style="margin: 0;">
                <input type="hidden" name="_token" id="csrfToken">
                <button type="submit" class="btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </header>

    <nav class="tabs">
        <button class="tab active" data-tab="request" onclick="switchTab('request')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 12h-8"/>
                <path d="M21 6H3"/>
                <path d="M21 18H3"/>
                <path d="M3 12h8"/>
            </svg>
            Requests
            <span class="tab-count" id="requestCount">0</span>
        </button>
        <button class="tab" data-tab="error" onclick="switchTab('error')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            Errors
            <span class="tab-count" id="errorCount">0</span>
        </button>
        <button class="tab" data-tab="external_api" onclick="switchTab('external_api')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/>
                <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            External APIs
            <span class="tab-count" id="external_apiCount">0</span>
        </button>
        <button class="tab" data-tab="job" onclick="switchTab('job')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
            Jobs
            <span class="tab-count" id="jobCount">0</span>
        </button>
        <button class="tab" data-tab="schedule" onclick="switchTab('schedule')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Schedules
            <span class="tab-count" id="scheduleCount">0</span>
        </button>
    </nav>

    <main class="content">
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Method:</span>
                <select class="filter-input" id="filterMethod" onchange="filterLogs()">
                    <option value="">All</option>
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                    <option value="DELETE">DELETE</option>
                    <option value="OPTIONS">OPTIONS</option>
                    <option value="HEAD">HEAD</option>
                </select>
            </div>

            <div class="filter-group">
                <span class="filter-label">Status:</span>
                <select class="filter-input" id="filterStatus" onchange="filterLogs()">
                    <option value="">All</option>
                    <option value="2xx">2xx Success</option>
                    <option value="3xx">3xx Redirect</option>
                    <option value="4xx">4xx Client Error</option>
                    <option value="5xx">5xx Server Error</option>
                </select>
            </div>

            <div class="filter-group">
                <span class="filter-label">Endpoint:</span>
                <input type="text" class="filter-input" id="filterEndpoint" placeholder="/api/..." oninput="filterLogs()">
            </div>

            <div class="filter-group">
                <span class="filter-label">IP:</span>
                <input type="text" class="filter-input" id="filterIp" placeholder="192.168..." oninput="filterLogs()">
            </div>

            <div class="filter-group">
                <span class="filter-label">From:</span>
                <input type="datetime-local" class="filter-input" id="dateFrom" onchange="filterLogs()">
            </div>

            <div class="filter-group">
                <span class="filter-label">To:</span>
                <input type="datetime-local" class="filter-input" id="dateTo" onchange="filterLogs()">
            </div>

            <button class="btn-filter" onclick="clearFilters()">Clear</button>
        </div>
        <div class="log-list" id="logList">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M8 12h8"/>
                </svg>
                <p>No logs yet. Make some requests!</p>
            </div>
        </div>
    </main>

    <!-- Error Modal -->
    <div class="modal-overlay" id="errorModal" onclick="closeModal(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    Error Details
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="errorModalBody">
                <!-- Error content will be injected here -->
            </div>
        </div>
    </div>

    <script>
        const logs = { request: [], error: [], external_api: [], job: [], schedule: [] };
        let currentTab = 'request';
        let isPaused = false;
        let eventSource = null;
        let lastTimestamp = null;

        function connect() {
            if (eventSource) {
                eventSource.close();
            }

            const url = new URL('./_penta-logger/stream', window.location.origin);
            if (lastTimestamp) {
                url.searchParams.set('since', lastTimestamp);
            }

            eventSource = new EventSource(url);

            eventSource.onopen = () => {
                document.getElementById('statusDot').classList.add('connected');
                document.getElementById('statusText').textContent = 'Connected';
            };

            eventSource.onerror = () => {
                document.getElementById('statusDot').classList.remove('connected');
                document.getElementById('statusText').textContent = 'Reconnecting...';
                setTimeout(connect, 3000);
            };

            eventSource.addEventListener('log', (e) => {
                if (isPaused) return;
                const data = JSON.parse(e.data);
                addLog(data);
            });

            eventSource.addEventListener('ping', () => {});
        }

        function addLog(log) {
            const type = log.type;
            if (!logs[type]) logs[type] = [];

            logs[type].unshift(log);
            lastTimestamp = log.timestamp;

            if (logs[type].length > 200) {
                logs[type] = logs[type].slice(0, 200);
            }

            updateCounts();
            if (type === currentTab) {
                renderLogs();
            }
        }

        function updateCounts() {
            document.getElementById('requestCount').textContent = logs.request.length;
            document.getElementById('errorCount').textContent = logs.error.length;
            document.getElementById('external_apiCount').textContent = logs.external_api.length;
            document.getElementById('jobCount').textContent = logs.job.length;
            document.getElementById('scheduleCount').textContent = logs.schedule.length;
        }

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
            renderLogs();
        }

        function renderLogs() {
            const list = document.getElementById('logList');

            // Get filter values
            const filterMethod = document.getElementById('filterMethod')?.value || '';
            const filterStatus = document.getElementById('filterStatus')?.value || '';
            const filterEndpoint = document.getElementById('filterEndpoint')?.value.toLowerCase() || '';
            const filterIp = document.getElementById('filterIp')?.value.toLowerCase() || '';
            const dateFrom = document.getElementById('dateFrom')?.value || '';
            const dateTo = document.getElementById('dateTo')?.value || '';

            const filtered = logs[currentTab].filter(log => {
                const data = log.data;

                // Method filter
                if (filterMethod) {
                    const method = (data.method || '').toUpperCase();
                    if (method !== filterMethod) return false;
                }

                // Status filter
                if (filterStatus) {
                    const status = data.status || 0;
                    if (filterStatus === '2xx' && (status < 200 || status >= 300)) return false;
                    if (filterStatus === '3xx' && (status < 300 || status >= 400)) return false;
                    if (filterStatus === '4xx' && (status < 400 || status >= 500)) return false;
                    if (filterStatus === '5xx' && (status < 500 || status >= 600)) return false;
                }

                // Endpoint filter
                if (filterEndpoint) {
                    const path = (data.path || data.endpoint || data.url || '').toLowerCase();
                    if (!path.includes(filterEndpoint)) return false;
                }

                // IP filter
                if (filterIp) {
                    const ip = (data.ip || '').toLowerCase();
                    if (!ip.includes(filterIp)) return false;
                }

                // Date/time filter
                if (dateFrom || dateTo) {
                    const logTime = new Date(log.timestamp).getTime();

                    if (dateFrom) {
                        const fromTime = new Date(dateFrom).getTime();
                        if (logTime < fromTime) return false;
                    }

                    if (dateTo) {
                        const toTime = new Date(dateTo).getTime() + (60 * 1000) - 1;
                        if (logTime > toTime) return false;
                    }
                }

                return true;
            });

            if (filtered.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M8 12h8"/>
                        </svg>
                        <p>No ${currentTab.replace('_', ' ')} logs yet</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = filtered.map(log => renderLogEntry(log)).join('');
        }

        function renderLogEntry(log) {
            if (log.type === 'error') {
                return renderErrorEntry(log);
            }
            if (log.type === 'job') {
                return renderJobEntry(log);
            }
            if (log.type === 'schedule') {
                return renderScheduleEntry(log);
            }
            return renderRequestEntry(log);
        }

        function renderRequestEntry(log) {
            const d = log.data;
            const time = new Date(log.timestamp).toLocaleTimeString();
            const statusClass = getStatusClass(d.status);
            const methodClass = `method-${d.method}`;
            const isExternal = log.type === 'external_api';
            const hasLinkedError = d.has_error && d.request_id && findLinkedError(d.request_id);

            return `
                <div class="log-entry ${isExternal ? 'external-api-entry' : ''} ${hasLinkedError ? 'has-error' : ''}" data-id="${log.id}">
                    <div class="log-header" onclick="toggleEntry('${log.id}')">
                        <span class="log-time">${time}</span>
                        <span class="log-method ${methodClass}">${d.method}</span>
                        <span class="log-path">${isExternal ? d.url : d.path}</span>
                        <span class="log-status ${statusClass}">${d.status}</span>
                        <span class="log-duration">${d.duration_ms}ms</span>
                        ${d.ip ? `<span class="log-ip">${d.ip}</span>` : ''}
                        ${hasLinkedError ? `<button class="btn-view-error" onclick="showLinkedError(event, '${d.request_id}')">View Error</button>` : ''}
                    </div>
                    <div class="log-details">
                        <div class="detail-section">
                            <div class="detail-title">
                                Request
                                <button class="copy-btn" onclick="copyJson(event, ${escapeHtml(JSON.stringify(d.request))})">Copy</button>
                            </div>
                            <pre>${formatJson(d.request)}</pre>
                        </div>
                        <div class="detail-section">
                            <div class="detail-title">
                                Response
                                <button class="copy-btn" onclick="copyJson(event, ${escapeHtml(JSON.stringify(d.response))})">Copy</button>
                            </div>
                            <pre>${formatJson(d.response)}</pre>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderErrorEntry(log) {
            const d = log.data;
            const time = new Date(log.timestamp).toLocaleTimeString();

            return `
                <div class="log-entry error-entry" data-id="${log.id}">
                    <div class="log-header" onclick="toggleEntry('${log.id}')">
                        <span class="log-time">${time}</span>
                        <span class="error-message">${escapeHtmlText(d.message)}</span>
                        <span class="error-file">${d.file}:${d.line}</span>
                    </div>
                    <div class="log-details">
                        <div class="detail-section">
                            <div class="detail-title">Exception</div>
                            <pre>${escapeHtmlText(d.exception)}</pre>
                        </div>
                        ${d.request ? `
                        <div class="detail-section">
                            <div class="detail-title">Request Context</div>
                            <pre>${formatJson(d.request)}</pre>
                        </div>
                        ` : ''}
                        ${d.trace && d.trace.length > 0 ? `
                        <div class="detail-section">
                            <div class="detail-title">Stack Trace</div>
                            ${d.trace.map(t => `
                                <div class="trace-item">
                                    <span class="trace-file">${t.file || 'unknown'}</span>:<span class="trace-line">${t.line || '?'}</span>
                                    ${t.class ? `<span class="trace-function"> ${t.class}${t.type || ''}${t.function}()</span>` : ''}
                                </div>
                            `).join('')}
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        function renderJobEntry(log) {
            const d = log.data;
            const time = new Date(log.timestamp).toLocaleTimeString();
            const statusClass = getJobStatusClass(d.status);
            const hasException = d.exception;

            return `
                <div class="log-entry job-entry ${hasException ? 'has-error' : ''}" data-id="${log.id}">
                    <div class="log-header" onclick="toggleEntry('${log.id}')">
                        <span class="log-time">${time}</span>
                        <span class="job-status ${statusClass}">${d.status}</span>
                        <span class="job-name">${escapeHtmlText(d.name)}</span>
                        <span class="job-queue">${d.queue}</span>
                        <span class="log-duration">${d.duration_ms}ms</span>
                        <span class="job-attempt">attempt ${d.attempt}${d.max_tries ? '/' + d.max_tries : ''}</span>
                    </div>
                    <div class="log-details">
                        <div class="detail-section">
                            <div class="detail-title">Job Info</div>
                            <pre>${formatJson({
                                job_id: d.job_id,
                                queue: d.queue,
                                connection: d.connection,
                                attempt: d.attempt,
                                max_tries: d.max_tries,
                                timeout: d.timeout
                            })}</pre>
                        </div>
                        ${Object.keys(d.data || {}).length > 0 ? `
                        <div class="detail-section">
                            <div class="detail-title">Job Data</div>
                            <pre>${formatJson(d.data)}</pre>
                        </div>
                        ` : ''}
                        ${hasException ? `
                        <div class="detail-section">
                            <div class="detail-title">Exception</div>
                            <pre class="error-text">${escapeHtmlText(d.exception.class)}: ${escapeHtmlText(d.exception.message)}
at ${escapeHtmlText(d.exception.file)}:${d.exception.line}</pre>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        function renderScheduleEntry(log) {
            const d = log.data;
            const time = new Date(log.timestamp).toLocaleTimeString();
            const statusClass = getJobStatusClass(d.status);
            const hasException = d.exception;

            return `
                <div class="log-entry schedule-entry ${hasException ? 'has-error' : ''}" data-id="${log.id}">
                    <div class="log-header" onclick="toggleEntry('${log.id}')">
                        <span class="log-time">${time}</span>
                        <span class="job-status ${statusClass}">${d.status}</span>
                        <span class="schedule-command">${escapeHtmlText(d.command)}</span>
                        <span class="schedule-expression">${d.expression}</span>
                        <span class="log-duration">${d.duration_ms}ms</span>
                    </div>
                    <div class="log-details">
                        <div class="detail-section">
                            <div class="detail-title">Schedule Info</div>
                            <pre>${formatJson({
                                command: d.command,
                                description: d.description,
                                expression: d.expression,
                                timezone: d.timezone,
                                without_overlapping: d.without_overlapping,
                                run_in_background: d.run_in_background,
                                even_in_maintenance_mode: d.even_in_maintenance_mode
                            })}</pre>
                        </div>
                        ${d.output ? `
                        <div class="detail-section">
                            <div class="detail-title">Output</div>
                            <pre>${escapeHtmlText(d.output)}</pre>
                        </div>
                        ` : ''}
                        ${hasException ? `
                        <div class="detail-section">
                            <div class="detail-title">Exception</div>
                            <pre class="error-text">${escapeHtmlText(d.exception.class)}: ${escapeHtmlText(d.exception.message)}
at ${escapeHtmlText(d.exception.file)}:${d.exception.line}</pre>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        function getJobStatusClass(status) {
            switch(status) {
                case 'completed': return 'status-completed';
                case 'failed': return 'status-failed';
                case 'exception': return 'status-exception';
                case 'skipped': return 'status-skipped';
                default: return 'status-pending';
            }
        }

        function toggleEntry(id) {
            const entry = document.querySelector(`[data-id="${id}"]`);
            if (entry) {
                entry.classList.toggle('expanded');
            }
        }

        function getStatusClass(status) {
            if (status >= 500) return 'status-5xx';
            if (status >= 400) return 'status-4xx';
            if (status >= 300) return 'status-3xx';
            return 'status-2xx';
        }

        function formatJson(obj) {
            if (!obj) return '<span class="json-null">null</span>';
            const json = JSON.stringify(obj, null, 2);
            return syntaxHighlight(json);
        }

        function syntaxHighlight(json) {
            json = escapeHtmlText(json);
            return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, (match) => {
                let cls = 'json-number';
                if (/^"/.test(match)) {
                    if (/:$/.test(match)) {
                        cls = 'json-key';
                    } else {
                        cls = 'json-string';
                    }
                } else if (/true|false/.test(match)) {
                    cls = 'json-boolean';
                } else if (/null/.test(match)) {
                    cls = 'json-null';
                }
                return `<span class="${cls}">${match}</span>`;
            });
        }

        function escapeHtmlText(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeHtml(str) {
            return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function copyJson(event, json) {
            event.stopPropagation();
            navigator.clipboard.writeText(JSON.stringify(JSON.parse(json), null, 2));
            const btn = event.target;
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy', 1500);
        }

        function togglePause() {
            isPaused = !isPaused;
            const btn = document.getElementById('pauseBtn');
            if (isPaused) {
                btn.classList.add('active');
                btn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    Resume
                `;
            } else {
                btn.classList.remove('active');
                btn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="6" y="4" width="4" height="16"/>
                        <rect x="14" y="4" width="4" height="16"/>
                    </svg>
                    Pause
                `;
            }
        }

        function clearLogs() {
            fetch('./_penta-logger/clear', { method: 'POST' })
                .then(() => {
                    logs.request = [];
                    logs.error = [];
                    logs.external_api = [];
                    logs.job = [];
                    logs.schedule = [];
                    updateCounts();
                    renderLogs();
                });
        }

        function filterLogs() {
            renderLogs();
        }

        function clearFilters() {
            document.getElementById('filterMethod').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterEndpoint').value = '';
            document.getElementById('filterIp').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            renderLogs();
        }

        function findLinkedError(requestId) {
            if (!requestId) return null;
            return logs.error.find(e => e.data.request_id === requestId);
        }

        function showLinkedError(event, requestId) {
            event.stopPropagation();
            const errorLog = findLinkedError(requestId);
            if (!errorLog) {
                alert('Error log not found');
                return;
            }

            const d = errorLog.data;
            const time = new Date(errorLog.timestamp).toLocaleString();

            const modalBody = document.getElementById('errorModalBody');
            modalBody.innerHTML = `
                <div class="error-detail">
                    <div class="error-detail-label">Exception</div>
                    <div class="error-detail-value exception">${escapeHtmlText(d.exception)}</div>
                </div>
                <div class="error-detail">
                    <div class="error-detail-label">Message</div>
                    <div class="error-detail-value message">${escapeHtmlText(d.message)}</div>
                </div>
                <div class="error-detail">
                    <div class="error-detail-label">File</div>
                    <div class="error-detail-value">${escapeHtmlText(d.file)}:${d.line}</div>
                </div>
                <div class="error-detail">
                    <div class="error-detail-label">Time</div>
                    <div class="error-detail-value">${time}</div>
                </div>
                ${d.trace && d.trace.length > 0 ? `
                <div class="error-detail">
                    <div class="error-detail-label">Stack Trace</div>
                    <div class="error-detail-value" style="max-height: 300px; overflow-y: auto;">
                        ${d.trace.map(t => `<div style="padding: 6px 0; border-bottom: 1px solid var(--border-color);">
                            <span style="color: var(--accent-blue);">${t.file || 'unknown'}</span>:<span style="color: var(--accent-yellow);">${t.line || '?'}</span>
                            ${t.class ? `<br><span style="color: var(--text-secondary);">${t.class}${t.type || ''}${t.function}()</span>` : ''}
                        </div>`).join('')}
                    </div>
                </div>
                ` : ''}
            `;

            document.getElementById('errorModal').classList.add('active');
        }

        function closeModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('errorModal').classList.remove('active');
        }

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });

        function loadInitialLogs() {
            fetch('./_penta-logger/logs')
                .then(r => r.json())
                .then(data => {
                    data.forEach(log => {
                        const type = log.type;
                        if (!logs[type]) logs[type] = [];
                        logs[type].push(log);
                        if (log.timestamp > (lastTimestamp || '')) {
                            lastTimestamp = log.timestamp;
                        }
                    });
                    updateCounts();
                    renderLogs();
                    connect();
                })
                .catch(() => connect());
        }

        // Setup logout form
        document.getElementById('logoutForm').action = './_penta-logger/logout';

        loadInitialLogs();
    </script>
</body>
</html>
HTML;
    }
}
