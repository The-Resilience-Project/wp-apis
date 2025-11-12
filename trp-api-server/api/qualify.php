<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers_dev.php";
require dirname(__FILE__)."/../init.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

// Log API call to Sentry
if (function_exists('\Sentry\captureMessage')) {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($method) {
        $scope->setTag('endpoint', 'qualify');
        $scope->setTag('method', $method);
        $scope->setContext('request', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('API: qualify endpoint called', \Sentry\Severity::info());
    });
}

if ($method === 'POST') {
    // error_log($data["interested_programs"]);
    // error_log(gettype($data["interested_programs"]));
    $data_controller = new WorkplaceVTController($data);
    
    $success = $data_controller->submit_qualifier();
	

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'fail',
	]);
	exit;

}
