<?php
class dhrest {
	public static function call($type,$uri,$parms=array(),$auth=false,$timeout=30,$ssl_verify=false) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$uri);
		//curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_HEADER,true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $ssl_verify);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		//curl_setopt($ch, CURLOPT_VERBOSE, true);
		if($auth !== false) {
			curl_setopt($ch, CURLOPT_USERPWD, $auth);
		}
		
		switch($type) {
			case 'GET':
				if(strpos($uri,"?") == false) $uri.='?';
				$uri.=http_build_query($parms);
				break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
				break;
			case 'DELETE':
			case 'HEAD':
			case 'PUT':
			default:
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($type));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $parms);
		}
		
		
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
	
	public static function get($uri,$parms=array(),$auth=false,$timeout=30) {
		return dhrest::call("GET",$uri,$parms,$auth,$timeout);
	}
	public static function post($uri,$parms=array(),$auth=false,$timeout=30) {
		return dhrest::call("POST",$uri,$parms,$auth,$timeout);
	}
	public static function put($uri,$parms=array(),$auth=false,$timeout=30) {
		return dhrest::call("PUT",$uri,$parms,$auth,$timeout);
	}
	public static function head($uri,$parms=array(),$auth=false,$timeout=30) {
		return dhrest::call("HEAD",$uri,$parms,$auth,$timeout);
	}
	public static function delete($uri,$parms=array(),$auth=false,$timeout=30) {
		return dhrest::call("DELETE",$uri,$parms,$auth,$timeout);
	}
}
