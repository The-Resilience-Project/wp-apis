<?php
require_once dirname(__FILE__)."/base.php";

require_once dirname(__FILE__)."/traits/enquiry.php";
require_once dirname(__FILE__)."/traits/lead.php";
require_once dirname(__FILE__)."/traits/registration.php";
require_once dirname(__FILE__)."/traits/confirmation.php";


class EarlyYearsVTController extends VTController {
    use Enquiry;
    use Lead;
    use Registration;
    use Confirmation;


	protected $organisation_type = "Early Years";
	protected $deal_name = "2026 Early Years Partnership Program";
	protected $deal_type = "Early Years";
	protected $deal_org_type = "Early Years - New";
	protected $enquiry_type = "Early Years";
	
	protected $inspire = "EY Digital";
	protected $quote_name = "2026 Early Years Partnership Program";
	protected $quote_type = "Early Years";
	protected $quote_program = "Early Years";
    
    protected function get_enquiry_assignee(){
        return self::BRENDAN; // get Emma's ID
    }
    
    protected function get_contact_assignee(){
        $org_assignee = $this->organisation_details["assigned_user_id"];
        if($org_assignee != self::MADDIE){
            return $org_assignee;
        }  
        return self::BRENDAN;
    }
    
    protected function get_org_assignee(){
        $org_assignee = $this->organisation_details["assigned_user_id"];
        if($org_assignee != self::MADDIE){
            return $org_assignee;
        }  
        return self::BRENDAN;
    }

    // protected function capture_customer_info_in_vt($customer_data){
    //     $this->deactivate_contacts($customer_data["contact_email"]);

    //     $request_body = $this->format_customer_info_payload($customer_data);
    //     $response = $this->post_request_to_vt("captureCustomerInfo", $request_body);
    //     $response_data = $response->result[0];
            
    //     return $response_data;

    // }
    protected function capture_customer_info_in_vt($customer_data){
        $this->deactivate_contacts($customer_data["contact_email"]);
        
    	$request_body = $this->format_customer_info_payload($customer_data);

    	$response;
    	
        if($this->data["service_name_other_selected"]){
            $request_body["organisationName"] = $this->data["earlyyears_name_other"];
            $response = $this->post_request_to_vt("captureCustomerInfo", $request_body);
        } else {
            $request_body["organisationAccountNo"] = $this->data["earlyyears_account_no"];
            $response = $this->post_request_to_vt("captureCustomerInfoWithAccountNo", $request_body);
        }
    	
        $response_data = $response->result[0];
        return $response_data;
    }
    
    public function submit_enquiry(){
        try{
            $deal_close_date = date("d/m/Y", strtotime("+2 Weeks"));
            
            $this->capture_customer_info();
            $this->update_or_create_deal("New", $deal_close_date);
            $this->create_enquiry();
            return true;
        }
        catch(Exception $e){
            return false;
        }
    }
    
    public function submit_event_registration(){
        try{
            $event = $this->get_event_details($this->data['event_id']);
        	$event_start_date = $event->date_start;
        	$event_start_datetime = $event_start_date." ".$event->time_start;

            $this->capture_customer_info();

            $deal_close_date = $this->add_one_day($event_start_date);
            $this->update_or_create_deal("Considering", $deal_close_date);
            
            $first_info_session_date = $this->deal_details["cf_potentials_firstinfosessiondate"];
            
            if(empty($first_info_session_date) or strcmp($event_start_datetime, $first_info_session_date) == -1){
                $first_info_session_date = $event_start_datetime;
            }
        
            $this->update_deal_with_registration($first_info_session_date, $this->add_one_day($first_info_session_date));
            
            
            $this->register_contact_for_event($event);


            return true;
        }
        catch(Exception $e){
            return false;
        }
    }
    
    protected function get_quote_stage(){
        return "Delivered";
    }
    
    protected function get_line_items(){
        $engage_code = "SER84";
        $inspire_code = "SER13";
        
        $items = array(
            array(
                "qty" => 1,
                "code" => $inspire_code,
                "duration" => 1,
                "section_name" => "Display on Invoice",
                "section_no" => 1,
            ),
            array(
                "qty" => $this->data["participating_num_of_students"],
                "code" => $engage_code,
                "duration" => 1,
                "section_name" => "Display on Invoice",
                "section_no" => 1,
            ),
        );
        
        
        $services = $this->get_services(array_column($items, "code"));
        $line_items = array();
        
        foreach($items as $item){
            $code = $item["code"];
            $service = $services[array_search($code, array_column($services, 'service_no'))];
            
            $line_item = array(
                "productid" => $service->id,
                "quantity" => $item["qty"],
                "listprice" => $service->unit_price,
                "tax5" => "10",
                "cf_quotes_xerocode" => $service->cf_services_xerocode,
                "duration" => $item["duration"],
                "section_name" => $item["section_name"],
                "section_no" => $item["section_no"],
            );
            
            array_push($line_items, $line_item);
        }
        return $line_items;
    }
    
    public function get_info_for_confirmation_form($account_no=null, $accountname=null){
        $deal_response;
        // $org_response;
        $request_body = array(
            "dealName"=> $this->deal_name
        );

        if(!empty($account_no)){
            $request_body["organisationAccountNo"] = $account_no;
            $org_response = $this->post_request_to_vt("getOrgWithAccountNo", $request_body, true);
            $deal_response = $this->post_request_to_vt("getDealDetailsFromAccountNo", $request_body, true);
        } else {
            $request_body["organisationName"] = $accountname;
            $org_response = $this->post_request_to_vt("getOrgWithName", $request_body, true);
            $deal_response = $this->post_request_to_vt("getDealDetails", $request_body, true);
        }
        
        
        $deal_status = "";
        $deal_id = "";
        // $deal_org_type = "";
        // $engage = "";
        if(!empty($deal_response) and !empty($deal_response->result) and !empty($deal_response->result[0])){
            $deal_details = $deal_response->result[0];
            $deal_status = $deal_details->sales_stage;
            $deal_id = $deal_details->id;
            // $deal_org_type = $deal_details->cf_potentials_orgtype;
            // $engage = $deal_details->cf_potentials_curriculum;

        }

        // $free_travel = "";
        // $priority = "";
        // $funded_years = "";
        // $org_state = "";
        // $org_leading_trp = "";
        // if(!empty($org_response) and !empty($org_response->result) and !empty($org_response->result[0])){
        //     $org_details = $org_response->result[0];
        //     $free_travel = $org_details->cf_accounts_freetravel;
        //     $priority = $org_details->cf_accounts_priority;
        //     $funded_years = $org_details->cf_accounts_fundedyears;
        //     $org_state = $org_details->cf_accounts_statenew;
        //     $org_leading_trp = $org_details->cf_accounts_leadingtrp;
        // }
        
        return array(
            "deal_status" => $deal_status,
            "id" => $deal_id,
            // "deal_org_type" => $deal_org_type,
            // "engage" => $engage,
            // "free_travel" => $free_travel,
            // "priority" => $priority,
            // "funded_years" => $funded_years,
            // "org_state" => $org_state,
            // "leading_trp" => $org_leading_trp,
        );
        
    }
    

}