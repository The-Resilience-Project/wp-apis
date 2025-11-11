<?php
/**
 * Simple File-Based Call Logger
 *
 * Tracks which files are calling which files with full context
 * No external dependencies - just writes to log files
 */

class CallLogger {
    private $logDir;
    private $logFile;

    public function __construct() {
        $this->logDir = dirname(__FILE__) . '/logs/';
        if (!file_exists($this->logDir)) {
            @mkdir($this->logDir, 0777, true);
        }
        $this->logFile = $this->logDir . date('Y-m-d') . '_calls.log';
    }

    /**
     * Log a file execution with full call stack
     *
     * @param string $currentFile The file being executed
     * @param array $context Additional context data
     */
    public function logCall($currentFile, $context = []) {
        $callStack = $this->getCallStack();

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'current_file' => basename($currentFile),
            'full_path' => $currentFile,
            'called_from' => $callStack[0] ?? 'direct',
            'call_stack' => $callStack,
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            ],
            'context' => $context,
        ];

        $this->writeLog($logData);
    }

    /**
     * Log a webhook call
     *
     * @param string $webhook Webhook name
     * @param array $data Webhook data
     */
    public function logWebhook($webhook, $data = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'WEBHOOK',
            'webhook' => $webhook,
            'data' => $data,
            'request' => [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ],
        ];

        $this->writeLog($logData);
    }

    /**
     * Get the call stack (who called this file)
     *
     * @return array Array of callers with file, line, and function
     */
    private function getCallStack() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $stack = [];

        foreach ($trace as $item) {
            // Skip CallLogger class methods
            if (isset($item['class']) && $item['class'] === 'CallLogger') {
                continue;
            }

            // Skip the helper functions
            if (isset($item['function']) && in_array($item['function'], ['log_call', 'log_webhook'])) {
                continue;
            }

            $caller = [
                'file' => isset($item['file']) ? basename($item['file']) : 'unknown',
                'full_path' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
            ];

            if (isset($item['function'])) {
                $caller['function'] = $item['function'];
            }
            if (isset($item['class'])) {
                $caller['class'] = $item['class'];
            }

            $stack[] = $caller;
        }

        return $stack;
    }

    /**
     * Write log data to file
     *
     * @param array $logData The data to log
     */
    private function writeLog($logData) {
        // Format for readability
        $logLine = "\n" . str_repeat('=', 80) . "\n";
        $logLine .= "[{$logData['timestamp']}]\n";

        if (isset($logData['type']) && $logData['type'] === 'WEBHOOK') {
            $logLine .= "TYPE: WEBHOOK CALL\n";
            $logLine .= "Webhook: {$logData['webhook']}\n";
            $logLine .= "Method: {$logData['request']['method']}\n";
            $logLine .= "IP: {$logData['request']['ip']}\n";
            $logLine .= "Data: " . json_encode($logData['data'], JSON_PRETTY_PRINT) . "\n";
        } else {
            $logLine .= "FILE EXECUTION: {$logData['current_file']}\n";
            $logLine .= "Full Path: {$logData['full_path']}\n";

            if ($logData['called_from'] !== 'direct') {
                $caller = $logData['called_from'];
                $logLine .= "Called From: {$caller['file']} (line {$caller['line']})\n";
            } else {
                $logLine .= "Called From: Direct access\n";
            }

            if (!empty($logData['call_stack'])) {
                $logLine .= "\nCall Stack:\n";
                foreach ($logData['call_stack'] as $index => $frame) {
                    $logLine .= "  #{$index} {$frame['file']}";
                    if (isset($frame['class'])) {
                        $logLine .= " -> {$frame['class']}";
                    }
                    if (isset($frame['function'])) {
                        $logLine .= "::{$frame['function']}()";
                    }
                    $logLine .= " (line {$frame['line']})\n";
                }
            }

            $logLine .= "\nRequest Info:\n";
            $logLine .= "  Method: {$logData['request']['method']}\n";
            $logLine .= "  URI: {$logData['request']['uri']}\n";
            $logLine .= "  IP: {$logData['request']['ip']}\n";

            if (!empty($logData['context'])) {
                $logLine .= "\nContext:\n";
                $logLine .= "  " . json_encode($logData['context'], JSON_PRETTY_PRINT) . "\n";
            }
        }

        $logLine .= str_repeat('=', 80) . "\n";

        @file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

// Global helper functions for easy use
function log_call($file, $context = []) {
    static $logger = null;
    if ($logger === null) {
        $logger = new CallLogger();
    }
    $logger->logCall($file, $context);
}

function log_webhook($webhook, $data = []) {
    static $logger = null;
    if ($logger === null) {
        $logger = new CallLogger();
    }
    $logger->logWebhook($webhook, $data);
}
