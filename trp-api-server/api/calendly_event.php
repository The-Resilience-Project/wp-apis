<?php

require dirname(__FILE__)."/utils.php";
require dirname(__FILE__)."/api_helpers.php";
require dirname(__FILE__)."/../init.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

// Log API call to Sentry
if (function_exists('\Sentry\captureMessage')) {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) {
        $scope->setTag('endpoint', 'calendly_event');
        $scope->setTag('method', $_SERVER['REQUEST_METHOD'] ?? 'unknown');
        $scope->setContext('request', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('API: calendly_event webhook called', \Sentry\Severity::info());
    });
}

// log_data(print_r($_SERVER,1));

// $webhook_sig = null;
// if(array_key_exists("Calendly-Webhook-Signature", $_SERVER)){
//     $webhook_sig = $_SERVER["Calendly-Webhook-Signature"];
// } else if(array_key_exists("HTTP_CALENDLY_WEBHOOK_SIGNATURE", $_SERVER)){
//     $webhook_sig = $_SERVER["HTTP_CALENDLY_WEBHOOK_SIGNATURE"];
// } else if(array_key_exists("CALENDLY_WEBHOOK_SIGNATURE", $_SERVER)){
//     $webhook_sig = $_SERVER["CALENDLY_WEBHOOK_SIGNATURE"];
// } else{
//     log_data("Cannot find signing key");
//     send_response([
//     	'status' => 'false',
//     	'message' => 'unable to find signing key'
//     ]);
//     exit;
// }

$raw_data = get_request_data();

// $key_components = explode(",", $webhook_sig);
// $signed_key = explode("=", $key_components[1])[1];
// $signed_time = explode("=", $key_components[0])[1];

// $expected_signature = hash_hmac('sha256', $signed_time.'.'.print_r($raw_data,1), '0^hWV%VRDv25QRf8');

// log_data(print_r())

// if($signed_key !== $expected_signature){
//     log_data("Invalid signing key");
//     send_response([
//     	'status' => 'false',
//     	'message' => 'invalid signing key'
//     ]);
//     exit;
// }


$calendly_payload = $raw_data["payload"];
$vic_link = "https://api.calendly.com/event_types/033a4470-b2f0-4e57-85b4-a2af4383b4f1";
$laura_link = "https://api.calendly.com/event_types/c3c384f9-de9f-4155-9b9c-c86eb378facb";
$round_robin_link = "https://api.calendly.com/event_types/053e1993-414f-4619-a4a0-b3c218fbcedb";
if(!in_array($calendly_payload["scheduled_event"]["event_type"], array($vic_link, $laura_link, $round_robin_link))){
    log_data("Integration not required");
    send_response([
    	'status' => 'success',
    	'message' => 'integration not required'
    ]);
    exit;
}

$question_text = "Organisation Name";

if($calendly_payload["scheduled_event"]["event_type"] === $round_robin_link){
    $question_text = "Workplace Name";
}

$found_key_org = array_search($question_text, array_column($calendly_payload["questions_and_answers"], 'question'));
$org_name = $calendly_payload["questions_and_answers"][$found_key_org]["answer"];

$found_key_info = array_search("Please share anything that will help prepare for our meeting.", array_column($calendly_payload["questions_and_answers"], 'question'));
$info_provided = $calendly_payload["questions_and_answers"][$found_key_info]["answer"];

$scheduled_date = $calendly_payload["scheduled_event"]["start_time"];


$data = array(
    "contact_email" => $calendly_payload["email"],
    "contact_first_name" => $calendly_payload["first_name"], 
    "contact_last_name" => $calendly_payload["last_name"], 
    "organisation_name" => $org_name,
    "scheduled_date" => $scheduled_date,
    "info_provided" => $info_provided,
    "source_form" => "Calendly Prospect",
);

log_data("--------------");
log_data("Creating deal for");
log_data(print_r($data, 1));
log_data("--------------");

$data_controller = new WorkplaceVTController($data);

$success = $data_controller->create_calendly_prospect();


// Then, respond with a success
send_response([
	'status' => $success ? 'success' : 'fail',
]);
exit;

function log_data($var)
{
    $content = date("Y-m-d H:i:s") . "\t";
    if (is_array($var) || is_object($var)) {
        $content .= print_r($var, true);
    } else {
        $content .= $var;
    }
    $content .= "\n";
//     echo $content;
//     echo "<br>";
    if (!file_exists(dirname(__FILE__) . "/calendlyLog.log")) {
        @touch(dirname(__FILE__) . "/calendlyLog.log", 0777, true);
    }
    file_put_contents(dirname(__FILE__) . "/calendlyLog.log", $content, FILE_APPEND);
}