<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";
require dirname(__FILE__)."/../init.php";

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
    if($data["service_type"] === "School"){
        $data_controller = new SchoolVTController($data);
    } elseif($data["service_type"] === "Early Years"){
        $data_controller = new EarlyYearsVTController($data);
    }
    
    $success = $data_controller->confirm_program();
	

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'fail',
	]);
	exit;

}
