<?php
/**
 * Sentry Test Endpoint with Diagnostics
 *
 * Tests Sentry logging in the API directory
 *
 * Usage: GET http://localhost:8880/api/test-sentry.php
 */

// Force no caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json');

// Clear PHP OpCache for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, we'll capture in JSON

$diagnostics = [
    'test_file_version' => '2.0',
    'current_file' => __FILE__,
    'current_dir' => __DIR__,
    'init_file_path' => __DIR__ . '/init.php',
    'init_file_exists' => file_exists(__DIR__ . '/init.php'),
    'vendor_paths_checked' => [],
];

// Check vendor paths
$vendorPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../wp-content/vendor/autoload.php',
];

foreach ($vendorPaths as $path) {
    $diagnostics['vendor_paths_checked'][$path] = file_exists($path);
}

// Try to load init.php
if (file_exists(__DIR__ . '/init.php')) {
    try {
        require_once __DIR__ . '/init.php';
        $diagnostics['init_loaded'] = true;
    } catch (Exception $e) {
        $diagnostics['init_loaded'] = false;
        $diagnostics['init_error'] = $e->getMessage();
    }
} else {
    $diagnostics['init_loaded'] = false;
    $diagnostics['init_error'] = 'init.php not found';
}

// Check what's available after init
$diagnostics['after_init'] = [
    'sentry_class_exists' => class_exists('\Sentry\init'),
    'dsn_defined' => defined('WP_SENTRY_PHP_DSN'),
    'dsn_value' => defined('WP_SENTRY_PHP_DSN') ? substr(WP_SENTRY_PHP_DSN, 0, 20) . '...' : 'not defined',
];

$sentryInitialized = class_exists('\Sentry\init') && defined('WP_SENTRY_PHP_DSN');

$testResults = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'diagnostics' => $diagnostics,
    'sentry' => [
        'dsn_configured' => defined('WP_SENTRY_PHP_DSN'),
        'class_available' => class_exists('\Sentry\init'),
        'initialized' => $sentryInitialized,
    ],
    'tests' => [],
];

// Run tests if Sentry is initialized
if ($sentryInitialized) {
    // Test 1: Simple message
    try {
        \Sentry\captureMessage('Test: API Sentry logging is working', \Sentry\Severity::info());
        $testResults['tests'][] = [
            'name' => 'Simple message',
            'status' => 'sent',
            'message' => 'Info message sent to Sentry',
        ];
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => 'Simple message',
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }

    // Test 2: Message with context
    try {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) {
            $scope->setContext('api_request', [
                'method' => $_SERVER['REQUEST_METHOD'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            ]);
            $scope->setTag('test_type', 'context_test');
            \Sentry\captureMessage('Test: Message with context data', \Sentry\Severity::info());
        });
        $testResults['tests'][] = [
            'name' => 'Message with context',
            'status' => 'sent',
            'message' => 'Info message with context sent to Sentry',
        ];
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => 'Message with context',
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }

    // Test 3: Warning level
    try {
        \Sentry\captureMessage('Test: Warning level message', \Sentry\Severity::warning());
        $testResults['tests'][] = [
            'name' => 'Warning message',
            'status' => 'sent',
            'message' => 'Warning message sent to Sentry',
        ];
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => 'Warning message',
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }

    // Test 4: Exception
    try {
        $testException = new Exception('Test: Sample exception from API');
        \Sentry\captureException($testException);
        $testResults['tests'][] = [
            'name' => 'Exception capture',
            'status' => 'sent',
            'message' => 'Exception sent to Sentry',
        ];
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => 'Exception capture',
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }

    // Test 5: Error with additional context
    try {
        \Sentry\withScope(function (\Sentry\State\Scope $scope) {
            $scope->setTag('component', 'api');
            $scope->setTag('test', 'true');
            $scope->setUser(['id' => 'test-user', 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            $scope->setContext('test_data', [
                'timestamp' => time(),
                'php_version' => phpversion(),
            ]);
            \Sentry\captureMessage('Test: Full context example', \Sentry\Severity::info());
        });
        $testResults['tests'][] = [
            'name' => 'Full context example',
            'status' => 'sent',
            'message' => 'Message with tags, user, and context sent to Sentry',
        ];
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => 'Full context example',
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];
    }

    $testResults['message'] = 'All tests completed. Check your Sentry dashboard at https://sentry.io';
} else {
    $testResults['status'] = 'error';
    $testResults['message'] = 'Sentry not initialized. Check diagnostics for details.';

    if (!defined('WP_SENTRY_PHP_DSN')) {
        $testResults['error'] = 'WP_SENTRY_PHP_DSN not defined in wp-config.php';
    } elseif (!class_exists('\Sentry\init')) {
        $testResults['error'] = 'Sentry SDK not loaded. Check vendor/autoload.php exists at public_html/vendor/';
    }
}

echo json_encode($testResults, JSON_PRETTY_PRINT);
