<?php
//Requires dhrest

class dhvt {
	public $url;
	public $userName;
	public $accessKey;
	
	public $false = true;
	
	public $authed = false;
	
	public $challengeToken;
	public $sessionId;
	public $userId;
	public $apiVersion;
	public $vtigerVersion;
	
	public $ret_array = true;
	public $status;
	public $header;
	public $response;
	public $data;
	
	public $debug=false;
	public $echo_debug=false;
	
	public $api_delay=0; //value in seconds
	
	function __construct($url,$userName,$accessKey) {

		if ($userName && strpos($userName, '+') !== false) {
		    $userName = str_replace('+', '%2B', $userName );
		}

		$this->url = $url;
		$this->authenticate($userName,$accessKey);
	}
	
	function authenticate($userName="",$accessKey="") {
		if(!empty($userName)) $this->userName = $userName;
		if(!empty($accessKey)) $this->accessKey = $accessKey;
		
		if($this->authed)
			$this->logout();
		
		if(!empty($this->userName) && !empty($this->accessKey)) {
			if($this->getChallange() !== false)
				$this->login();
		}
		
		if(!$this->authed)
			throw new Exception("Unable to authenticate");
	}
	
	//Operation based functions
	function listTypes() {
		$data = $this->call("get",$this->url."?operation=listtypes&sessionName=".$this->sessionId);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function describe($type) {
		$data = $this->call("get",$this->url."?operation=describe&sessionName=".$this->sessionId."&elementType=".$type);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function describeRelations($type) {
		$data = $this->call("get",$this->url."?operation=relatedtypes&sessionName=".$this->sessionId."&elementType=".$type);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function retrieve($wsid) {
		$data = $this->call("get",$this->url."?operation=retrieve&sessionName=".$this->sessionId."&id=".$wsid);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function retrieveRelated($wsid,$relatedLabel,$relatedType,$page="") {
		$endpoint = $this->url."?operation=retrieve_related&sessionName=".$this->sessionId."&id=".$wsid."&relatedLabel=".$relatedLabel."&relatedType=".$relatedType;
		if($page != "")
			$endpoint.="&page=$page";
		$data = $this->call("get",$endpoint);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function retrieveAllRelated($wsid,$relatedLabel,$relatedType) {
		$allrecords = array();
		$cont = true;
		$page=0;
		while($cont) {
			$page++;
			$records = $this->retrieveRelated($wsid,$relatedLabel,$relatedType,$page);
			foreach($records as $k=>$v) {
				$allrecords[] = $v;
			}
			if(count($records) < 100) {
				$cont = false;
			}
		}
		if(empty($allrecords))
			return false;
		return $allrecords;
	}
	function create($type,$data=array()) {
		$parms = array();
		$parms["operation"] = "create";
		$parms["sessionName"] = $this->sessionId;
		$parms["elementType"] = $type;
		$parms["element"] = json_encode($data);
		$data = $this->call("post",$this->url,$parms);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function update($data=array()) {
		$parms = array();
		$parms["operation"] = "update";
		$parms["sessionName"] = $this->sessionId;
		$parms["element"] = json_encode($data);
		$data = $this->call("post",$this->url,$parms);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function revise($data=array()) {
		$parms["operation"] = "revise";
		$parms["sessionName"] = $this->sessionId;
		$parms["element"] = json_encode($data);
		$data = $this->call("post",$this->url,$parms);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function delete($wsid) {
		$parms = array();
		$parms["operation"] = "delete";
		$parms["sessionName"] = $this->sessionId;
		$parms["id"] = $wsid;
		$data = $this->call("post",$this->url,$parms);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	function query($query) {
		$data = $this->call("get",$this->url."?operation=query&sessionName=".$this->sessionId."&query=".urlencode($query));
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	
	function comment($comment, $related, $user){
	    //$createcomment =    $wsC->operation("create", array("elementType" => "ModComments", "element" => json_encode($commentelement)), "POST",$file_path);
		$parms = array();
		$parms["operation"] = "create";
		$parms["sessionName"] = $this->sessionId;
		$parms["elementType"] = "ModComments";
		$parms["element"] = json_encode(array(
            'commentcontent'=>$comment,
            'related_to' => $related,
            'assigned_user_id'=> $user,
        ));
		$data = $this->call("post",$this->url,$parms);
		if(is_array($data)) {
			return $data;
		} else {
			return false;
		}
	}
	
	//Authentication based functions
	function getChallange() {
		$data = $this->call("get",$this->url."?operation=getchallenge&username=".$this->userName);
		if(is_array($data)) {
			$this->challengeToken = $data["token"];
		} else {
			return false;
		}
		return true;
	}
	function login() {
		$parms = array("operation"=>"login","username"=>$this->userName,"accessKey"=>md5($this->challengeToken.$this->accessKey));
		$data = $this->call("post",$this->url,$parms);
		if(is_array($data) && isset($data["sessionName"])) {
			$this->sessionId = $data["sessionName"];
			$this->userId = $data["userId"];
			$this->apiVersion = $data["version"];
			$this->vtigerVersion = $data["vtigerVersion"];
			$this->authed = true;
		} else {
			$this->authed = false;
			return false;
		}
		return true;
	}
	function logout() {
		$parms = array("operation"=>"logout","sessionName"=>$this->sessionId);
		$data = $this->call("post",$this->url,$parms);
		$this->authed = false;
		$this->sessionId = "";
		$this->userId = "";
		$this->apiVersion = "";
		$this->vtigerVersion = "";
	}
	
	//Internal functions for interfacing with the rest library
	function call($type,$url,$parms=array()) {	
		if(is_array($parms) && $type == "post") {
			$string = "";
			foreach($parms as $k=>$v) {
				$string.="&$k=$v";
			}
			$parms = trim($string,"&");
			
		}
		
		if($this->debug) {
			echo "\n";
			echo "Call: $type $url\n";
			echo "Input:\n";
			echo $parms."\n";
			echo "\n\n";
		}
		
		switch(strtolower($type)) {
			case "get":
				$return = $this->parse(dhrest::get($url,$parms));
				break;
			case "post":
				$return = $this->parse(dhrest::post($url,$parms));
				break;
			case "put":
				$return = $this->parse(dhrest::put($url,$parms));
				break;
			case "delete":
				$return = $this->parse(dhrest::delete($url,$parms));
				break;
			case "head":
				$return = $this->parse(dhrest::head($url,$parms));
				break;
			default:
				$return = false;
				break;
		}
		if($this->api_delay>0) {
			if($this->echo_debug)
				echo "..\n";
			usleep($this->api_delay*1000000);
		}
		
		if($return === false) return false;
		
		if($this->ret_array) return (array) $return;
		return $return;
	}
	function parse($response) {
		$this->response = $response;
		$this->status = $response["status"];
		$this->header = $response["header"];
		if($this->ret_array)
			$this->data = json_decode($response["data"],true);
		else
			$this->data = json_decode($response["data"]);
		
		if($this->status != 200) {
			throw new Exception("Webservice returned code: ".$this->status."\n".print_r($this->data,true)."\n");
		}
		
		if($this->debug) echo "Result\n".var_dump($this->data)."\n\n";
		
		
		
		if(is_array($this->data) && isset($this->data["result"])) {
			return $this->data["result"];
		} elseif(is_object($this->data) && !empty($this->data->result)) {
			return $this->data->result;
		} else {
			return !$this->parseError($this->data);
		}
		
	}
	function parseError($data) {
		if(isset($data["error"])) {
			$error = $data["error"];
		} else {
			$error = $data;
		}
		if(isset($error["code"]) || isset($error["message"])) {
			$this->errorCode = $error["code"];
			$this->errorMessage = $error["message"];
			throw new Exception("\nError Code: ".$this->errorCode."\r\nError Message:".$this->errorMessage."\n\n");
		} else {
			return false;
		}
	}

	/*
	 * Establish relation between two records.
	 * Created by Peter.Ha
	 * Request Type : POST
	 * Parameters:
	 * operation : add_related
	 * sessionName : session_from_login_response
	 * sourceRecordId : record_id
	 * relatedRecordId : record_id
	 * relationIdLabel : relation_label
	 *
	 * */
	function addRelated($sourceRecordId, $relatedRecordId, $relationIdLabel = "")
	{
		$parms                    = array();
		$parms["operation"]       = "add_related";
		$parms["sessionName"]     = $this->sessionId;
		$parms["sourceRecordId"]  = $sourceRecordId;
		$parms["relatedRecordId"] = $relatedRecordId;
		$parms["relationIdLabel"] = $relationIdLabel;

		$data = $this->call("post", $this->url, $parms);
		if (is_array($data)) {
			return $data;
		}
		else {
			return false;
		}

	}

	function curlPost($url, $data = null, $headers = null) {
        $curl = curl_init();
        if($headers) {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_HTTPHEADER => $headers
            ));
        } else {
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $data
            ));
        }
        $response = curl_exec($curl);
		return $response;
	}
}
