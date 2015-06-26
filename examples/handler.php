<?php


/*
 *  Set the email address you would like the response sent to at line 46
 *  If you declared a token in your callback array, enter it at line 12
 */


require '../class/page2imagesHandler.php';

$p2iHandler = new page2imagesHandler('your_callback_token');// if you are using a token, declare it here

if ($res = $p2iHandler->run())
{
	// the following details will be returned whether the request was successful or not
	$line[] = 'page2images has something for you!';
	$line[] = '';
	$line[] = 'Request:   '.$res->request;            // the URL page2images grabbed (or tried to grab)
	$line[] = '';
	$line[] = 'Time:      '.$res->passback->time;     // you can add passback data using the setCallback method
	$line[] = 'ID:        '.$res->passback->id;       // this can help you process data when it is returned
	$line[] = 'Anything:  '.$res->passback->anything; 
	$line[] = '';
	
	if ($res->status == 'OK')
	{
		$line[] = 'Everything worked perfectly';
		$line[] = '';
		$line[] = 'URL:       '.$res->url;        // where your image can be found (will be saved for 24 hours)
		$line[] = 'Duration:  '.$res->duration;   // how long the request took to process
		$line[] = 'Remaining: '.$res->remaining;  // page2images monthly credits remaining
	}
	else
	{
		$line[] = 'Something went wrong';
		$line[] = '';
		$line[] = 'Error #    '.$res->error;
		$line[] = 'Message    '.$res->message;
		$line[] = '';
		$line[] = print_r($res->api_response,true); // include the original API response as it may help with debugging
	}
			
	$message = implode("\r\n",$line);
	
	mail('hello@example.com','page2images callback',$message);
}
else
{
	echo 'This page handles callbacks from the page2images.com API and requires a valid token';
}
