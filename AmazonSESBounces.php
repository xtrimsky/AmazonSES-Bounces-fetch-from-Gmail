<?php

class AmazonSESBounces{
	
	private $c = false; //connection
	private $username;
	private $password;
	private $label = 'INBOX';
	private $errors;
	private $messagesFound = array();
	
	/*
	 * $username = emailaddress you use on gmail, can be google apps
	 * $password = you're email address password
	 */
	function setGmailCredentials($username, $password){
		$this->username = $username;
		$this->password = $password;
	}
	
	/*
	 * Set Label from where the Gmail messages are parsed, default is INBOX
	 * $label = label name, nothing special, example: Amazon Bounces
	 */
	function setLabel($label){
		$this->label = $label;
	}
	
	/*
	 * connects to the imap after username and password have been set
	 * return false if fails
	 */
	function connect(){
		if(empty($this->username) || empty($this->password)){
			$this->errors[] = 'No username or password defined! Use setGmailCredentials!';
			return false;
		}
		
		$imapaddress = "{imap.gmail.com:993/ssl/novalidate-cert}";
		
		$this->c = @imap_open($imapaddress.$this->label, $this->username, $this->password, NULL, 1);
		
		if($this->c){return $this->c;}
		
		$this->errors = imap_errors();
		return false;
	}
	
	/*
	 * Fetch any errors that could have occured
	 */
	function getErrors(){
		return $this->errors;
	}
	
	/*
	 * returns a list of all emails that bounced
	 */
	function getEmailsThatBounced(){
		if(!$this->c){return false;}
		
		$emails = array();
		
		$headers = @imap_headers($this->c);
		$max_message_count = sizeof($headers);
		
		$count = 1;
		while($count <= $max_message_count){
			$headerinfo = @imap_headerinfo($this->c, $count);
			$from = $headerinfo->fromaddress;
			
			if($from == 'MAILER-DAEMON@email-bounces.amazonses.com' || $from == 'complaints@email-abuse.amazonses.com'){
				$body = @imap_body($this->c, $count);
				
				$email = $this->parseBody($body);
				if($email){
					$emails[] = $email;
					$this->messagesFound[] = $count;
				}
			}
			
			$count++;
		}
		
		return $emails;
	}
	
	function parseBody($body){
		$lines = explode("\r\n",$body);
		
		foreach($lines as $line){
			$lookfor = 'Final-Recipient: ';
			if(substr($line,0,strlen($lookfor)) == $lookfor){
				$email = substr($line,strlen($lookfor));
				$email = $this->cleanEmail($email);
				
				if($this->isEmailValid($email)){ return $email;}
			}
			
			$lookfor = 'X-HmXmrOriginalRecipient: ';
			if(substr($line,0,strlen($lookfor)) == $lookfor){
				$email = substr($line,strlen($lookfor));
				$email = $this->cleanEmail($email);
				
				if($this->isEmailValid($email)){ return $email;}
			}
			
			$lookfor = 'Original-Rcpt-To: ';
			if(substr($line,0,strlen($lookfor)) == $lookfor){
				$email = substr($line,strlen($lookfor));
				$email = $this->cleanEmail($email);
				
				if($this->isEmailValid($email)){ return $email;}
			}
			
			$lookfor = 'Original-Recipient: ';
			if(substr($line,0,strlen($lookfor)) == $lookfor){
				$email = substr($line,strlen($lookfor));
				$email = $this->cleanEmail($email);
				
				if($this->isEmailValid($email)){ return $email;}
			}
		}
		
		return false;
	}
	
	/*
	 * checks if email that was found is real
	 */
	function isEmailValid($email){
		return	$email &&
				filter_var($email, FILTER_VALIDATE_EMAIL) &&		//valid email
				strpos($email,'redacted@') !== 0;					//email has been redacted, wrong email
	}
	
	/*
	 * makes sure email is well formated
	 */
	function cleanEmail($email){
		$email = str_ireplace(array('rfc822;','<','>'),'',$email);
		$email = trim($email);
		
		return $email;
	}
	
	/*
	 * deletes all emails that have been previously found
	 */
	function deleteEmailsFound(){
		if(!$this->c){return false;}
		
		foreach($this->messagesFound as $message_id){
			$this->deleteEmail($message_id);
		}
	}
	
	function deleteEmail($message_id){
		if(!$this->c){return false;}
		
		@imap_delete($this->c, $message_id);
	}
	
	/*
	 * call required to save all the changes
	 * expunges messages and closes the connection
	 */
	function end(){
		@imap_expunge($this->c);
		imap_close($this->c);
	}
}