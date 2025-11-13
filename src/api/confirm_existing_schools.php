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
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($method) {
        $scope->setTag('endpoint', 'confirm_existing_schools');
        $scope->setTag('method', $method);
        $scope->setContext('request', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('API: confirm_existing_schools endpoint called', \Sentry\Severity::info());
    });
}

if ($method === 'POST') {
    $data_controller = new ExistingSchoolVTController($data);
    
    $success = $data_controller->confirm_program();
    // error_log("***********");
    // error_log($data["teacher_wellbeing_program"]);
    // error_log($data["twb_1_online_only"]);
    // error_log($data["twb_1_workshop_paid"]);
    // error_log($data["twb_1_workshop_free"]);
    // error_log(print_r($data), true);
    // $success = true;
	

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'fail',
	]);
	exit;

}
