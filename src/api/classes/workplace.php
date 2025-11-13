<?php
require_once dirname(__FILE__)."/base.php";

require_once dirname(__FILE__)."/traits/enquiry.php";
require_once dirname(__FILE__)."/traits/registration.php";
require_once dirname(__FILE__)."/traits/qualify.php";
require_once dirname(__FILE__)."/traits/calendly_prospect.php";
require_once dirname(__FILE__)."/traits/lead.php";

class WorkplaceVTController extends VTController {
    use Enquiry;
    use Registration;
    use Qualify;
    use CalendlyProspect;
    use Lead;

	protected $organisation_type = "Workplace";
	protected $deal_name = "2026 Workplace Partner";
	protected $deal_type = "Workplace";
	protected $deal_org_type = "Workplace - New";
	protected $enquiry_type = "Workplace";
	
    
    protected function get_enquiry_assignee(){
        return self::LAURA;
    }
    
    protected function get_contact_assignee(){
        $org_assignee = $this->organisation_details["assigned_user_id"];
        if($org_assignee != self::MADDIE){
            return $org_assignee;
        }
        return self::LAURA;
    }
    
    protected function get_org_assignee(){
        $org_assignee = $this->organisation_details["assigned_user_id"];
        if($org_assignee != self::MADDIE){
            return $org_assignee;
        }        
        return self::LAURA;
    }
    
    public function submit_enquiry(){
        try{
            $deal_close_date = date("d/m/Y", strtotime("+10 Days"));
            
            $this->capture_customer_info();
            $this->update_or_create_deal("New", $deal_close_date);
            $this->create_enquiry();
            return true;
        }
        catch(Exception $e){
            return false;
        }
    }
    
    protected function capture_customer_info_in_vt($customer_data){
        $this->deactivate_contacts($customer_data["contact_email"]);
        
    	$request_body = $this->format_customer_info_payload($customer_data);

    	$response;
    	if($this->isset_data("organisation_name")){
    	    $request_body["organisationName"] = $this->data["organisation_name"];
    	    $response = $this->post_request_to_vt("captureCustomerInfo", $request_body);
    	}
        else if($this->isset_data("workplace_name_other_selected")){
            $request_body["organisationName"] = $this->data["workplace_name_other"];
            $response = $this->post_request_to_vt("captureCustomerInfo", $request_body);
        } else {
            $request_body["organisationAccountNo"] = $this->data["workplace_account_no"];
            $response = $this->post_request_to_vt("captureCustomerInfoWithAccountNo", $request_body);
        }
    	
        $response_data = $response->result[0];
        return $response_data;
    }



    public function submit_event_registration(){
        try{
            // error_log(print_r($this->data, 1));
            $event = $this->get_event_details($this->data['event_id']);
            
            if($this->isset_data('role_workplace')){
                $this->data['job_title'] = $this->data['role_workplace'];
            }
            
            if($this->isset_data('role_school')){
                $this->data['job_title'] = $this->data['role_school'];
            }
            
            if($this->isset_data('role_ey')){
                $this->data['job_title'] = $this->data['role_ey'];
            }
            
            $this->capture_customer_info();
            
            if($this->data["source_form"] === "Workplace Webinar Recording 2025"){
                $size = (int)$this->data["num_of_employees"] >= 100 ? " >100" : " <100";
                $this->data["source_form"] .= $size;   
                
                if($this->organisation_details["cf_accounts_2025confirmationstatus"] === "" and in_array($this->data["organisation_sub_type"], array("Professional Services", "Healthcare","Government","Not for Profit","Retail/Wholesale"))){
                    $deal_close_date = date("d/m/Y", strtotime("+10 Days"));
                    $this->create_deal("In Campaign", $deal_close_date);
                    // $this->update_deal_with_wp_webinar_recording($deal_close_date);
                }
            } 
            
            $this->register_contact_for_event($event);


        }
        catch(Exception $e){
            return false;
        }
    }

}