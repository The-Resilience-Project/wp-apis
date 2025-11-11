<?php
/**
 * Wordpress webhook to get order from Woocommerce and post it in vtiger
 *
 * taskid: 54815
 * User: rprajapati
 * Date: 20/10/22
 * Time: 12:21 PM
 */


// die("Remove this when push to prod.");
//Remove above die when need to run webhook


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

chdir(dirname(__FILE__));
require_once "../init.php";

//========Debugging=============
error_reporting(0);
//ini_set('display_errors', 'on');
//error_reporting(E_ALL); // STRICT DEVELOPMENT
//echo "<pre>";

$vtod = init_vtod();
// global $dbh;

// Start transaction for performance tracking
$transaction = null;
if (function_exists('\Sentry\startTransaction')) {
    $transactionContext = new \Sentry\Tracing\TransactionContext();
    $transactionContext->setName('Order Webhook');
    $transactionContext->setOp('webhook.order');
    $transaction = \Sentry\startTransaction($transactionContext);
    \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
}

putlogwebhook("========== START ==========");

$postData = json_decode(file_get_contents("php://input"), true);
$schoolName = '';

// Log webhook received with metrics
if (function_exists('\Sentry\captureMessage')) {
    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($postData) {
        $scope->setTag('webhook', 'order');
        $scope->setTag('order_status', $postData['status'] ?? 'unknown');
        $scope->setTag('order_id', $postData['id'] ?? 'unknown');
        $scope->setContext('order_info', [
            'id' => $postData['id'] ?? null,
            'status' => $postData['status'] ?? null,
            'total' => $postData['total'] ?? null,
            'currency' => $postData['currency'] ?? null,
            'date_created' => $postData['date_created'] ?? null,
        ]);
        $scope->setContext('request', [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
        \Sentry\captureMessage('Order webhook received', \Sentry\Severity::info());
    });
}

putlogwebhook("New order to process, process data:");
putlogwebhook($postData);

/*(
    [id] => 1052
    [parent_id] => 0
    [status] => processing
    [currency] => AUD
    [version] => 7.5.1
    [prices_include_tax] => 
    [date_created] => 2023-05-08T10:42:35
    [date_modified] => 2023-05-08T14:56:25
    [discount_total] => 0.00
    [discount_tax] => 0.00
    [shipping_total] => 0.00
    [shipping_tax] => 0.00
    [cart_tax] => 197.20
    [total] => 2169.20
    [total_tax] => 197.20
    [customer_id] => 0
    [order_key] => wc_order_vVWttL5UaC74Q
    [billing] => Array
        (
            [first_name] => Navneet
            [last_name] => Kaur
            [company] => Nido Early School Wyndham Vale
            [address_1] => 9 Welcome Parade
            [address_2] => 
            [city] => Wyndhamvale
            [state] => VIC
            [postcode] => 3024
            [country] => AU
            [email] => esm.wyndhamvale@nido.edu.au
            [phone] => 0370199398
        )

    [shipping] => Array
        (
            [first_name] => Navneet
            [last_name] => Kaur
            [company] => Nido Early School Wyndham Vale
            [address_1] => 9 Welcome Parade
            [address_2] => 
            [city] => Wyndhamvale
            [state] => VIC
            [postcode] => 3024
            [country] => AU
            [phone] => 
        )

    [payment_method] => bacs
    [line_items] => Array
        (
            [0] => Array
                (
                    [id] => 3609
                    [name] => Engage: Early Years Teaching and Learning Program
                    [product_id] => 58
                    [variation_id] => 0
                    [quantity] => 1
                    [tax_class] => 
                    [subtotal] => 0.00
                    [subtotal_tax] => 0.00
                    [total] => 0.00
                    [total_tax] => 0.00
                    [taxes] => Array
                        (
                            [0] => Array
                                (
                                    [id] => 1
                                    [total] => 0
                                    [subtotal] => 0
                                )

                        )
                        
[payment_url] => https://curriculum.theresilienceproject.com.au/checkout/order-pay/688/?pay_for_order=true&key=wc_order_WUjddTmpbWezA
*/

$woocommerce_id = $postData['id'];
// exit;

$tmpDates = explode('T', $postData['date_created']);
$curriculum_ordered_date = $tmpDates[0];


$schoolName = $postData['billing']['company'];
if (empty($schoolName)){
    foreach ($postData['meta_data'] as $otherData) {
        if ($otherData['key'] == 'school_name') {
            $schoolName = $otherData['value'];
        }
    }
    
}
// check payment_url
// $url = $postData['payment_url'];
// $array=parse_url($url);
// $array['host']=explode('.', $array['host']);
// $buildname = $array['host'][0]; // returns 'sub'


// $early_years = false; 
$qty_early_year = NULL;
// if (strpos($postData['payment_url'], 'early-years.theresilienceproject') !== false) {
//     $early_years = TRUE; 
// }
foreach ($postData['line_items'] as $lineItem) {
    if (strpos($lineItem['name'], 'Early Years Children’s Portfolio') !== false){
        $qty_early_year = $lineItem['quantity'] ;
    }
}

if (!empty($schoolName) && $postData['status'] == 'processing') {
    putlogwebhook("Order have processing status, doing process");

    // Log processing started
    if (function_exists('\Sentry\addBreadcrumb')) {
        \Sentry\addBreadcrumb(
            new \Sentry\Breadcrumb(
                \Sentry\Breadcrumb::LEVEL_INFO,
                \Sentry\Breadcrumb::TYPE_DEFAULT,
                'order.processing',
                'Started processing order',
                ['school_name' => $schoolName, 'order_id' => $woocommerce_id]
            )
        );
    }
    // check by buildname first 
    // $sqlGet = "SELECT * FROM boru_woocommerce_order WHERE woocommerce_id = ? and buildname = ?";
    // $dataCheck = $dbh->getSingle($sqlGet, array($woocommerce_id,$buildname));
    // if (empty($dataCheck) && $buildname == 'curriculum' ){
    //     $sqlGet = "SELECT * FROM boru_woocommerce_order WHERE woocommerce_id = ?";
    //     $dataCheck = $dbh->getSingle($sqlGet, array($woocommerce_id));
    // }
    

    // if (!empty($dataCheck['crm_id'])){
    //     putlogwebhook("Order already processed with result:");
    //     putlogwebhook($dataCheck);
    //     putlogwebhook("========== END==========");
    //     die();
    // }
    $accountId = '';

    try {

        $queryOrg = sprintf("SELECT * FROM Accounts WHERE accountname='%s'; ", addslashes( $schoolName ) );
        $resultOrg = $vtod->query($queryOrg);

        if (count($resultOrg) == 0) {
            putlogwebhook("No account found in crm with name, checking other account-> " . $schoolName);

            // Log account not found
            if (function_exists('\Sentry\captureMessage')) {
                \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($schoolName, $woocommerce_id) {
                    $scope->setTag('event', 'account_not_found');
                    $scope->setTag('school_name', $schoolName);
                    $scope->setContext('school_lookup', ['school_name' => $schoolName, 'order_id' => $woocommerce_id]);
                    \Sentry\captureMessage('Account not found, using fallback', \Sentry\Severity::warning());
                });
            }

            $queryOrg1 = "SELECT * FROM Accounts WHERE accountname='School Name Other'; ";
            $resultOrg = $vtod->query($queryOrg1);

            if (count($resultOrg) > 0) {
                putlogwebhook("Other account found using other account.");
                $accountId = $resultOrg[0]['id'];
            }
        } else {
            $accountId = $resultOrg[0]['id'];
        }

        if (empty($accountId)) {
            putlogwebhook("No matching account or other accout found, creating new other account.");

            
            $arr_org['accountname'] = 'School Name Other';
            $arr_org['cf_accounts_organisationtype'] = 'School';
            $arr_org['assigned_user_id'] = $vtod->userId;

            try {
                $dataCOrg = $vtod->create("Accounts", $arr_org);

                if (!empty($dataCOrg['id'])) {
                    putlogwebhook("Other account created with id -> " . $dataCOrg['id']);

                    $accountId = $dataCOrg['id'];

                    // Log account creation
                    if (function_exists('\Sentry\captureMessage')) {
                        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($accountId, $schoolName, $woocommerce_id) {
                            $scope->setTag('event', 'account_created');
                            $scope->setTag('account_type', 'fallback');
                            $scope->setContext('account_creation', [
                                'account_id' => $accountId,
                                'intended_school_name' => $schoolName,
                                'order_id' => $woocommerce_id,
                            ]);
                            \Sentry\captureMessage('Fallback account created', \Sentry\Severity::info());
                        });
                    }
                }
            } catch (Exception $e) {
                putlogwebhook('Error while creating new Other school Account in CRM. error = ' . $e->getMessage());

                // Log account creation error
                if (function_exists('\Sentry\captureException')) {
                    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($schoolName, $woocommerce_id) {
                        $scope->setTag('event', 'account_creation_failed');
                        $scope->setTag('error_type', 'account_creation');
                        $scope->setContext('account_creation', [
                            'school_name' => $schoolName,
                            'order_id' => $woocommerce_id,
                        ]);
                    });
                    \Sentry\captureException($e);
                }
            }
            /**/
        }

        if (!empty($accountId)) {
            putlogwebhook("Account found in system, account id -> " . $accountId);
            if (empty($resultOrg[0]['cf_accounts_curriculumordered'])){

            
                $selectedyearlevels = [];

                $newOrg = array(
                    'id' => $accountId,
                    'cf_accounts_curriculumordered' => $curriculum_ordered_date, // curric ordered date
                    'cf_accounts_selectedyearlevels' => 'Early Years',
                    'cf_accounts_totalresourcesordered' => $qty_early_year,
                );
                putlogwebhook("Updating account in  crm.");
                putlogwebhook($newOrg);

                try {
                    $resUpdate = $vtod->revise($newOrg);

                    // $sql = "INSERT INTO boru_woocommerce_order (woocommerce_id, crm_id, buildname, sync_date) VALUES (?, ?, ?,NOW())";
                    // $dbh->run($sql, array($postData['id'], $resUpdate['id'],$buildname));

                    putlogwebhook("Success, account updated.");

                    // Log successful account update
                    if (function_exists('\Sentry\captureMessage')) {
                        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($accountId, $schoolName, $woocommerce_id, $qty_early_year) {
                            $scope->setTag('event', 'account_updated');
                            $scope->setTag('success', 'true');
                            $scope->setContext('account_update', [
                                'account_id' => $accountId,
                                'school_name' => $schoolName,
                                'order_id' => $woocommerce_id,
                                'qty_early_year' => $qty_early_year,
                            ]);
                            \Sentry\captureMessage('Account updated successfully', \Sentry\Severity::info());
                        });
                    }

                } catch (Exception $e) {
                    putlogwebhook('Error while updating Account in CRM. error = ' . $e->getMessage());

                    // Log account update error
                    if (function_exists('\Sentry\captureException')) {
                        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($accountId, $schoolName, $woocommerce_id) {
                            $scope->setTag('event', 'account_update_failed');
                            $scope->setTag('error_type', 'account_update');
                            $scope->setContext('account_update', [
                                'account_id' => $accountId,
                                'school_name' => $schoolName,
                                'order_id' => $woocommerce_id,
                            ]);
                        });
                        \Sentry\captureException($e);
                    }
                }
                try{
                    putlogwebhook("Attempting to update deal.");
                    $queryDeal = sprintf("SELECT * FROM Potentials WHERE related_to='%s' AND potentialname='2025 Early Years Partnership Program'; ", addslashes( $accountId ) );
                    $resultDeal = $vtod->query($queryDeal)[0];
                    putlogwebhook($queryDeal);
                    putlogwebhook($resultDeal);

                    $dealId = $resultDeal['id'];
                    putlogwebhook($dealId);
                    
                    $deal_items = array_column($resultDeal['lineItems'], 'productid');
                    putlogwebhook($deal_items);
                    $deal_found_key = array_search('25x95211', $deal_items);
                    putlogwebhook($deal_found_key);
                    
                    // $new_line_items = $resultDeal['lineItems'];
                    
                    $new_line_items = Array();
                    foreach($resultDeal['lineItems'] as $item){
                        array_push($new_line_items, array_intersect_key(
                            $item,  // the array with all keys
                            array_flip(["productid", "quantity", "listprice", "netprice", "discount_amount", "discount_percent", "section_name", "section_no", "comment", "billing_type", "duration"]) // keys to be extracted
                        ));
                    }
                    
                    $new_line_items[$deal_found_key]['quantity'] = $qty_early_year;
                    putlogwebhook($new_line_items);

                    $order_items = array_column($postData['line_items'], 'name');
                    putlogwebhook($order_items);
                    $order_found_key = array_search('Engage: Early Years Teaching and Learning Program', $order_items);
                    putlogwebhook($order_found_key);
                    $number_of_groups = count($postData['line_items'][$order_found_key]['meta_data'][1]['value']);
                    putlogwebhook($number_of_groups);

                    $newTotal = 0;
                    foreach($new_line_items as $item)
                        {

                            $newTotal += ($item['listprice']*$item['quantity']);
                        }
                    

                    $updatedDeal = array(
                        'id' => $dealId,
                        'cf_potentials_wcreference' => $postData['id'],
                        'cf_potentials_numberofgroups' => $number_of_groups,
                        'cf_potentials_numberofparticipants' => $qty_early_year,
                        'LineItems' => $new_line_items,
                        'hdnGrandTotal' => $newTotal,
                    );
                    putlogwebhook($updatedDeal);

                    $resUpdate = $vtod->revise($updatedDeal);

                    // Log successful deal update
                    if (function_exists('\Sentry\captureMessage')) {
                        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($dealId, $woocommerce_id, $number_of_groups, $qty_early_year) {
                            $scope->setTag('event', 'deal_updated');
                            $scope->setTag('success', 'true');
                            $scope->setContext('deal_update', [
                                'deal_id' => $dealId,
                                'order_id' => $woocommerce_id,
                                'number_of_groups' => $number_of_groups,
                                'participants' => $qty_early_year,
                            ]);
                            \Sentry\captureMessage('Deal updated successfully', \Sentry\Severity::info());
                        });
                    }

                } catch (Exception $e) {
                    putlogwebhook('Error while updating deal in CRM. error = ' . $e->getMessage());

                    // Log deal update error
                    if (function_exists('\Sentry\captureException')) {
                        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($accountId, $woocommerce_id) {
                            $scope->setTag('event', 'deal_update_failed');
                            $scope->setTag('error_type', 'deal_update');
                            $scope->setContext('deal_update', [
                                'account_id' => $accountId,
                                'order_id' => $woocommerce_id,
                            ]);
                        });
                        \Sentry\captureException($e);
                    }
                }

            } else {
                putlogwebhook("Account already has curric ordered date. Update not required." . $resultOrg[0]['cf_accounts_curriculum_ordered_date']);
            }
            /**/
        } else {
            putlogwebhook('No Account found for name and no new accout created = ' . $schoolName);
        }
    } catch (Exception $e) {
        putlogwebhook('Error while fetching account from CRM. error = ' . $e->getMessage());

        // Log critical error
        if (function_exists('\Sentry\captureException')) {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($schoolName, $woocommerce_id) {
                $scope->setTag('event', 'critical_error');
                $scope->setTag('error_type', 'account_fetch');
                $scope->setContext('error_context', [
                    'school_name' => $schoolName,
                    'order_id' => $woocommerce_id,
                ]);
            });
            \Sentry\captureException($e);
        }
    }
} else {
    if (empty($schoolName)) {
        putlogwebhook('No school name in order');

        // Log missing school name
        if (function_exists('\Sentry\captureMessage')) {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($postData) {
                $scope->setTag('event', 'missing_school_name');
                $scope->setTag('order_id', $postData['id'] ?? 'unknown');
                $scope->setLevel(\Sentry\Severity::warning());
                \Sentry\captureMessage('Order received without school name', \Sentry\Severity::warning());
            });
        }
    }else{
        putlogwebhook('Order status is not processing');

        // Log skipped order (non-processing status)
        if (function_exists('\Sentry\captureMessage')) {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($postData) {
                $scope->setTag('event', 'order_skipped');
                $scope->setTag('order_status', $postData['status'] ?? 'unknown');
                $scope->setTag('order_id', $postData['id'] ?? 'unknown');
                \Sentry\captureMessage('Order skipped - status not processing', \Sentry\Severity::info());
            });
        }
    }
}

// Finish transaction
if ($transaction) {
    $transaction->finish();
}

putlogwebhook("========== END==========");


function putlogwebhook($var)
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
    if (!file_exists(dirname(__FILE__) . "/Order.log")) {
        @touch(dirname(__FILE__) . "/Order.log", 0777, true);
    }
    file_put_contents(dirname(__FILE__) . "/Order.log", $content, FILE_APPEND);
}