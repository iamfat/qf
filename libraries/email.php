<?php

/*
	e.g.
	
	$email = new Email;
	$email->from('jia.huang@geneegroup.com', 'Jia Huang');
	$email->to('somebody@geneegroup.com', 'Somebody');
	$email->subject('Hello, world!');
	$email->body('lalalalalalala...');
	$email->send();

*/

abstract class _Email {

	private $_recipients;
	private $_sender;
	private $_reply_to;
	private $_subject;
	private $_body;
	private $_header;
	private $_multi;
	
	function __construct($sender=NULL){
		if(is_null($sender)){
            $sender = new stdClass;
			$sender->email = Config::get('system.email_address');
			$sender->name = Config::get('system.email_name', $sender->email);
		}

		$this->from($sender->email, $sender->name);
	}

	private function make_header() {
		$header = (array) $this->_header;

		$header['User-Agent']='Genee-Q';				
		$header['Date']=$this->get_date();
		$header['X-Mailer']='Genee-Q';		
		$header['X-Priority']='3 (Normal)';
		$header['Message-ID']=$this->get_message_id();		
		$header['Mime-Version']='1.0';
		if ($this->_multi) {
            if ($this->has_attachment()) {
                $header['Content-Type'] = 'multipart/mixed; boundary='. $this->_multi;
            }
            else {
                $header['Content-Type']='multipart/alternative; boundary='.$this->_multi.'';
            }
		}
		else {
			$header['Content-Transfer-Encoding']='8bit';
			$header['Content-Type']='text/plain; charset="UTF-8"';
		}

		$header_content = '';
		foreach($header as $k=>$v){
			$header_content .= "$k: $v\n";
		}

		return $header_content;
	}

    private $_body_text, $_body_html;

    private function make_body() {


        if ($this->has_attachment()) {

            $_body .= "--{$this->_multi}\n";
            $_body .= "Content-Type: text/html; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
            //存在attachment时,只发送html
            $this->_body_html = $this->_body_html ? : $this->_body_text;
            $_body .= stripslashes(rtrim(str_replace("\r", "", $this->_body_html))). "<br />\n\n";
            $_body .= $this->attachment_body();
        }
        else {
            $_body .= "--{$this->_multi}\n";
            $_body .= "Content-Type: text/plain; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
            $_body .= stripslashes(rtrim(str_replace("\r", "", $this->_body_text))). "\n\n\n";

            if ($this->_body_html) {
                $_body .= "--{$this->_multi}\n";
                $_body .= "Content-Type: text/html; charset=\"UTF-8\"\nContent-Transfer-Encoding: 8bit\n\n";
                $_body .= stripslashes(rtrim(str_replace("\r", "", $this->_body_html))). "\n\n\n";
            }
        }

        $_body .= "\n--{$this->_multi}--\n\n";

        return $_body;
    }

	function clear(){
		unset($this->_recipients);
		unset($this->_subject);
		unset($this->_body);
		unset($this->_header);
		unset($this->_sender);
		unset($this->_reply_to);
	}

	private function encode_text($text) {
		return mb_encode_mimeheader($text, 'UTF-8');
	}

	function send()
	{		
		$success = FALSE;
		if (Config::get('debug.email')) {
			$success = TRUE;
		}
		else {		
			$subject = $this->encode_text($this->_subject);
			
			$recipients = $this->_header['To'];
			unset($this->_header['To']);

			$header = $this->make_header();
            $body = $this->make_body();

            $success = mail($recipients, $subject, $body, $header);
		}
		
		$subject = $this->_subject;
		$recipients =  $this->_recipients;
		$sender = $this->_sender;
		$reply_to = $this->_reply_to;

		Log::add("邮件:{$subject} 由{$sender}(RT:{$reply_to}) 发送到{$recipients} S:{$success}", 'mail');
		$this->clear();
		return $success;		
	}

	function from($email, $name=NULL)
	{
		$this->_header['From'] = $name ? $this->encode_text($name) . "<$email>" : $email;
		$this->_header['Return-Path']="<$email>";
		$this->_header['X-Sender']=$email;

		$this->_sender = $email;
	}
  	
	function reply_to($email, $name=NULL)
	{
		$this->_header['Reply-To'] = $name ? $this->encode_text($name) . "<$email>" : $email;

		$this->_reply_to = $email;
	}

	function to($email, $name=NULL)
	{
		if (is_array($email)) {
			$mails = array();
			$header_to = array();
			foreach($email as $k=>$v) {
				if (is_numeric($k)) {
					$mails[] = $v;
					$header_to[] = $v;
				}
				else {
					// $k是email, $v是name
					$mails[] = $k;
					$header_to[] = $v ? $this->encode_text($v) . "<$k>" : $k;
				}
			}
			$this->_header['To'] = implode(', ', $header_to);
			$this->_recipients = implode(', ', $mails);
		}
		else {
			$this->_header['To'] = $name ? $this->encode_text($name) . "<$email>" : $email;
			$this->_recipients = $email;
		}
	}
  	  	
	function subject($subject)
	{
		$subject = preg_replace("/(\r\n)|(\r)|(\n)/", "", $subject);
		$subject = preg_replace("/(\t)/", " ", $subject);
		
		$this->_subject = trim($subject);		
	}
  	
	function body($text, $html=NULL){
		if (!$html) {
			$this->_multi=NULL;
			$this->_body=$text;
		}
		else {
			$this->_multi='GENEE-'.md5(Date::time());
            $this->_body_text = $text;
            $this->_body_html = $html;
		}
	}
	
	private function get_message_id() {
		$from = $this->_headers['Return-Path'];
		$from = str_replace(">", "", $from);
		$from = str_replace("<", "", $from);
		return  "<".uniqid('').strstr($from, '@').">";	
	}

	private function get_date() {
		$timezone = date("Z");
		$operator = (substr($timezone, 0, 1) == '-') ? '-' : '+';
		$timezone = abs($timezone);
		$timezone = floor($timezone/3600) * 100 + ($timezone % 3600 ) / 60;
		
		return sprintf("%s %s%04d", date("D, j M Y H:i:s"), $operator, $timezone);		
	}

    private $_attachment = array();
    function attachment($files = '') {
        if (!is_array($files)) $files = array($files);

        foreach($files as $file) {
            //增加到attachment中
            $this->_attachment[$file] = basename($file);
        }
    }

    private function has_attachment() {
        return count($this->_attachment);
    }

    private function attachment_body() {
        foreach($this->_attachment as $path => $file) {
            $attach_data[] = sprintf('--%s', $this->_multi);
            $attach_data[] = sprintf('Content-Type: application/octet-stream; name="%s"',  File::mine_type($file)? : 'octet-stream', $file);
            $attach_data[] = 'Content-Transfer-Encoding: base64';
            $attach_data[] = 'Content-Disposition: attachment';
            $attach_data[] = sprintf('filename="%s"', $file);
            $attach_data[] = NULL; //需要占位，这样mail发送才能正常进行解析
            $attach_data[] = chunk_split(@base64_encode(@file_get_contents($path)));
        }

        return join("\n", $attach_data);
    }
}
