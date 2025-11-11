<?php
chdir(dirname(__FILE__));
require_once("shipstation.php");

require "../init.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");



if($_SERVER['REQUEST_METHOD'] != "POST") {
    echo json_encode(array('success'=>false));
    exit;
}

class ShipStationOrder {
    protected $vtod;
    protected $shipstation;

    protected $invoice_no;
    protected $invoice_id;
    protected $invoice_data;

    protected $total_weight = 0;
    protected $items = array();
    protected $shipping_price = 0;

    protected $contact_email = '';
    protected $account_name = '';

    protected $ship_to = array();
    protected $bill_to = array();

    protected $ss_payload = array();
    protected $ss_response = '';
    
    const bulk_order_store_id = '380683';
    const school_25_store_id = '809689';
    const school_26_store_id = '823800';

    const item_sections = array("Student Journals", "Teacher Resources", "Extra Resources");
    const teacher_sem = array("25x662672", "25x662669", "25x662668", "25x662673");

    
    protected $store_id = self::school_25_store_id;
    protected $shipstation_order_id = "";
    

    public function __construct($request_invoice_id) {
        $this->pushlog("-----------------Create Shipment For Invoice:".$request_invoice_id."-----------------");
        $this->vtod = init_vtod();
        if(is_string($this->vtod)){
            return;
        }
        $this->invoice_id = $request_invoice_id;
        if (!str_contains($request_invoice_id, '16x')){
            $this->invoice_id = '16x' . $request_invoice_id;
        }
        $this->pushlog("-----------------Inv ID:".$this->invoice_id."-----------------");

        
        // $authKey = base64_encode("384c7039e4914a31935861a6e52b4974:fed4f771714b48919e7cdfa2d94411db"); // Shipstation API Key:API Secret
        $authKey = base64_encode("cec687e3607445c9b939f2fa6f114cd3:70d7a032e6b04821837f41fd325f6949"); // Shipstation API Key:API Secret
        $this->shipstation = new shipstation($authKey);
        $this->shipstation->decode=true;
        $this->shipstation->print=false;
    }

    public function create(){
        if(is_string($this->vtod)){
            $this->pushlog("Failed to create SS order - unable to init vtod". $this->vtod);
            $this->pushlog("---------------------------------------------------------------------");
            http_response_code(500);
            echo json_encode(array('success'=>'false', 'msg'=>'Failed to init'));
            exit;
        }

        if(!$this->invoice_id) {
            $this->pushlog("Failed to create SS order - no invoice ID");
            $this->pushlog("---------------------------------------------------------------------");
            // $this->vtod->comment("Failed to create order in SS - no invoice ID provided", $this->invoice_id, "19x1");
            http_response_code(500);
            echo json_encode(array('success'=>'false', 'msg'=>'No Invoice ID'));
            exit;
        }

        $this->invoice_data = $this->vtod->retrieve($this->invoice_id);

        if(!$this->invoice_data['id']) {
            $this->pushlog("Failed to create SS order - failed to find invoice in VT");
            $this->pushlog("---------------------------------------------------------------------");
            http_response_code(500);
            echo json_encode(array('success'=>'false', 'msg'=>"Failed to find invoice in VT"));
            exit;
        }

        $this->pushlog("Found invoice data in VT");
        $this->invoice_id = $this->invoice_data['id'];
        if(str_contains($this->invoice_data['subject'], "Workplace")){
            // workplace store
            $this->store_id = self::bulk_order_store_id;
            $this->shipstation_order_id = "2".$this->invoice_data['invoice_no'];
        } else if(str_contains($this->invoice_data['subject'], "2026")){
            // 2026 store
            $this->store_id = self::school_26_store_id;
            $this->shipstation_order_id = $this->invoice_data['invoice_no'];
        } else {
            // 2025 store
            $this->store_id = self::school_25_store_id;
            $this->shipstation_order_id = $this->invoice_data['invoice_no'];
        }

        // check if order needs to be created
        if($this->count_line_items() == 0 ){
            echo json_encode(array("success" => 'true', 'orderData'=>'', "msg" => "No need to create order in Ship Station"));
            exit;
        }
        
        // check if order already in SS 
        if($this->is_order_in_SS()){
            $this->pushlog("Order already in SS");
            $this->pushlog("-------------------------------------------------------------------");
            echo json_encode(array('success'=>'true','orderData'=>'', 'msg' => "Order already in Ship Station"));
            exit;
        }
        
        try{
            $this->format_items();
            $this->pushlog("-- Formatted Items");
            
            $this->set_account_data();
            $this->pushlog("-- Set Account Data");
            
            $this->format_ship_to();
            $this->pushlog("-- Formatted Ship To");
            
            $this->format_bill_to();
            $this->pushlog("-- Formatted Bill To");
            
            $this->format_ss_payload();
            $this->pushlog("SS Payload");
            $this->pushlog($this->ss_payload);
        } catch (Exception $e) {
            $error = $e->getMessage();
            $this->pushlog('Error formatting SS payload: '. $error . "\n");
            // $this->vtod->comment("Failed to create order in SS.", $this->invoice_id, $this->invoice_data["assigned_user_id"]);
            http_response_code(500);
            echo json_encode(array('success'=>'false','msg' => $error));
            exit;
        }

        $ss_response = $this->shipstation->post("/orders/createorder", $this->ss_payload);

        if(!isset($ss_response["orderId"])){
            $this->pushlog("Failed to create SS order");
            $this->pushlog($ss_response);
            

            http_response_code(500);
            echo json_encode(array('success'=>'false','msg' => "Failed to create order in SS: ". print_r($ss_response["data"], 1)));
            exit;
        }
        
        $this->pushlog("Created SS order".$ss_response["orderId"]);
        $this->pushlog($ss_response);
        

        if($this->invoice_data['cf_invoice_holduntil']){
            $hold_response = $this->shipstation->post("/orders/holduntil", array(
              "orderId" => $ss_response["orderId"],
              "holdUntilDate" => $this->invoice_data['cf_invoice_holduntil']
            ));
            $this->pushlog("Hold response");
            $this->pushlog($hold_response);

            if(isset($hold_response["success"]) and $hold_response["success"]){
                // 
            } else{
                $this->pushlog("Failed to hold order");
                
                http_response_code(500);
                echo json_encode(array('success'=>'false','msg' => "Failed to hold order in SS"));
                exit;
                
            }
        }


        $this->pushlog("-------------------------------------------------------------------");

        echo json_encode(array('success'=>'true','orderData' => $ss_response["orderId"], 'msg'=>''));
        exit;


    }
    
    private function is_order_in_SS(){
        $this->pushlog("Getting order ".$this->shipstation_order_id);
        $ss_response = $this->shipstation->get("/orders/?orderNumber=".$this->shipstation_order_id."&storeId=".$this->store_id."&orderStatus=on_hold&orderStatus=shipped&orderStatus=awaiting_shipment");
        $number_of_orders = $ss_response["total"];
        $this->pushlog(print_r($ss_response,1));
        return (int)$number_of_orders > 0;
    }

    private function format_ss_payload(){
        $this->ss_payload["orderNumber"] = $this->shipstation_order_id;
        $this->ss_payload["orderDate"] = date('Y-m-d');
        $this->ss_payload["orderStatus"] = $this->invoice_data['cf_invoice_holduntil'] ? "on_hold" : "awaiting_shipment";
        // $this->ss_payload["holdUntilDate"] = $this->invoice_data['cf_invoice_holduntil'];
        if($this->invoice_data['cf_invoice_shipby']){
            $this->ss_payload["shipByDate"] = $this->invoice_data['cf_invoice_shipby'];
        }

        $this->ss_payload["billTo"] = $this->bill_to;
        $this->ss_payload["shipTo"] = $this->ship_to;
        $this->ss_payload['items'] = $this->items;

        $this->ss_payload["carrierCode"] = "star_track";
        $this->ss_payload["serviceCode"] = "express";
        $this->ss_payload["packageCode"] = "startrack_carton";
        $this->ss_payload["shippingAmount"] = $this->shipping_price;
        $this->ss_payload["customerEmail"] = $this->contact_email;
        $this->ss_payload["weight"] = array(
            "value" => $this->total_weight,
            "units" => "grams"
        );
        $this->ss_payload["dimensions"] = array(
            "units" => "centimeters",
            "length" => 35.00,
            "width" => 23.00,
            "height" => 22.00
        );
        $this->ss_payload["advancedOptions"] = array(
            "storeId" => $this->store_id
        );
    }


    private function set_account_data(){
        if($this->invoice_data['account_id']) {
            $account_data = $this->vtod->retrieve($this->invoice_data['account_id']);
            if($account_data['accountname']) {
                $this->account_name = $account_data['accountname'];
            }
        }
    }

    private function format_bill_to(){
        $this->bill_to["name"] = $this->account_name;
        $this->bill_to["company"] = "";
        $this->bill_to["street1"] = $this->invoice_data['bill_street'];
        $this->bill_to["city"] = $this->invoice_data['bill_city'];
        $this->bill_to["state"] = $this->invoice_data['bill_state'];
        $this->bill_to["postalCode"] = $this->invoice_data['bill_code'];
        $this->bill_to["country"] = "AU";
        $this->bill_to["residential"] = null;
    }

    private function format_ship_to(){
        $this->ship_to["company"] = $this->account_name;
        $this->ship_to["street1"] = $this->invoice_data['ship_street'];
        $this->ship_to["city"] = $this->invoice_data['ship_city'];
        $this->ship_to["state"] = $this->invoice_data['ship_state'];
        $this->ship_to["postalCode"] = $this->invoice_data['ship_code'];
        $this->ship_to["country"] = "AU";
        $this->ship_to["residential"] = false;
        $this->ship_to["addressVerified"] = "Address validated successfully";

        if(empty($this->invoice_data['contact_id'])) {
            return;
        }

        $contact_data = $this->vtod->retrieve($this->invoice_data['contact_id']);
        if($contact_data['phone']) {
            $this->ship_to["phone"] = $contact_data['phone'];
        }
        if($contact_data['email']) {
            $this->contact_email = $contact_data['email'];
        }
        if($contact_data['firstname']) {
            $this->ship_to["name"] = $contact_data['firstname'];
        }
        if($contact_data['lastname']) {
            $this->ship_to["name"] .= " ".$contact_data['lastname'];
        }
        
    }

    private function count_line_items(){
        $line_items = $this->invoice_data['LineItems'];
        $count = 0;

        foreach($line_items as $line_item) {
            
            if(in_array($line_item['section_name'], self::item_sections)){
                $count++;
            }
        }
        return $count;
    }

    private function format_items(){
        $line_items = $this->invoice_data['LineItems'];

        $product_ids = array();
        
        foreach($line_items as $line_item) {
            
            if(!in_array($line_item['section_name'], self::item_sections)){
                continue;
            }
            $product_ids[] = "'".$line_item['productid']."'";
        }
        
        $sql = "SELECT * FROM Products WHERE id IN (".implode(',',$product_ids).");";
        $products = $this->vtod->query($sql);
        $product_map = array();
        
        foreach($products as $p){
            $product_map[$p['id']] = array("weight" => $p['cf_products_weight'], "sku" => $p["cf_products_skunumber"]);
        }

        foreach($line_items as $line_item) {

            if($line_item['product_name'] === 'Shipping costs'){
                $this->shipping_price = $line_item['netprice'];
                $this->pushlog("-- Shipping costs ". $this->shipping_price);
                continue;
            }
            if(!in_array($line_item['section_name'], self::item_sections)){
                continue;
            }
            if(in_array($line_item['productid'], self::teacher_sem)){
              continue;  
            }
            $qty = (int)$line_item['quantity'];
            $product_data = $product_map[$line_item['productid']];
            $product_sku = $product_data["sku"];
            $product_weight = $product_data["weight"];
            $this->total_weight += (float)$product_weight * $qty;
            $this->items[] = array(
                'name' => $line_item['product_name'],
                'quantity' => $qty,
                'unitPrice' => $line_item['listprice'],
                'sku' => $product_sku,
            );
        }
        
    }

    private function pushlog($var) {
        global $debug;
        if(!$debug)
            return;

        $content = date("Y-m-d H:i:s")."\t";
        if(is_array($var) || is_object($var)) {
            $content.=print_r($var,true);
        } else {
            $content.=$var;
        }
        $content.="\n";

        file_put_contents(dirname(__FILE__)."/createShipment2025.log",$content,FILE_APPEND);
    }
}
$ship_station_order = new ShipStationOrder($_REQUEST['recordid']);
$ship_station_order->create();