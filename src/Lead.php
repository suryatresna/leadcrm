<?php

namespace Leadcrm;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;
use Goweb\Goadmin\Entities\LeadLog;

class Lead {

	protected $client;

	protected $config = [
		'page_id',
		'form_id',
		'access_token',
		'verify_token' => 'abc123',
		'valid_rules'
	];

	public $challenge = false;

	public $response = false;

	public $values = [];

	public $notif;

	public function __construct($config = array())
	{	
		// Set default endpoint
		$this->client = new Client(['base_uri'=>'https://graph.facebook.com/']);
		// Merge config origin
		$this->config = array_merge($this->config,$config);
	}

	/**
	 * Get Information Page from LeadGen
	 * @param  String $leadgen_id
	 * @return ArrayObject | ClientException
	 */
	public function get_leadgen_id($leadgen_id)
	{
		$res 		= $this->client->request('GET','/v2.8/'.$leadgen_id,['query'=>['access_token'=>$this->config['access_token']]]);
		$res_body 	= $res->getBody();
		$stream 	= Psr7\stream_for($res_body);
        $out 		= json_decode($stream->getContents());
        return $out;
	}

	/**
	 * Handling request from facebook Webhook Messanger
	 * @param  Request $challenge get from input $_REQUEST['hub_challenge']  
	 * @param  Request $verify_token get from input $_REQUEST['hub_verify_token'] 
	 * @param  $anonym Anonym Function
	 * @return $this               
	 */
	public function set_webhook($challenge = false, $verify_token = false, $input = null,$anonym = false)
	{	
		// Verify token webhook with you token.
		if ($verify_token === $this->config['verify_token']) {
		  $this->challenge = $challenge;
		}
		
		// Get input from Webhook Facebook
		// $input = json_decode(file_get_contents('php://input'), true);
		try {
			$this->get_lead_info($input);
			if(!empty($this->values))
				$this->response = $anonym($this);
			

			return $this;
		} catch (Exception $e) {
			return $e;
		}

	}

	public function get_response()
	{
		return $this->response;
	}

	/**
	 * Find array if exist and get value
	 * @param  String $needle   
	 * @param  Array  $haystack 
	 * @return String | Boolean if fails
	 */
	public function recursive_array_search($needle,$haystack) {
	    foreach($haystack as $key=>$value) {
	        $current_value = $value;
	        if($needle===$key OR (is_array($value) && ($current_value = $this->recursive_array_search($needle,$value)) !== false)) {
	            return $current_value;
	        }
	    }
	    return false;
	}

	public function field_data_convert($arr) {
		$temp = [];
		foreach($arr->field_data as $key => $val) {
			$temp[$val->name] = $val->values[0];
		}
		return $temp;
	}

	/**
	 * Get input from Webhook facebook
	 * $input = json_decode(file_get_contents('php://input'), true);
	 * @return Array | Exception if request from get_leadgen_id fails
	 */
	public function get_lead_info($input) {
		if(is_array($input)) {
		    if(($lead_id = $this->recursive_array_search('leadgen_id',$input)) !== false) {
		    	$value = $this->recursive_array_search('value',$input);
			    $this->values = $value;
			    return $this;
		    }
		    throw new Exception("Lead ID not found.", 1);		    
		}
		return false;
	}

	/**
	 * Send Request to Facebook API and get detail information lead ads
	 * @param  Integer $lead_id
	 * @return Void | Exception
	 */
	public function send_http_with_leadid($lead_id) {
		try {
			$res 		= $this->client->request('GET','/v2.8/'.$lead_id,['query'=>['access_token'=>$this->config['access_token']]]);
			$res_body 	= $res->getBody();
			$stream 	= Psr7\stream_for($res_body);
	        $out 		= json_decode($stream->getContents());
	    	return $this->response = $out;
	    } catch(ClientException $e) {
	    	$r = $e->getResponse();
			$resp = Psr7\str($r);
	    	throw new Exception("Request has failed, details: ".$resp);
	    }
	    
	}

}