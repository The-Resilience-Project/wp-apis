<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL  & ~(E_STRICT|E_NOTICE| E_WARNING | E_PARSE |E_DEPRECATED) );

chdir(dirname(__FILE__));
require "../init.php";

// Log API call to Sentry
if (function_exists('\Sentry\captureMessage')) {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) {
        $scope->setTag('endpoint', 'getEventPlanned');
        $scope->setTag('method', $_SERVER['REQUEST_METHOD'] ?? 'unknown');
        $scope->setContext('request', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('API: getEventPlanned endpoint called', \Sentry\Severity::info());
    });
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

$vtod = init_vtod();
$vtod_config_url = $vtod_config["url"];
$type;
if(isset($_REQUEST['type'])){
    $type = $_REQUEST['type'];
}
$sql = "SELECT id,subject,eventstatus,cf_events_shorteventname FROM Events ";
if($type && $type == 'leading-trp') {
    $sql .= "  WHERE eventstatus='Open for registration' AND cf_events_presentationworkshoptype='Leading TRP in your School'";
} else if($type && $type == 'early-year') {
    $sql .= " WHERE eventstatus='Open for registration' AND cf_events_presentationworkshoptype='EY Information Session'";
} else {
    $sql .= " WHERE eventstatus='Open for registration' AND cf_events_presentationworkshoptype='Information Session'";
}

$sql .= " ORDER BY date_start, time_start;";

$result = $vtod->query($sql);
$optionContent = "";
$optionTextValueMapping = array();
if (is_array($result) && count($result) > 0) {
    foreach ($result as $key => $event_val) {
        if(!empty($event_val['id'])) {
            if(isset($_REQUEST['event'])){
                if(trim($_REQUEST['event']) == trim($event_val['cf_events_shorteventname'])) {
                    $optionContent .= "<option value='".$event_val['id']."' selected='selected'>".$event_val['cf_events_shorteventname']."</option>";
                    $optionTextValueMapping[] = array( 'text' => $event_val['cf_events_shorteventname'], 'value' => $event_val['id'], 'isSelected' => true );
                }
            } else {
                $optionContent .= "<option value='" . $event_val['id'] . "'>" . $event_val['cf_events_shorteventname'] . "</option>";
                $optionTextValueMapping[] = array( 'text' => $event_val['cf_events_shorteventname'], 'value' => $event_val['id'] );
            }
        }
    }
}
echo json_encode(array(
    'optionContent' => $optionContent,
    'optionTextValueMapping' => $optionTextValueMapping,
));
die;
