<?php

class page2images
{
	private $api_timeout         = NULL;
	private $api_timeout_default = '120';
	private $api_url             = 'http://api.page2images.com/restfullink';
	private $callback            = array();
	private $params              = array();
	
	function __construct($params_array = NULL)
	{
		$this->api_timeout = $this->api_timeout_default;
		
		if ($params_array)
		{
			$this->setParams($params_array);
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
			
			if (!$format && ($quality < 70 || $quality > 85))
			{
				throw new Exception ('Image quality must be between 70 and 85 for png (default format)');
			}
			else if ($format == 'png' && ($quality < 70 || $quality > 85))
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
		if (!array_key_exists('url',$this->callback))
		{
			throw new Exception ('Callback array must include a key named \'url\'');
		}
		else if ($this->callback['url'] == '' || !is_string($this->callback['url']))
		{
			throw new Exception ('Callback value for \'url\' must be a string');
		}
		
		$this->params['p2i_callback'] = $this->callback['url'];
		
		foreach ($this->callback as $k => $v)
		{
			if ($k != 'url')
			{
				$vars[] = urlencode('page2images_callback_var_'.$k).'='.urlencode($v);
			}
		}
		
		if ($vars)
		{
			// append url with & or ?
			$append = (strstr($this->callback['url'],'?')) ? '&' : '?';
			
			$this->params['p2i_callback'] .= $append.implode('&',$vars);
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
			
			case '403Account ha':
				$id  = '7';
				$msg = 'Account has expired';
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
	
	public function call($params_array = NULL, $clear = false)
	{
		if ($params_array)
		{
			$this->setParams($params_array, $clear);
		}
		else if ($clear)
		{
			$this->clear($clear);
		}
		
		$this->checkParams();
		
		if (count($this->callback) > 0)
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
				$err_id  = '1';
				$err_msg = 'API did not respond';
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
						$res = $this->processApiError($json_data->errno,$json_data->msg);
						$err_id   = $res['id'];
						$err_msg  = $res['msg'];
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
							$err_id  = '4';
							$err_msg = 'API did not return an image within the timeout limit ('.$this->api_timeout.' seconds)';
							$finished = true;
						}
						else
						{
							sleep(2);
						}
						break;
					
					default:
						if ($json_data->status == '')
						{
							$err_id  = '2';
							$err_msg = 'API did not return a status';
						}
						else
						{
							$err_id  = '3';
							$err_msg = 'API returned an unknown status ('.$json_data->status.')';
						}
						$finished = true;
				}
			}
		}
		while(!$finished);
	
		if ($err_id)
		{
			$r['status']       = 'ERR';
			$r['error']        = $err_id;
			$r['message']      = $err_msg;
			$r['request']      = $json_data->ori_url;
			$r['api_response'] = $json_data;
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
	
		return (object) $r;
	}
	
	public function clear($action = true)
	{
		$allow = array(true,'params','callback','all_params','all_callback','all');
		
		if (!in_array($action,$allow,true))
		{
			throw new Exception ('Unexpected action requested for clear ('.$action.')');
		}
		
		if ($action === 'all')
		{
			$this->callback = array();
			$this->params   = $this->params_default;
		}
		else if ($action === 'all_callback')
		{
			$this->callback = array();
		}
		else if ($action === 'all_params')
		{
			$this->params = $this->params_default;
		}
		else
		{
			// clear parameters except for key
			if ($action === true || $action === 'params')
			{
				if ($this->params['p2i_key'])
				{
					// get the API key
					$key = $this->params['p2i_key'];
					
					// re-declare the params array with default values plus the API key
					$this->params = $this->params_default;
					$this->params['p2i_key'] = $key;
				}
				else
				{
					$this->params = $this->params_default;
				}
			}
			
			// clear callback vars except for url and token
			if ($action === true || $action === 'callback')
			{
				if ($this->callback['url'])
				{
					$save['url'] = $this->callback['url'];
				}
				if ($this->callback['token'])
				{
					$save['token'] = $this->callback['token'];
				}
				
				$this->callback = array();
				
				if ($save)
				{
					$this->callback = $save;
				}
			}
		}
	}
	
	public function setParams($params_array, $clear = false)
	{
		if ($clear)
		{
			$this->clear($clear);
		}
		
		$a = $params_array;
		
		// check incoming
		if (!is_array($a))
		{
			throw new Exception ('Params input must be array');
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
		if (array_key_exists('callback',$a))
		{
			$this->setCallback($a['callback']);
			unset($a['callback']);
		}
		if (array_key_exists('device_id',$a))
		{
			$this->setDeviceId($a['device_id']);
			unset($a['device_id']);
		}
		if (array_key_exists('format',$a))
		{
			$this->setFormat($a['format']);
			unset($a['format']);
		}
		if (array_key_exists('full_page',$a))
		{
			$this->setFullPage($a['full_page']);
			unset($a['full_page']);
		}
		if (array_key_exists('js',$a))
		{
			$this->setJs($a['js']);
			unset($a['js']);
		}
		if (array_key_exists('quality',$a))
		{
			$this->setQuality($a['quality']);
			unset($a['quality']);
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
		if (array_key_exists('url',$a))
		{
			$this->setUrl($a['url']);
			unset($a['url']);
		}
		if (array_key_exists('wait',$a))
		{
			$this->setWait($a['wait']);
			unset($a['wait']);
		}
		
		// any left over params?
		if (count($a) > 0)
		{
			// get each key left in the array
			foreach ($a as $k => $v)
			{
				$list[] = $k;
			}
			$list = implode(', ',$list);
			
			throw new Exception ('Params array contained unknown keys ('.$list.')');
		}
	}
	
	public function setApiKey($api_key)
	{
		if ($api_key === NULL)
		{
			unset($this->params['p2i_key']);
		}
		else
		{
			if (!is_string($api_key))
			{
				throw new Exception ('API key must be a string');
			}
			
			$this->params['p2i_key'] = $api_key;
		}
	}
	
	public function setApiTimeout($seconds_to_timeout)
	{
		if ($seconds_to_timeout === NULL)
		{
			$this->api_timeout = $this->api_timeout_default;
		}
		else
		{
			$s = floor($seconds_to_timeout);
			
			if ($s < 10 || $s > 180)
			{
				throw new Exception ('API timeout must be between 10 and 180 seconds');
			}
			
			$this->api_timeout = $s;
		}
	}
	
	public function setCallback($callback_array)
	{
		if ($callback_array === NULL)
		{
			$this->callback = array();
		}
		else
		{
			if (!is_array($callback_array))
			{
				throw new Exception ('Callback input must be array');
			}
			
			foreach ($callback_array as $k => $v)
			{
				if (!(is_string($k) || is_numeric($k)) || !(is_string($v) || is_numeric($v)))
				{
					throw new Exception ('Callback array keys and variables must be strings, integers or floats');
				}
				else if ($k == '')
				{
					throw new Exception ('Callback keys cannot be empty');
				}
				
				$this->callback[$k] = $v;
			}
		}
	}
	
	public function setDeviceId($id)
	{
		if ($id === NULL)
		{
			unset($this->params['p2i_device']);
		}
		else
		{
			$valid = array('0','1','2','3','4','5','6','7','8');
			if (!in_array($id,$valid))
			{
				throw new Exception ('Device ID must be an integer between 0 and 8 (strings accepted)');
			}
			$this->params['p2i_device'] = (string) $id;
		}
	}
	
	public function setFormat($format)
	{
		if ($format === NULL)
		{
			unset($this->params['p2i_imageformat']);
		}
		else
		{
			$f = strtolower($format);
			
			$valid = array('jpg','pdf','png');
			if (!in_array($f,$valid))
			{
				throw new Exception ('Format must be jpg, pdf or png');
			}
			$this->params['p2i_imageformat'] = $f;
		}
	}
	
	public function setFullPage($bool)
	{
		if ($bool === NULL)
		{
			unset($this->params['p2i_fullpage']);
		}
		else
		{
			if ($bool === false || $bool == 'false')
			{
				$this->params['p2i_fullpage'] = '0';
			}
			else if ($bool == '1' || $bool === true || $bool == 'true')
			{
				$this->params['p2i_fullpage'] = '1';
			}
			else
			{
				throw new Exception ('Full page value must be boolean or string (true, false, 1, 0)');
			}
		}
	}

	public function setJs($bool)
	{
		if ($bool === NULL)
		{
			unset($this->params['p2i_js']);
		}
		else
		{
			if ($bool == false || $bool == 'false')
			{
				$this->params['p2i_js'] = '0';
			}
			else if ($bool == '1' || $bool === true || $bool == 'true')
			{
				$this->params['p2i_js'] = '1';
			}
			else
			{
				throw new Exception ('JS value must be boolean or string (true, false, 1, 0)');
			}
		}
	}
	
	public function setQuality($quality)
	{
		if ($quality === NULL)
		{
			unset($this->params['p2i_quality']);
		}
		else
		{
			$q = floor($quality);
			
			if ($q < 70 || $q > 95)
			{
				throw new Exception ('Quality must be integer 70-85 for png or 80-95 for jpg (strings accepted)');
			}
			
			$this->params['p2i_quality'] = $q;
		}
	}
	
	public function setRefresh($bool)
	{
		if ($bool === NULL)
		{
			unset($this->params['p2i_refresh']);
		}
		else
		{
			if ($bool == false || $bool == 'false')
			{
				$this->params['p2i_refresh'] = '0';
			}
			else if ($bool == '1' || $bool === true || $bool == 'true')
			{
				$this->params['p2i_refresh'] = '1';
			}
			else
			{
				throw new Exception ('Refresh value must be boolean or string (true, false, 1, 0)');
			}
		}
	}
	
	public function setScreen($width_height)
	{
		if ($width_height === NULL)
		{
			unset($this->params['p2i_screen']);
		}
		else
		{
			if ($width_height == '0x0')
			{
				throw new Exception ('Screen width and height cannot both be 0');
			}
			else
			{
				$d = explode('x',$width_height);
				
				if (count($d) != 2 || $d[0] < 0 || $d[1] < 0)
				{
					throw new Exception ('Screen must be formatted WxH where W and H are positive integers');
				}
			
				$this->params['p2i_screen'] = $width_height;
			}
		}
	}

	public function setSize($width_height)
	{
		if ($width_height === NULL)
		{
			unset($this->params['p2i_size']);
		}
		else
		{
			if ($width_height == '0x0')
			{
				throw new Exception ('Size width and height cannot both be 0');
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
	}
	
	public function setUrl($url)
	{
		if ($url === NULL)
		{
			unset($this->params['p2i_url']);
		}
		else
		{
			if (!is_string($url))
			{
				throw new Exception ('URL must be a string');
			}
			
			$this->params['p2i_url'] = $url;
		}
	}

	public function setWait($seconds)
	{
		if ($seconds === NULL)
		{
			unset($this->params['p2i_wait']);
		}
		else
		{
			$i = floor($seconds);
			
			if (preg_match("/[^\d]/",$seconds) || ($i < 0 || $i > 25))
			{
				throw new Exception ('Wait must be integer between 0 and 25 (strings accepted)');
			}
			
			$this->params['p2i_wait'] = (string) $i;
		}
	}
}
