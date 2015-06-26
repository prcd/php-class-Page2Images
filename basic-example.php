<?php


/* 
 *  Sign up at http://www.page2images.com to get your API key and enter it at line 16
 *  Enter the URL you would like screen grabbed at line 17
 *  Load this file in a browser to see the image page2images returns
 */


require 'class/page2images.php';

$p2i = new page2images();

$params = array(
	'api_key' => 'your_api_key_here',
	'url'     => 'http://www.google.com'
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
	echo '<img src="'.$res->url.'" />';
}
else
{
	echo 'Error #'.$res->error.': '.$res->message;
}