<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

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
