<?php

abstract class _Email {

	private $_recipients;
	private $_subject;
	private $_body;
	private $_header=array();
	private $_multi=NULL;
	
	function __construct($sender=NULL){
		if(is_null($sender)){
			$sender->email = Config::get('system.email_address');
			$sender->name = Config::get('system.email_name', $sender->email);
		}
		$this->from($sender->email, $sender->name);
	}
	
	function header(){
		$this->_header['User-Agent']='LIMS';				
		$this->_header['Date']=$this->get_date();
		$this->_header['X-Mailer']='LIMS';		
		$this->_header['X-Priority']='3 (Normal)';
		$this->_header['Message-ID']=$this->get_message_id();		
		$this->_header['Mime-Version']='1.0';
		if($this->_multi){
			$this->_header['Content-Type']='multipart/alternative; boundary='.$this->_multi.'';   
		}else{
			$this->_header['Content-Transfer-Encoding']='8bit';
			$this->_header['Content-Type']='text/plain; charset="UTF-8"';   
		}

		foreach($this->_header as $k=>$v){
			$header.= "$k: $v\n";
		}
		
		return $header;
	}
	
	function clear(){
		unset($this->_recipients);
		unset($this->_subject);
		unset($this->_body);
		unset($this->_header);
		unset($this->_sender);
	}

	function send()
	{		
		Log::add("邮件:{$this->_subject} 发送到:{$this->_recipients}", "mail");
		if( Config::get('debug.email') ){
			$this->clear();
			return true;
		}
		else {		
			$subject = '=?UTF-8?B?'.base64_encode($this->_subject).'?=';
			if(mail($this->_recipients, $subject, $this->_body, $this->header())){
				$this->clear();
				return true;
			}
		}
		
		$this->clear();
		return false;		
	}

	function from($email, $name = '')
	{
		if ($name != '' && substr($name, 0, 1) != '"'){
			$name = '"'.$name.'"';
		}else{
			$name=$email;
		}
	
		$this->_header['From']="$name <$email>";
		$this->_header['Return-Path']="<$email>";
		$this->_header['X-Sender']=$email;
	}
  	
	function reply_to($email, $name = '')
	{
		if ($name == ''){
			$name = $email;
		}

		if (substr($name, 0, 1) != '"'){
			$name = '"'.$name.'"';
		}

		$this->_header['Reply-To']="$name <$email>";
	}
 
	function to($to)
	{
		if(is_array($to))$to=implode(", ", $to);
		$this->_recipients = $to;
	}
  	  	
	function subject($subject)
	{
		$subject = preg_replace("/(\r\n)|(\r)|(\n)/", "", $subject);
		$subject = preg_replace("/(\t)/", " ", $subject);
		
		$this->_subject=trim($subject);		
	}
  	
	function body($text, $html=NULL){
		if(!$html){
			$this->_multi=NULL;
			$this->_body=$text;
		}else{
			$this->_multi='LIMS-'.md5(time());
			$this->_body.="--{$this->_multi}\n";
			$this->_body.="Content-Type: text/plain; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
			$this->_body.= stripslashes(rtrim(str_replace("\r", "", $text)));	
			$this->_body.="\n\n--{$this->_multi}\n";
			$this->_body.="Content-Type: text/html; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
			$this->_body.= stripslashes(rtrim(str_replace("\r", "", $html)));	
			$this->_body.="\n--{$this->_multi}--\n\n";
		}
	}
	
	private function get_message_id(){
		$from = $this->_headers['Return-Path'];
		$from = str_replace(">", "", $from);
		$from = str_replace("<", "", $from);
		return  "<".uniqid('').strstr($from, '@').">";	
	}

	private function get_date(){
		$timezone = date("Z");
		$operator = (substr($timezone, 0, 1) == '-') ? '-' : '+';
		$timezone = abs($timezone);
		$timezone = floor($timezone/3600) * 100 + ($timezone % 3600 ) / 60;
		
		return sprintf("%s %s%04d", date("D, j M Y H:i:s"), $operator, $timezone);		
	}

	private function clean_email($email)
	{
		if ( ! is_array($email))
		{
			if (preg_match('/\<(.*)\>/', $email, $match))
		   		return $match['1'];
		   	else
		   		return $email;
		}
			
		$clean_email = array();
		
		foreach ($email as $addy)
		{
			if (preg_match( '/\<(.*)\>/', $addy, $match))
			{
		   		$clean_email[] = $match['1'];				
			}
		   	else
			{
		   		$clean_email[] = $addy;					
			}
		}
		
		return $clean_email;
	}
	
}
