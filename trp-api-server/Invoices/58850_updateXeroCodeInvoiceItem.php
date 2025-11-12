<?php
chdir(dirname(__FILE__));
require "../init.php";

// Log API call to Sentry
if (function_exists('\Sentry\captureMessage')) {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) {
        $scope->setTag('endpoint', 'updateXeroCodeInvoiceItem');
        $scope->setTag('method', $_SERVER['REQUEST_METHOD'] ?? 'unknown');
        $scope->setContext('request', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('API: updateXeroCodeInvoiceItem endpoint called', \Sentry\Severity::info());
    });
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
if($_SERVER['REQUEST_METHOD'] != "POST") {
    echo json_encode(array('success'=>false));
    exit;
}
$vtod = init_vtod();
$vtconfig_url = $vtod_config["url"];
$invoiceId = $_REQUEST['id'];
if(!$invoiceId) {
    exit;
}
$invoiceData = $vtod->retrieve($invoiceId);
if(!$invoiceData) {
    exit;
}
$lineItems = $invoiceData['LineItems'];
if(count($lineItems)==0) {
    exit;
}
$update_required = false;
foreach($lineItems as $k => $Item) {
    if(($Item['cf_invoice_xerocode'] === "" or $Item['xero_account'] === "") and $Item['productid']){
        $xero_code = $Item['cf_invoice_xerocode'];
        $xero_sales_account = $Item['xero_account'];
        
        $productData = $vtod->retrieve($Item['productid']);
        if(isset($productData['service_no']) and $productData['cf_services_xerocode'] !== $xero_code) {
            $xero_code = $productData['cf_services_xerocode'];
            $update_required = true;
        } else if(isset($productData['product_no']) and $productData['cf_products_xerocode'] !== $xero_code){
            $xero_code = $productData['cf_products_xerocode'];
            $update_required = true;
        }
        if($productData['xero_account'] and $productData['xero_account'] !== $xero_sales_account) {
            $xero_sales_account = $productData['xero_account'];
            $update_required = true;
        }

        $invoiceData['LineItems'][$k]['cf_invoice_xerocode'] = $xero_code;
        $invoiceData['LineItems'][$k]['xero_account'] = $xero_sales_account;


    }
}
if ($update_required){
    $invoiceJson = json_encode($invoiceData);
    $invoiceParams = array(
        "sessionName" => $vtod->sessionId,
        "operation" => 'update',
        "element" => $invoiceJson
    );
    $dataIV = $vtod->curlPost($vtconfig_url . "webservice.php", $invoiceParams);
    $this->vtod->comment("Updated Xero Code and Xero Sales Account", $invoiceId, "19x1");
}

exit;