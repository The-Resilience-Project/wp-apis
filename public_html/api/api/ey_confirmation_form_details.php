<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$method = get_method();
$data = get_request_data();

if ($method === 'GET') {
    $data_controller = new EarlyYearsVTController($data);
    $account_no = !empty($data["school_account_no"]) ? $data["school_account_no"] : null;
    $accountname = !empty($data["school_name"]) ? $data["school_name"] : null;
    
    error_log("EY ".$account_no. " ".$accountname);
    
    $form_data = $data_controller->get_info_for_confirmation_form($account_no, $accountname);    


	// Then, respond with a success
	send_response([
		'data' => $form_data
	]);
	exit;

}