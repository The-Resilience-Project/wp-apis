<?php
trait Confirmation {
    protected function update_years_with_trp($year){
        $years_with_trp_array = explode(" |##| ", $this->organisation_details["cf_accounts_yearswithtrp"]);
        if(!in_array($year, $years_with_trp_array)){
            array_push($years_with_trp_array, $year);
        }
        

        $request_body = array(
            "organisationId" => $this->organisation_id,
            "yearsWithTrp" => $years_with_trp_array,
    		"address" => $this->data["address"],
    		"suburb" => $this->data["suburb"],
    		"postcode" => $this->data["postcode"],
    		"state" => $this->data["state"],
        );
        
    	$response = $this->post_request_to_vt("updateOrganisation", $request_body);

        $response_data = $response->result[0];
        $this->organisation_details["cf_accounts_yearswithtrp"] = $response_data->cf_accounts_yearswithtrp;
    }
    
    protected function set_deal_line_items($line_items, $total){
        $request_body = array(
            "dealId" => $this->deal_id,
            "total" => $total,

        );
    	$response = $this->post_request_with_line_items("setDealLineItems", $request_body, $line_items);
    }

    protected function update_deal_with_confirmation($total){
    	$request_body = array(
    		"dealId" => $this->deal_id,
    		"contactId" => $this->contact_id,
    		
    // 		"inspire" => array($this->data["inspire"]),
    // 		"engage" => array($this->data["engage"]),
    		"address" => $this->data["address"],
    		"suburb" => $this->data["suburb"],
    		"postcode" => $this->data["postcode"],
    		"state" => $this->data["state"],
            "total" => $total,
            "dealStage" => "Deal Won",
            
    	);
    	
    	if(!is_null($this->billing_contact_id)){
    	    $request_body["billingContactId"] = $this->billing_contact_id;
    	}
    	
    	if(isset($this->inspire)){
    	    $request_body["inspire"] = array($this->inspire);
    	} else if($this->isset_data("inspire")){
    	    $request_body["inspire"] = array($this->data["inspire"]);
    	}
    	
    	if(isset($this->engage)){
    	    $request_body["engage"] = $this->engage;
    	} else if($this->isset_data("engage") and $this->data["engage"] !== ""){
            $request_body["engage"] = $this->data["engage"];
        } else {
            $request_body["engage"] = "Journals";
        }
    	
        if(isset($this->extend) and count($this->extend) > 0){
    	    $request_body["extend"] = $this->extend;
    	}
    	
    	if($this->isset_data("mental_health_funding")){
    	    $request_body["mentalHealthFunding"] = $this->data["mental_health_funding"];
    	}
    	
    	if($this->isset_data("kindy_uplift")){
    	    $request_body["kindyUplift"] = $this->data["kindy_uplift"];
    	}
    	
    	if($this->isset_data("srf")){
    	    $request_body["srf"] = $this->data["srf"];
    	}
    	
    	if($this->isset_data("funding_org")){
    	    $request_body["eyFundingOrg"] = $this->data["funding_org"];
    	}
    	
    	if(isset($this->billing_note) && $this->billing_note !== ""){
    	    $request_body["billingNote"] = $this->deal_details["cf_potentials_billingnote"] . ". " . $this->billing_note;
    	}
    // 	"selectedYearLevels" => $this->isset_data("selected_year_levels") ? $this->data["selected_year_levels"]: array(),
    	if($this->isset_data("selected_year_levels")){
    	    $request_body["selectedYearLevels"] = $this->data["selected_year_levels"];
    	}
    	
    	$response = $this->post_request_to_vt("updateDeal", $request_body);
    }

    protected function create_quote($line_items, $total){
        $quote_stage = $this->get_quote_stage();
        
    	$request_body = array(
    		"dealId" => $this->deal_id,
    		"subject" => $this->quote_name,    		
    		"type" => $this->quote_type,
    		"program" => $this->quote_program,
    		"stage" => $quote_stage,
    		"contactId" => $this->contact_id,
    		"contactEmail" => $this->data["contact_email"],
    // 		"billingContactId" => $this->billing_contact_id,
    // 		"billingContactEmail" => $this->billing_contact_email,
    		"organisationId" => $this->organisation_id,
    		"assignee" => $this->get_contact_assignee(),
    		"address" => $this->data["address"],
    		"suburb" => $this->data["suburb"],
    		"postcode" => $this->data["postcode"],
    		"state" => $this->data["state"],
    		"preTaxTotal" => $total,
    		"grandTotal" => $total * 1.1,
    		"taxTotal" => $total * 0.1,
    		
    	);
    	if(!is_null($this->billing_contact_id)){
    	    $request_body["billingContactId"] = $this->billing_contact_id;
    	}
    	if(!is_null($this->billing_contact_email)){
    	    $request_body["billingContactEmail"] = $this->billing_contact_email;
    	}
        $response = $this->post_request_with_line_items("createQuote", $request_body, $line_items);
    	
        $response_data = $response->result;
        $this->quote_id = $response_data->id;
    }
    
    protected function createSEIP(){

        if(get_class($this) === "EarlyYearsVTController"){
            return;
        }
        
        $request_body = array(
            "seipName" => $this->seip_name,
            "organisationId" => $this->organisation_id,
            "dateConfirmed" => date("d/m/Y"),
            "assignee" => $this->organisation_details["assigned_user_id"],
            "participants" => $this->data["participating_num_of_students"],
            "dealId" => $this->deal_id,
        );
        

        
        if(get_class($this) === "SchoolVTController"){
            $request_body["yearsWithTrp"] = "1st year";
        } else if((int)$this->data["participating_num_of_students"] <= 99){
            $request_body["assignee"] = "19x49"; // LCD
        } else if($this->data["state"] === "WA"){
            $request_body["assignee"] = "19x6"; // BW
        }

    	$response = $this->post_request_to_vt("createOrUpdateSEIP", $request_body);

        $response_data = $response->result[0];
        $seip_id = $response_data->id;
        
        $contact_request_body = array(
            "contactId" => $this->contact_id,
            "seipId" => $seip_id,
        );
        

        $contact_response = $this->post_request_to_vt("updateContactById", $contact_request_body);
    }

    public function confirm_program(){
        try{
            if(!$this->isset_data("participating_num_of_students") and $this->isset_data("participating_journal_students") and $this->isset_data("participating_planner_students")){
                $this->data["participating_num_of_students"] = (int)$this->data["participating_journal_students"] + (int)$this->data["participating_planner_students"];
            }
                
            $deal_close_date = date("d/m/Y");
            $this->capture_main_customer_info();
            $this->update_or_create_deal("Deal Won", $deal_close_date);
            $this->capture_billing_contact_info();
            
            $line_items = $this->get_line_items();
            $total = array_sum(array_map(function($item) { 
                return $item['listprice'] * $item['quantity']; 
            }, $line_items));
            
            $this->update_deal_with_confirmation($total);
            $this->set_deal_line_items($line_items, $total);

            $this->create_quote($line_items, $total);
            $this->update_years_with_trp("2026");
            $this->createSEIP();
        }
        catch(Exception $e){
            return false;
        }        
    }
}