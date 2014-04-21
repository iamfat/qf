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
    private $_boundary;

    function __construct($sender = NULL) {
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
        if ($this->_boundary) {
            if ($this->_has_attachment) {
                $header['Content-Type'] = 'multipart/mixed; boundary='. $this->_boundary;
            }
            else {
                $header['Content-Type']='multipart/alternative; boundary='.$this->_boundary.'';
            }
        }
        else {
            //不存在boundary 说明只有plain
            //需要设定header中charset为utf-8
            //content-type为plain
            //encoding为base64

            if (!$this->_has_attachment) {
                $header['Content-Type'] = 'text/plain; charset=UTF-8';
                $header['Content-Transfer-Encoding'] = 'base64';
            }
        }

        $header_content = '';
        foreach($header as $k=>$v){
            $header_content .= "$k: $v\n";
        }

        //进行换行
        $header_content .= "\n";

        return $header_content;
    }

    private $_body_plain, $_body_html;

    private function make_body() {

        $_body_plain = $this->_body_plain();

        if ($_body_plain) {
            if ($this->_boundary) $_body .= "--{$this->_boundary}\n";
            $_body .= $_body_plain;
        }

        $_body_html = $this->_body_html();

        if ($_body_html) {
            $_body .= $_body_html;
        }

        $_body_attachment = $this->_body_attachment();

        if ($_body_attachment) {
            $_body .= $_body_attachment;
        }

        //body结束后补充boundary
        if ($this->_boundary) $_body .= "--{$this->_boundary}--";

        return $_body;
    }

    //body_plain有可能只设定plain
    private function _body_plain() {
        if ($this->_has_attachment) return FALSE;

        if ($this->_boundary) {
            $_body_plain = array();
            $_body_plain[] = 'Content-Type: text/plain; charset=UTF-8';
            $_body_plain[] = 'Content-Transfer-Encoding: base64';
            $_body_plain[] = NULL;
            $_body_plain[] = chunk_split(base64_encode($this->_body_plain));
            $_body_plain[] = NULL;

            return join("\n", $_body_plain);
        }
        else {
            return chunk_split(base64_encode($this->_body_plain));
        }
    }

    private function _body_html() {
        if (!$this->_body_html && !$this->_has_attachment) return FALSE;

        $_body_html = array();
        $_body_html[] = "--{$this->_boundary}";
        $_body_html[] = 'Content-Type: text/html; charset=UTF-8';
        $_body_html[] = 'Content-Transfer-Encoding: base64';
        $_body_html[] = NULL;

        //存在attachment但是不存在html, 解析plain为html, 增加换行
        if ($this->_has_attachment) $this->_body_html = ($this->_body_html ? : $this->_body_plain). '<br />';

        $_body_html[] = chunk_split(base64_encode($this->_body_html));
        $_body_html[] = NULL;

        return join("\n", $_body_html);
    }

    private function _body_attachment() {

        if (!$this->_has_attachment) return NULL;

        foreach($this->_attachment as $path => $file) {
            $attach_data[] = sprintf('--%s', $this->_boundary);
            $attach_data[] = sprintf('Content-Type: %s; name="%s"',  File::mime_type($file) ? : 'application/octet-stream', $file);
            $attach_data[] = 'Content-Transfer-Encoding: base64';
            $attach_data[] = 'Content-Disposition: attachment';
            $attach_data[] = sprintf('filename="%s"', $file);
            $attach_data[] = NULL; //需要占位，这样mail发送才能正常进行解析附件
            $attach_data[] = chunk_split(@base64_encode(@file_get_contents($path)));
            $attach_data[] = NULL;
        }

        return join("\n", $attach_data);
    }

    private function encode_text($text) {
        return mb_encode_mimeheader($text, 'UTF-8');
    }

    function send() {
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

        if ($success) {
            Log::add("邮件:{$subject} 由{$sender}(RT:{$reply_to}) 发送到{$recipients} S:{$success}", 'mail');
        }

        return $success;
    }

    function from($email, $name=NULL) {
        $this->_header['From'] = $name ? $this->encode_text($name) . "<$email>" : $email;
        $this->_header['Return-Path']="<$email>";
        $this->_header['X-Sender']=$email;

        $this->_sender = $email;
    }

    function reply_to($email, $name=NULL) {
        $this->_header['Reply-To'] = $name ? $this->encode_text($name) . "<$email>" : $email;

        $this->_reply_to = $email;
    }

    function to($email, $name=NULL) {
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

    function subject($subject) {
        $subject = preg_replace("/(\r\n)|(\r)|(\n)/", "", $subject);
        $subject = preg_replace("/(\t)/", " ", $subject);

        $this->_subject = trim($subject);
    }

    function body($plain, $html=NULL) {
        if (!$html) {
            $this->_boundary = NULL;
            $this->_body_plain = $plain;
        }
        else {
            $this->_make_boundary();
            $this->_body_plain = $plain;
            $this->_body_html = $html;
        }
    }

    private function _make_boundary() {
        $this->_boundary = $this->_boundary ? : 'GENEE-'.md5(Date::time());
    }

    private function get_message_id() {
        $from = $this->_headers['Return-Path'];
        $from = str_replace('>', '', $from);
        $from = str_replace('<', '', $from);
        return  '<'.uniqid('').strstr($from, '@').'>';
    }

    private function get_date() {
        $timezone = date("Z");
        $operator = (substr($timezone, 0, 1) == '-') ? '-' : '+';
        $timezone = abs($timezone);
        $timezone = floor($timezone/3600) * 100 + ($timezone % 3600 ) / 60;

        return sprintf('%s %s%04d', date('D, j M Y H:i:s'), $operator, $timezone);
    }

    private $_attachment = array();
    private $_has_attachment;

    function attachment($files = '') {
        if (!is_array($files)) $files = array($files);

        foreach($files as $file) {
            //增加到attachment中
            $this->_attachment[$file] = basename($file);
        }

        $this->_has_attachment = (bool) count($this->_attachment);

        $this->_make_boundary();
    }

}
