<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

if ($method === 'POST') {
    // $data_controller = new ExistingSchoolVTController($data);
    
    // $success = $data_controller->get_line_items();
    // error_log("***********");
    // error_log($data["teacher_wellbeing_program"]);
    // error_log($data["twb_1_online_only"]);
    // error_log($data["twb_1_workshop_paid"]);
    // error_log($data["twb_1_workshop_free"]);
    // error_log(print_r($data), true);
    // $success = true;
	
	error_log(print_r($data, 1));

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'fail',
	]);
	exit;

}