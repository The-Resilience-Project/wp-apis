<?php
class shipstation {
	public $root_uri = "https://ssapi.shipstation.com";

	public $decode = true;
	public $print = false;
	
	
	private $apikey;
	
	function __construct($apikey) {
		//define the api_key and api_secret on construct
		$this->apikey = $apikey;
	}
	
	//Raw query function -- includes signing the request
	function query($type,$endpoint,$parms=array()) {
		$ch = curl_init();
		
		$rest = "";
		//if there is any url params, remove it for the signature
		if(strpos($endpoint,"?")!==false){
			$arr = explode("?",$endpoint);
			$endpoint = $arr[0];
			$rest = "?".$arr[1];
		}
		
		//URI is our root_uri + the endpoint
		$uri = $this->root_uri.$endpoint.$rest;
		
		switch($type) {
			case 'GET':
				//if(strpos($uri,"?") == false) $uri.='?';
				//$uri.=http_build_query($parms);
				$parms = json_encode($parms);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
				break;
			case 'POST':
				$parms = json_encode($parms);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
				break;
			case 'DELETE':
			case 'HEAD':
			case 'PUT':
				$parms = json_encode($parms);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
				break;
			default:
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
		}
		
		//Headers to include our key, signature and nonce
		$headers = array(
			'Content-Type: application/json',
			'Authorization: Basic '.$this->apikey,
		);
		
		//Curl request
		curl_setopt($ch, CURLOPT_URL,$uri);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Boru PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		
		
		$response  = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		//echo $parms."\n";

		return array(
			'status'=>$info['http_code'],
			'header'=>trim(substr($response,0,$info['header_size'])),
			'data'=>substr($response,$info['header_size'])
		);
	}
	function parseReturn($array) {
		$data = array();
		if($array["status"] != 200) {
			$data = $array;
		}else {
			if($this->decode)
				$data = json_decode($array["data"],true);
			else 
				$data = $array["data"];
		}
		if($this->print) {
			echo "\nReturned Data::\n";
			if(is_array($data))
				print_r($data);
			else 
				echo $data;
			echo "\n";
			return $data;
		} else {
			return $data;
		}
	}
	//helper aliases just to make things easier
	function get($endpoint,$parms=array()) {
		return $this->parseReturn($this->query("GET",$endpoint,$parms));
	}
	function post($endpoint,$parms=array()) {
		return $this->parseReturn($this->query("POST",$endpoint,$parms));
	}
	function put($endpoint,$parms=array()) {
		return $this->parseReturn($this->query("PUT",$endpoint,$parms));
	}
	function delete($endpoint,$parms=array()) {
		return $this->parseReturn($this->query("DELETE",$endpoint,$parms));
	}
}