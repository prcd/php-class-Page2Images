<?php

// Proceed Development Ltd
// http://prcd.co 

class page2imagesHandler
{	
	private $token = NULL;
	
	function __construct($token = NULL)
	{
		if ($token)
		{
			$this->setToken($token);
		}
	}

	private function processApiError($id_in, $msg_in)
	{
		$concat = substr($id_in.$msg_in,0,13);
		
		switch ($concat)
		{
			case '403The API Ke':
				$id  = '5';
				$msg = 'Invalid API key';
				break;
			
			case '403Account do':
				$id  = '6';
				$msg = 'Not enough credits in account';
				break;
			
			case '403Your accou':
				$id  = '7';
				$msg = 'Account has expired or invalid API key';
				break;
			
			case '403You are us':
				$id  = '8';
				$msg = 'Direct link key is being used in Rest API';
				break;
			
			case '404We cannot ':
				$id  = '9';
				$msg = 'Requested URL not found (404)';
				break;
			
			case '500Time out.':
				$id  = '10';
				$msg = 'Requested URL timed out';
				break;
			
			default:
				$id  = '11';
				$msg = 'Unexpected error returned by API ('.$id_in.': '.$msg_in.')';
		}
		
		$r['id']  = $id;
		$r['msg'] = $msg;
		
		return $r;
	}
	
	public function run()
	{
		if ($this->token && $_GET['page2images_callback_var_token'] != $this->token)
		{
			return false;
		}
		else
		{
			$post_data = $_POST["result"];
			$json_data = json_decode($post_data);
			if ($json_data->status == 'finished')
			{
				$r['status']    = 'OK';
				$r['duration']  = $json_data->duration;
				$r['remaining'] = $json_data->left_calls;
				$r['request']   = urldecode($json_data->ori_url);
				$r['url']       = urldecode($json_data->image_url);
			}
			else
			{
				$res = $this->processApiError($json_data->errno,$json_data->msg);
				
				$r['status']   = 'ERR';
				$r['error']    = $res['id'];
				$r['message']  = $res['msg'];
				$r['duration'] = $json_data->duration;
				$r['request']  = urldecode($json_data->ori_url);
			}
			
			unset($_GET['page2images_callback_var_token']);
			
			if ($_GET)
			{
				foreach ($_GET as $k => $v)
				{
					if (substr($k,0,25) == 'page2images_callback_var_')
					{
						$passback[substr($k,25)] = $v;
					}
				}
				if ($passback)
				{
					$r['passback'] = (object) $passback;
				}
			}
			
			return (object) $r;
		}
	}
	
	public function setToken($token)
	{
		$this->token = $token;
	}
}
