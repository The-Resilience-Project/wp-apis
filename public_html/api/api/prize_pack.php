<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

// POST request
// Store some data or something
if ($method === 'POST') {
    $data_controller;
    
    if($data["service_type"] === "School"){
        $data_controller = new SchoolVTController($data);
    }
    elseif($data["service_type"] === "Workplace"){
        $data_controller = new WorkplaceVTController($data);
    }
    elseif($data["service_type"] === "Early Years"){
        $data_controller = new EarlyYearsVTController($data);
    }
    elseif($data["service_type"] === "Imperfects"){
        $data_controller = new ImperfectsVTController($data);
    }
    else{
        $data_controller = new GeneralVTController($data);
    }
    
    $success = $data_controller->submit_prize_pack_entry();
	

	// Then, respond with a success
	send_response([
		'status' => $success ? 'success' : 'fail',
	]);
	exit;

}