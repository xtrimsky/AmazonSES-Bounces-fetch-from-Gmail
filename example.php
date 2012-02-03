<?php
require_once("AmazonSESBounces.php");


$bounces = new AmazonSESBounces();
$bounces->setGmailCredentials('anaddress@gmail.com', 'coolpassword');
$bounces->setLabel('Amazon Bounces'); //don't set if its inbox
if($bounces->connect()){
	
	$emails = $bounces->getEmailsThatBounced();
	/*
	 * A cool script
	 */
	print_r($emails);
	
	$bounces->deleteEmailsFound();
	$bounces->end();
}else{
	
	print_r($bounces->getErrors());
	
}
