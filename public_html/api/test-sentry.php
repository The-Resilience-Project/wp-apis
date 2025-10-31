<?php
/**
 * Sentry Test Endpoint
 *
 * Tests Sentry logging in the API directory
 *
 * Usage: GET http://localhost:8880/api/test-sentry.php
 */

header('Content-Type: application/json');

// Load WordPress config to get Sentry DSN
if (file_exists(__DIR__ . '/../wp-config.php')) {
    // Load only the config constants we need, not the full WordPress
    $wp_config_content = file_get_contents(__DIR__ . '/../wp-config.php');
    if (preg_match("/define\(\s*'WP_SENTRY_PHP_DSN'\s*,\s*'([^']+)'/", $wp_config_content, $matches)) {
        define('WP_SENTRY_PHP_DSN', $matches[1]);
    }
}

// Load Sentry
$sentryLoaded = false;
$sentryInitialized = false;

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $sentryLoaded = true;
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sentryLoaded = true;
}

// Initialize Sentry
if ($sentryLoaded && defined('WP_SENTRY_PHP_DSN') && class_exists('\Sentry\init')) {
    try {
        \Sentry\init([
            'dsn' => WP_SENTRY_PHP_DSN,
            'traces_sample_rate' => 1.0,
            'environment' => 'development',
        ]);
        $sentryInitialized = true;
    } catch (Exception $e) {
        error_log('Sentry initialization failed: ' . $e->getMessage());
    }
}

$testResults = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'sentry' => [
        'autoload_found' => $sentryLoaded,
        'dsn_configured' => defined('WP_SENTRY_PHP_DSN'),
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

    $testResults['message'] = 'All tests completed. Check your Sentry dashboard.';
} else {
    $testResults['status'] = 'error';
    $testResults['message'] = 'Sentry not initialized. Check configuration.';

    if (!$sentryLoaded) {
        $testResults['error'] = 'Composer autoload not found';
    } elseif (!defined('WP_SENTRY_PHP_DSN')) {
        $testResults['error'] = 'WP_SENTRY_PHP_DSN not defined in wp-config.php';
    } elseif (!class_exists('\Sentry\init')) {
        $testResults['error'] = 'Sentry class not found after autoload';
    }
}

echo json_encode($testResults, JSON_PRETTY_PRINT);
