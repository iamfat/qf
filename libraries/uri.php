<?php

abstract class _URI {

	static function url($url=NULL, $query=NULL, $fragment=NULL) {
		
		if(!$url) $url = Input::route();
	
		$ui=parse_url($url);
	
		if($ui['scheme']=='mailto') {
			//邮件地址
			return 'mailto:'.$ui['user'].'@'.$ui['host'];
		}
	
		if ($query) {
			if ($ui['query']) {
				if(is_string($query))parse_str($query, $query);
				parse_str($ui['query'], $old_query);
				$ui['query']=http_build_query(array_merge($old_query, $query));
			} else {
				$ui['query']=is_string($query)?$query:http_build_query($query);
			}
		}
		
		if ($fragment) $ui['fragment']=$fragment;
	
		if ($ui['host']) {
			$url = $ui['scheme'] ?: 'http';
			$url.='://';
			if($ui['user']){
				if($ui['pass']){
					$url.=$ui['user'].':'.$ui['pass'].'@';
				}else{
					$url.=$ui['user'].'@';
				}
			}
			$url.=$ui['host'];
			if($ui['port'])$url.=':'.$ui['port'];
			$url.='/';		
		}
		else {
			$url = Config::get('system.script_url');
			if(substr($url, -1)!='/')$url.='/';
		}
		
		if($ui['path']){
			$url.=ltrim($ui['path'], '/');
		}
		
		if($ui['query']){
			$url.='?'.$ui['query'];
		}
		
		if($ui['fragment']){
			$url.='#'.$ui['fragment'];
		}
		
		return $url;
	}

	static function redirect($url='', $query=NULL) {
	    session_write_close();
		header('Location: '. URI::url($url, $query), TRUE, 302);
		exit();
	}
	
	static function encode($text) {
		return rawurlencode(strtr($text, array('.'=>'\.', '/'=>'\/')));
	}
	
	static function decode($text) {
		return strtr($text, array('\.'=>'.', '\/'=>'/'));
	}
	
	static function anchor($url, $text = NULL, $extra=NULL, $options=array()) {
		if ($extra) $extra = ' '.$extra;
		if (!$text) $text = $url;
		$url = URI::url($url, $options['query'], $options['fragment']);
		return '<a href="'.$url.'"'.$extra.'>'.$text.'</a>';
	}
	
	static function mailto($mail, $name=NULL) {
		if(!$name) $name = $mail;
		return '<a href="mailto:'.$mail.'">'.$name.'</a>';
	}
		
}

function _U() {
	$args = func_get_args();
	return call_user_func_array('URI::url', $args);
}

function _C($path) {
	$full_path = Core::file_exists(PUBLIC_BASE.$path);
	return $full_path ? Cache::cache_file($full_path) : $path;
}
