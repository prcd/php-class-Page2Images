<?php

/*
 *  Put the URL for your handler file on line 16
 *  Use any random string as your callback token and enter it at line 17
 *  Put your API key at line 22
 *  Before running this file make sure you have prepared handler.php
 */


require '../class/page2images.php';

$p2i = new page2images();

$callback = array(
	'url'      => 'http://example.com/handler.php',
	'token'    => 'your_callback_token',
	'time'     => date('r')
);

$params = array(
	'api_key'  => 'your_api_key_here',
	'screen'   => '1366x768',
	'callback' => $callback 
);

// these are the URLs we would like screen grabs of
// the key can be used as a unique ID to identify the request when page2images does its callback
$request = array(
	'1' => 'http://www.google.com',
	'2' => 'http://www.theguardian.com',
	'3' => 'http://www.youtube.com',
	'4' => 'http://www.ebay.com',
	'5' => 'http://www.page2images.com'
);

try
{
	// set the parameters but don't make a call yet - the parameters will be used for each call made
	$p2i->setParams($params);
		
	// loop through the URLs, setting each one as we go, update the callback ID and make a call
	foreach ($request as $id => $url)
	{
		$p2i->setUrl($url);
		$p2i->setCallback(array('id' => $id));
		$res = $p2i->call();
		if ($res->status != 'OK')
		{
			$error        = $res->error;                      // the error id
			$message      = $res->err_msg;                    // message explaining the error
			$request      = $res->request;                    // the URL page2images was asked to grab
			$api_response = print_r($res->api_response,true); // the original API response - may help with debugging
			
			// if the request is not successful, handle the error here
		}
	}
}
catch(Exception $e)
{
	exit($e->getMessage());
}

echo 'Success! page2images will be in touch soon with the results.';
