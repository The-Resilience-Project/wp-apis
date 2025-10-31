<?php 
require dirname(__FILE__)."/traits/contact_and_org.php";
require dirname(__FILE__)."/traits/deal.php";


class VTController{
    use ContactAndOrg;
    use Deal;

    protected $baseUrl = "https://theresilienceproject.od2.vtiger.com/restapi/vtap/webhook/";
    protected $data;
    protected $organisation_name;
    protected $enquiry_type;
    protected const tokens = array(
        "captureCustomerInfo" => "j2bXkMP4TaPmKjTBFXVIsq1K",
        "captureCustomerInfoWithAccountNo" => "ACyvS7YEKlzdFnUGQs25YFii",
        "setContactsInactive" => "P2VyeIMyd8oBZSNzm7tkCPBE",
        "createDeal" => "r8ZUEYcio6VpH0O54jDtE55L",
        "getOrCreateDeal" => "rUrrGktcgYOMPRwg6tyyaFFq",
        "getDealByContactId" => "GgdhkujHge4lwE60DaT7Rwuj",
        "updateDeal" => "ftC3lOd8l9LPs5VoCGC1y8SY",
        "createEnquiry" => "8di4F24NumqITmuAky325Vj3",
        "registerContact" => "xfQg4BDmOPg7TIkUb3kPWFqc",
        "getContactByEmail" => "cmgsCER088SIbv913HcTVUKd",
        "getOrganisationByName" => "d2Ixsf4kKsVaG88tTEvqDUOz",
        "getEventDetails" => "ErMGAwNfnc0fPzspaw9diQyz",
        "getOrgDetails" => "DdtiDMSsq9ETjSe2FMEZBICu",
        "updateContactById" => "YECve9NgFG1C6Os4DjRGeoHC",
        "checkContactRegisteredForEvent" => "znlPIG5g7I4ajSP3YMTCvetO",
        "getContactDetails" => "EBKJfO9StV8vQyeM0epx0SR9",
        "getDealDetails" => "RtpXl9YAcWzWL4B5ZHDHQGuX",
        "getDealDetailsFromAccountNo" => "K7Ub30Q3dLe8dT9UKCpjOPKP",
        "createQuote" => "aYgcfg1PFm1abA3a8QUEZGpJ",
        "getServices" => "jMgenBKJZxTi0mpz4Ga4rQom",
        "setDealLineItems" => "QfbZs2azB5yo2ps1yBIDzSp1",
        "setQuoteLineItems" => "g3j6HqHbKLjANpWSBAYAuA0R",
        "updateOrganisation" => "BP4MhG0zOY0qcY4fEzXnWIFZ", 
        "getOrgWithAccountNo" => "iE9d32UPGTrbd89DUVY2grvg",
        "getOrgWithName" => "9Mk0Mn3Pe6jKgdyJVcZRKeTf",
        "getInvoicesFromAccountNo" => "9dEzSH8ZO5aqPdqQSeDG4owy",
        "getInvoicesFromOrgName" => "DZL634WWcKpB2AOgmgzAKJNh",
        "createInvoice" => "JCen6WlxzHItOF0GQ0CKpSwN",
        "getQuoteWithAccountNo" => "5seD73TovOuVx9FCijtJFQ7K",
        "getQuoteWithName" => "SQNxbhwINb0HhXCgTTbc9Lnq",
        "getProducts" => "0riYjXnTEbxgOVK0iIsJkqEx",
        "createDateAcceptance" => "SbJ3cqpNKUSQcijQgCghvUI0",
        "updateDateAcceptance" => "u6sPyMXpA93G3Q0oIUxtvbJ2",
        "getContactById" => "RjlLbIhNjmR92dtek5YQfAcg",
        "createOrUpdateInvitation" => "VvKtda93QJ2Cf6q9Qf8nknGF",
        "createAssessment" => "5prJsecGL2qc8yLU8IAaTize",
        "createOrUpdateSEIP" => "3fcJ9sPNZ91zfozuMVLii6yu",
    );
    
    protected const MADDIE = "19x1";
    protected const LAURA = "19x8";
    protected const VICTOR = "19x33";
    protected const DAWN = "19x22";
    protected const EMMA = "19x15";
    protected const ASHLEE = "19x29";
    protected const BRENDAN = "19x57";
    protected const ED_TEAM = "20x47";
    protected const HELENOR = "19x24";

    protected $contact_id;
    protected $billing_contact_id;
    protected $billing_contact_email;
    protected $organisation_id;
    protected $organisation_details;
    protected $deal_id;
    protected $deal_details;
    protected $quote_id;
    protected $contact_name;
    
    public function __construct($data){
        $this->data = $data;
    }
    protected function get_token($endpoint){
        return self::tokens[$endpoint];
    }

    protected function post_request_to_vt($endpoint, $request_body, $get=false){
    	$request_header = array();
    	$request_header[] = "token: ".$this->get_token($endpoint);
        $request_header[] = "Content-Type: application/json";
        
        $request_method = $get ? "GET" : "POST";
    
    
    	$request_handle = curl_init( $this->baseUrl.$endpoint );
    	curl_setopt_array( $request_handle, array(
    		CURLOPT_CUSTOMREQUEST => $request_method,
    		CURLOPT_POSTFIELDS => json_encode($request_body),
    		CURLOPT_RETURNTRANSFER => true,
    		CURLOPT_HEADER => false,
    		CURLOPT_HTTPHEADER => $request_header,
    	));
    
    	$response = curl_exec( $request_handle );
    	if($response === false)
        {
            error_log('Curl error: ' . curl_error($response));
        }
        $json_response = json_decode($response);
        curl_close($request_handle);
        return $json_response;
    }
    
    protected function post_request_with_line_items($endpoint, $request_body, $line_items){
        $request_string = "";
        foreach($request_body as $key => $value){
            if (is_array($value)){
                foreach($value as $array_item){
                    $request_string .= $key."[]=".$array_item."&";
                }
            } else{
                $request_string .= $key."=".$value."&";
            }
        }
        $request_string .= "lineItems=".json_encode($line_items);

    	$request_header = array();
    	$request_header[] = "token: ".$this->get_token($endpoint);
        $request_header[] = "Content-Type: application/x-www-form-urlencoded";
        
    
    	$request_handle = curl_init( $this->baseUrl.$endpoint );
    	curl_setopt_array( $request_handle, array(
    		CURLOPT_CUSTOMREQUEST => "POST",
    		CURLOPT_POSTFIELDS => $request_string,
    		CURLOPT_RETURNTRANSFER => true,
    		CURLOPT_HEADER => false,
    		CURLOPT_HTTPHEADER => $request_header,
    	));
    
    	$response = curl_exec( $request_handle );

        $json_response = json_decode($response);
        curl_close($request_handle);
        return $json_response;
    }
    
    protected function isset_data($key){
        return isset($this->data[$key]) and !empty($this->data[$key]);
    }
    
    protected function get_services($codes, $ids=array()){
        $request_body = array(
            "serviceNumbers" => $codes,
            "serviceIds" => $ids
        );

    	$response = $this->post_request_to_vt("getServices", $request_body, true);
        return $response->result;
    }
    
    protected function get_products($codes){
        $request_body = array("productNumbers" => $codes);
    	$response = $this->post_request_to_vt("getProducts", $request_body, true);
        return $response->result;
    }
}
 