<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

if ($method === 'POST') {
    error_log("************** Curric Ordering Data 2026 ********************");
    error_log(print_r($data, true));
    error_log("********************************************************");
    $data_controller = new SchoolVTController($data);
    
    $success = $data_controller->order_resources_26();

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'fail',
	]);
	exit;

}
