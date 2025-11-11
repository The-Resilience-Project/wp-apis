<?php
/**
 * Logger Test Endpoint
 *
 * Call this via Postman or browser to test the logging system
 *
 * GET  http://localhost:8880/api/test-logger.php
 * POST http://localhost:8880/api/test-logger.php
 */

require_once "init.php";

header('Content-Type: application/json');

// Log this test call
if (function_exists('log_call')) {
    log_call(__FILE__, ['test' => 'Logger test endpoint accessed']);
}

// Simulate a webhook call
if (function_exists('log_webhook')) {
    log_webhook('test-logger.php', [
        'test_type' => 'manual_test',
        'timestamp' => time(),
        'method' => $_SERVER['REQUEST_METHOD'],
    ]);
}

// Get today's log file
$logFile = dirname(__FILE__) . '/logs/' . date('Y-m-d') . '_calls.log';

$response = [
    'status' => 'success',
    'message' => 'Logger is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'logger_loaded' => function_exists('log_call') && function_exists('log_webhook'),
    'log_file' => $logFile,
    'log_file_exists' => file_exists($logFile),
    'request_details' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    ],
];

// If log file exists, show last 50 lines
if (file_exists($logFile)) {
    $logContents = file($logFile);
    $response['log_size'] = filesize($logFile) . ' bytes';
    $response['log_lines'] = count($logContents);
    $response['last_50_lines'] = array_slice($logContents, -50);
}

// Check if logs directory is writable
$logsDir = dirname(__FILE__) . '/logs/';
$response['logs_dir_writable'] = is_writable($logsDir);
$response['logs_dir_permissions'] = substr(sprintf('%o', fileperms($logsDir)), -4);

echo json_encode($response, JSON_PRETTY_PRINT);
