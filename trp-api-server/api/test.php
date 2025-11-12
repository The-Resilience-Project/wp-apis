<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";
require dirname(__FILE__)."/../init.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

if ($method === 'POST') {
	error_log(print_r($data, 1));

	// Log to Sentry if available
	$logger_errors = [];
	if (function_exists('\Sentry\captureMessage')) {
		\Sentry\withScope(function (\Sentry\State\Scope $scope) use ($data, $method) {
			$scope->setTag('endpoint', 'test_api');
			$scope->setTag('method', $method);
			$scope->setContext('request_data', [
				'data' => $data,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			]);
			\Sentry\captureMessage('Test API endpoint called with captureMessage() v2', \Sentry\Severity::info());
		});
	}

	$success = true;

	// Detect Sentry version and capabilities
	$sentry_info = [
		'enabled' => function_exists('\Sentry\captureMessage'),
		'logger_function_exists' => function_exists('\Sentry\logger'),
		'sdk_class_exists' => class_exists('\Sentry\SDK'),
		'client_class_exists' => class_exists('\Sentry\ClientInterface'),
		'version' => 'unknown',
		'php_version' => PHP_VERSION,
		'dsn' => defined('WP_SENTRY_PHP_DSN') ? WP_SENTRY_PHP_DSN : 'not defined'
	];

	// Try multiple ways to get version
	if (class_exists('\Sentry\SDK')) {
		if (defined('\Sentry\SDK::VERSION')) {
			$sentry_info['version'] = \Sentry\SDK::VERSION;
		}
	}

	// Check composer packages
	if (file_exists(__DIR__ . '/../../vendor/composer/installed.json')) {
		$installed = json_decode(file_get_contents(__DIR__ . '/../../vendor/composer/installed.json'), true);
		foreach ($installed['packages'] ?? [] as $package) {
			if ($package['name'] === 'sentry/sentry') {
				$sentry_info['version'] = $package['version'] ?? 'unknown';
				break;
			}
		}
	}

	// Add logger errors if any
	if (!empty($logger_errors)) {
		$sentry_info['logger_errors'] = $logger_errors;
	}

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'failed',
		'message' => 'Test endpoint received data',
		'sentry' => $sentry_info,
	]);
	exit;

}