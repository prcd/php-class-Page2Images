<?php

class page2images
{
	private $api_timeout    = 120;
	private $api_url        = 'http://api.page2images.com/restfullink';
	private $params         = array();
	private $callback_token = NULL;
	private $callback_url   = NULL;
	private $callback_vars  = array();
	
	function __construct($options_array = NULL)
	{
		if ($options_array)
		{
			$this->setOptions($options_array);
		}
	}
	
	private function checkParams()
	{
		// check required parameters have been set
		if (!$this->params['p2i_key'])
		{
			$missing[] = 'API key';
		}
		if (!$this->params['p2i_url'])
		{
			$missing[] = 'URL';
		}
		
		// was anything missing?
		if ($missing)
		{
			$list = implode(', ',$missing);
			throw new Exception ('Call cannot be made, required parameters are missing ('.$list.')');
		}
		
		// if image quality is set, check it is within required ranges
		if ($this->params['p2i_quality'])
		{
			$format  = $this->params['p2i_imageformat'];
			$quality = $this->params['p2i_quality'];
			
			if (($format == 'png' || !$format) && ($quality < 70 || $quality > 85))
			{
				throw new Exception ('Image quality must be between 70 and 85 for png');
			}
			else if ($format == 'jpg' && ($quality < 80 || $quality > 95))
			{
				throw new Exception ('Image quality must be between 80 and 95 for jpg');
			}
		}
	}
	
	private function curl()
	{
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $this->api_url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->params));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		
		$data = curl_exec($ch);
		curl_close($ch);
		
		return $data;
	}
	
	private function prepareCallback()
	{
		$this->params['p2i_callback'] = $this->callback_url;
		
		if ($this->callback_token)
		{
			$add[] = 'page2images_callback_token='.urlencode($this->callback_token);
		}
		
		if ($this->callback_vars)
		{
			foreach ($this->callback_vars as $k => $v)
			{
				$add[] = urlencode($k).'='.urlencode($v);
			}
		}
		
		if ($add)
		{
			$this->params['p2i_callback'] .= '?'.implode('&',$add);
		}
	}
	
	public function call($options_array = NULL, $clear = false)
	{
		if ($options_array)
		{
			$this->setOptions($options_array, $clear);
		}
		
		$this->checkParams();
		
		if ($this->callback_url)
		{
			$this->prepareCallback();
		}
		else
		{
			// if callback is not being used, log start time and adjust timeout limit
			$start_time = time();
			set_time_limit($this->api_timeout+10);
		}
		
		do
		{
			// We need to call the API until we get the screenshot or error message
			$response = $this->curl();
			
			if (empty($response))
			{
				$error = 'The page2images API did not respond';
				$finished = true;
				break;
			}
			else
			{
				// API response could include the following,
				//   status               - The status of the request (finished/processing/error)
				//   estimated_need_time  - Estimated time needed to complete request
				//   url                  - The location of the image that has been generated (valid for 24 hours)
				//   duration             - How long the request took to process?
				//   left_calls           - How many calls left in current billing period
				//   ori_url              - URL sent to page2images
				//   errno                - Error number
				//   msg                  - A description of the error
				$json_data = json_decode($response);
				
				switch ($json_data->status)
				{
					case 'error':
						$error = 'The page2images API returned error #'.$json_data->errno.': '.$json_data->msg;
						$finished = true;
						break;
					case 'finished':
						$finished = true;
						break;
					case 'processing':
						if ($this->params['p2i_callback'])
						{
							// no need to loop if callback is being used
							$finished = true;
						}
						else if ((time() - $start_time) > $this->api_timeout)
						{
							$error = 'Request timeout after '.$this->api_timeout.' seconds';
							$finished = true;
						}
						else
						{
							sleep(2);
						}
						break;
					case '':
						$error = 'The page2images API returned data but no status';
						$finished = true;
						break;
					default:
						$error = 'Unexpected status from page2images API: '.$json_data->status;
						$finished = true;
				}
			}
		}
		while(!$finished);
	
		if ($error)
		{
			$r['status']   = 'ERR';
			$r['error']    = $error;
			$r['request']  = $json_data->ori_url;
		}
		else
		{
			$r['status']     = 'OK';
			$r['callback']   = $this->params['p2i_callback'] ? '1' : '0';
			$r['duration']   = $json_data->duration;
			$r['remaining']  = $json_data->left_calls;
			$r['request']    = $json_data->ori_url;
			$r['url']        = $json_data->image_url;
		}
	
		return $r;
	}
	
	public function clearOptions()
	{
		// get the API key
		$key = $this->params['p2i_key'];
		
		// re-declare the params array with only the API key
		$this->params = array('p2i_key' => $key);
		
		// clear callback vars (URL and token are preserved)
		$this->callback_vars = array();
	}
	
	public function setOptions($options_array, $clear = false)
	{
		if ($clear == true)
		{
			$this->clearOptions();
		}
		
		$a = $options_array;
		
		// check incoming
		if (!is_array($a))
		{
			throw new Exception ('Options input must be array');
		}
		
		// assign variables
		if (array_key_exists('api_key',$a))
		{
			$this->setApiKey($a['api_key']);
			unset($a['api_key']);
		}
		if (array_key_exists('api_timeout',$a))
		{
			$this->setApiTimeout($a['api_timeout']);
			unset($a['api_timeout']);
		}
		if (array_key_exists('callback_token',$a))
		{
			$this->setCallbackToken($a['callback_token']);
			unset($a['callback_token']);
		}
		if (array_key_exists('callback_url',$a))
		{
			$this->setCallbackUrl($a['callback_url']);
			unset($a['callback_url']);
		}
		if (array_key_exists('callback_vars',$a))
		{
			$this->setCallbackVars($a['callback_vars']);
			unset($a['callback_vars']);
		}
		if (array_key_exists('device',$a))
		{
			$this->setDevice($a['device']);
			unset($a['device']);
		}
		if (array_key_exists('image_format',$a))
		{
			$this->setImageFormat($a['image_format']);
			unset($a['image_format']);
		}
		if (array_key_exists('js',$a))
		{
			$this->setJs($a['js']);
			unset($a['js']);
		}
		if (array_key_exists('refresh',$a))
		{
			$this->setRefresh($a['refresh']);
			unset($a['refresh']);
		}
		if (array_key_exists('screen',$a))
		{
			$this->setScreen($a['screen']);
			unset($a['screen']);
		}
		if (array_key_exists('size',$a))
		{
			$this->setSize($a['size']);
			unset($a['size']);
		}
		if (array_key_exists('quality',$a))
		{
			$this->setQuality($a['quality']);
			unset($a['quality']);
		}
		if (array_key_exists('wait',$a))
		{
			$this->setWait($a['wait']);
			unset($a['wait']);
		}
		if (array_key_exists('url',$a))
		{
			$this->setUrl($a['url']);
			unset($a['url']);
		}
		
		// any left over options?
		if (count($a) > 0)
		{
			// get each key left in the array
			foreach ($a as $k => $v)
			{
				$list[] = $k;
			}
			$list = implode(', ',$list);
			
			throw new Exception ('Options input array contained unknown keys ('.$list.')');
		}
	}
	
	public function setApiKey($api_key)
	{
		if (!is_string($api_key))
		{
			throw new Exception ('API key must be a string');
		}
		
		$this->params['p2i_key'] = $api_key;
	}
	
	public function setApiTimeout($seconds_to_timeout)
	{
		$s = floor($seconds_to_timeout);
		
		if ($s < 10 || $s > 180)
		{
			throw new Exception ('API timeout must be between 10 and 180 seconds');
		}
		
		$this->api_timeout = $s;
	}
	
	public function setCallbackUrl($url)
	{
		if (!is_string($url))
		{
			throw new Exception ('Callback URL must be a string');
		}
		
		$this->callback_url = $url;
	}
	
	public function setCallbackToken($token)
	{
		if (!is_string($token))
		{
			throw new Exception ('Callback token must be a string');
		}
		
		$this->callback_token = $token;
	}
	
	public function setCallbackVars($callback_vars)
	{
		if (!is_array($callback_vars))
		{
			throw new Exception ('Callback vars must be array');
		}
		
		foreach ($callback_vars as $k => $v)
		{
			if (!is_string($k) || !is_string($v))
			{
				throw new Exception ('Callback array keys and variables must be strings');
			}
			else if ($k == '')
			{
				throw new Exception ('Callback keys cannot be empty');
			}
			$this->callback_vars[$k] = $v;
		}
	}
	
	public function setDevice($device_id)
	{
		$valid = array('0','1','2','3','4','5','6','7','8');
		if (!in_array($device_id,$valid))
		{
			throw new Exception ('Device ID must be an integer between 0 and 8 (strings accepted)');
		}
		$this->params['p2i_device'] = (string) $device_id;
	}
	
	public function setImageFormat($format)
	{
		$f = strtolower($format);
		
		$valid = array('jpg','pdf','png');
		if (!in_array($f,$valid))
		{
			throw new Exception ('Image format must be jpg, pdf or png');
		}
		$this->params['p2i_imageformat'] = $f;
	}
	
	public function setJs($bool)
	{
		if ($bool == false || $bool == 'false')
		{
			$this->params['p2i_js'] == '0';
		}
		else if ($bool == '1' || $bool === true || $bool == 'true')
		{
			$this->params['p2i_js'] == '1';
		}
		else
		{
			throw new Exception ('JS must be boolean or string (true, false, 1, 0)');
		}
	}

	public function setRefresh($bool)
	{
		if ($bool == false || $bool == 'false')
		{
			$this->params['p2i_refresh'] == '0';
		}
		else if ($bool == '1' || $bool === true || $bool == 'true')
		{
			$this->params['p2i_refresh'] == '1';
		}
		else
		{
			throw new Exception ('Refresh must be boolean or string (true, false, 1, 0)');
		}
	}

	public function setScreen($width_height)
	{
		if ($width_height == '0x0')
		{
			throw new Exception ('Width and height cannot both be 0 for '.__METHOD__);
		}
		else
		{
			$d = explode('x',$width_height);
			
			if (count($d) != 2 || preg_match("/[^\d]/",$d[0]) || preg_match("/[^\d]/",$d[1]) )
			{
				throw new Exception ('Screen must be formatted WxH where W and H are non-negative integers');
			}
		
			$this->params['p2i_screen'] = $width_height;
		}
	}

	public function setSize($width_height)
	{
		if ($width_height == '0x0')
		{
			throw new Exception ('Width and height cannot both be 0 for '.__METHOD__);
		}
		else
		{
			$d = explode('x',$width_height);
			
			if (count($d) != 2 || preg_match("/[^\d]/",$d[0]) || preg_match("/[^\d]/",$d[1]) )
			{
				throw new Exception ('Size must be formatted WxH where W and H are non-negative integers');
			}
		
			$this->params['p2i_size'] = $width_height;
		}
	}
	
	public function setUrl($url)
	{
		if (!is_string($url))
		{
			throw new Exception ('URL must be a string');
		}
		
		$this->params['p2i_url'] = $url;
	}
	
	public function setWait($seconds)
	{
		$i = floor($seconds);
		
		if (preg_match("/[^\d]/",$seconds) || ($i < 0 || $i > 25))
		{
			throw new Exception ('Wait must be integer between 0 and 25 (strings accepted)');
		}
		
		$this->params['p2i_wait'] = (string) $i;
	}
	
	public function setQuality($quality)
	{
		$q = floor($quality);
		
		if ($q < 70 || $q > 95)
		{
			throw new Exception ('Quality must be integer 70-85 for png or 80-95 for jpg (strings accepted)');
		}
		
		$this->params['p2i_quality'] = $q;
	}
}

class page2imagesCallbackHandler
{	
	private $token = NULL;
	
	function __construct($token = NULL)
	{
		if ($token)
		{
			$this->token = $token;
		}
	}
	
	public function run()
	{
		if ($this->token && $_GET['page2images_callback_token'] != $this->token)
		{
			return false;
		}
		else
		{
			$post_data = $_POST["result"];
			$json_data = json_decode($post_data);
			switch ($json_data->status)
			{
				case "error":
					$r['status']   = 'ERR';
					$r['duration'] = $json_data->duration;
					$r['error']    = '#'.$json_data->errno.': '.$json_data->msg;
					$r['request']  = urldecode($json_data->ori_url);
					break;
				case "finished":
					$r['status']    = 'OK';
					$r['duration']  = $json_data->duration;
					$r['remaining'] = $json_data->left_calls;
					$r['request']   = urldecode($json_data->ori_url);
					$r['url']       = urldecode($json_data->image_url);
					break;
				default:
					$r['status']   = 'ERR';
					$r['error']    = 'Unrecoginsed data receievd by page2images handler';
					break;
			}
			
			if ($_GET)
			{
				unset($_GET['page2images_callback_token']);
				
				foreach ($_GET as $k => $v)
				{
					$r['passback'][$k] = $v;
				}
			}
			
			return $r;
		}
	}
}
