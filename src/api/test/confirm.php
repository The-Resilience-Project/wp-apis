<?php
// Log immediately to error_log (before init.php is loaded, can't use log_debug yet)
error_log('[TEST CONFIRM] File execution started at ' . date('Y-m-d H:i:s'));

require dirname(__FILE__)."/../utils.php";
require dirname(__FILE__)."/../api_helpers.php";
require dirname(__FILE__)."/../../init.php";

// Now we can use log_debug which logs to both error_log AND Sentry
log_debug('Test confirm endpoint - file loaded successfully', [
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

// Log API call to Sentry
if (function_exists('\Sentry\captureMessage')) {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($method, $data) {
        $scope->setTag('endpoint', 'confirm');
        $scope->setTag('method', $method);
        $scope->setTag('service_type', $data['service_type'] ?? 'unknown');
        $scope->setContext('request', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('API: confirm endpoint called', \Sentry\Severity::info());
    });
}

if ($method === 'POST') {
    // Test endpoint - just log and return success
    log_info('Test confirm endpoint received POST request', [
        'service_type' => $data['service_type'] ?? 'unknown',
        'school_name' => $data['school_name_other'] ?? $data['school_account_no'] ?? 'unknown',
        'contact_email' => $data['contact_email'] ?? 'unknown'
    ]);

	// Return success immediately without processing
	send_response([
		'status' => 'success',
		'message' => 'Test endpoint received data successfully',
		'data_received' => array_keys($data)
	]);
	exit;

}
