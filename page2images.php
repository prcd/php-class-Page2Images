<?php

class page2image
{
	private $api_timeout = 120;
	private $api_url     = 'http://api.page2images.com/restfullink';
	private $params      = array();
	
	function __construct($options_array = NULL)
	{
		if ($options_array)
		{
			$this->setOptions($options_array);
		}
	}
	
	private function checkRequiredParams()
	{
		// check required properties have been set
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
			throw new Exception ('Call cannot be made, required properties are missing ('.$list.')');
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
	
	public function call($options_array = NULL, $reset = false)
	{
		if ($options_array)
		{
			$this->setOptions($options_array, $reset);
		}
		
		$this->checkRequiredParams();

		// the code up to the end of this method is based on the sample code from http://www.page2images.com/REST-Web-Service-Interface
		$loop_flag  = true;
		$start_time = time();
		set_time_limit($this->api_timeout+10);
		
		while ($loop_flag)
		{
			// We need to call the API until we get the screenshot or error message
			$response = $this->curl();
			
			if (empty($response))
			{
				$loop_flag = false;
				$error = 'The page2images API did not respond';
				break;
			}
			else
			{
				$json_data = json_decode($response);
				
				if (empty($json_data->status))
				{
					$loop_flag = false;
					$error = 'The page2images API returned incomplete data (no status)';
					break;
				}
			}
			
			switch ($json_data->status)
			{
				case 'error':
					$loop_flag = false;
					$error = 'The page2images API returned error #'.$json_data->errno.': '.$json_data->msg;
					break;
				case 'finished':
					$url = $json_data->image_url;
					$loop_flag = false;
					break;
				case 'processing':
				default:
					if ((time() - $start_time) > $this->api_timeout)
					{
						$loop_flag = false;
						$error = 'Request timeout after '.$this->api_timeout.' seconds';
					}
					else
					{
						sleep(2);
					}
					break;
			}
		}
		
		if ($error)
		{
			$return->status = 'ERR';
			$return->error  = $error;
		}
		else
		{
			$return->status = 'OK';
			$return->url    = $url;
		}
		
		return $return;
	}
	
	public function reset()
	{
		// store the API key
		$key = $this->params['p2i_key'];
		
		// re-declare the params array with only the API key
		$this->params = array('p2i_key' => $key);
	}
	
	public function setOptions($options_array, $reset = false)
	{
		if ($reset == true)
		{
			$this->reset();
		}
		
		$a = $options_array;
		
		// check incoming
		if (!is_array($a))
		{
			throw new Exception ('Input must be array for '.__METHOD__);
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
			
			throw new Exception ('Input array contained unknown keys ('.$list.') for '.__METHOD__);
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
				throw new Exception ('Input must be formatted WxH where W and H are non-negative integers for '.__METHOD__);
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
				throw new Exception ('Input must be formatted WxH where W and H are non-negative integers for '.__METHOD__);
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
			throw new Exception ('Wait must be an integer between 0 and 25 (strings accepted)');
		}
		
		$this->params['p2i_wait'] = (string) $i;
	}
	
	public function setQuality($quality)
	{
		$q = floor($quality);
		
		if ($q < 70 || $q > 95)
		{
			throw new Exception ('Quality must be 70-85 for png or 80-95 for jpg');
		}
		
		$this->params['p2i_quality'] = $q;
	}
}
