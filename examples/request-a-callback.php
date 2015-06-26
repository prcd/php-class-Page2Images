<?php

/*
 *  Put the URL for your handler file on line 16
 *  Use any random string as your callback token and enter it at line 17
 *  Put your API key at line 24
 *  Before running this file make sure you have prepared handler.php
 */


require '../class/page2images.php';

$p2i = new page2images();

$callback = array(
	'url'      => 'http://example.com/handler.php', // the URL page2images should respond to
	'token'    => 'your_callback_token',            // any random string, also use it in the callback handler
	'time'     => date('r'),                        // you can add any extra data you like, page2images will pass it back
	'id'       => '78951',                          // extra data can help you process the returned results
	'anything' => 'you like'
);

$params = array(
	'api_key'  => 'your_api_key_here',
	'url'      => 'http://www.theguardian.com',
	'screen'   => '1366x768',
	'callback' => $callback                         // add the callback array to the parameters array
);

try
{	
	$res = $p2i->call($params);
}
catch(Exception $e)
{
	exit($e->getMessage());
}


if ($res->status == 'OK')
{
	echo 'Success! page2images will be in touch soon with the result.';
}
else
{
	echo 'Error #'.$res->error.': '.$res->message;
}