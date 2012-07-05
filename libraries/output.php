<?php

abstract class _Output {

	public static $AJAX = array();
	
	static function setup(){}
	
	static function shutdown(){}

	static function AJAX(){
		if(sizeof($_FILES)>0){
			header('Content-Type: text/html; charset=utf-8');
			echo '<textarea>'.htmlentities(json_encode(Output::$AJAX)).'</textarea>';
		}else{
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(Output::$AJAX);
		}
	}
	
	static function & T($str, $args=NULL, $options=NULL){
	
		if (is_array($str)) {
			foreach($str as &$s) {
				$s = T($s, $args, $options);
			}
			return $str;
		}
		
		//如果有I18N模块反调用I18N
		if(class_exists('I18N', false)){
			$str = I18N::convert($str, $options);
		}
	
		if($args){
			return stripcslashes(strtr($str, $args));
		} else {
			return stripcslashes($str);
		}
	
	}
	
	static function & HT($str, $args=NULL, $options=NULL, $convert_return=FALSE){
		return Output::H(Output::T($str, $args, $options), $convert_return);
	}
	
	static function H($str, $convert_return=FALSE){
		if(is_array($str)){
			$str = http_build_query($str);
			/*
			// H没有必要对数组进行处理
			foreach($str as & $s){
				$s=H($s);
			}
			return $str;
			*/
		}
		$str = htmlentities(iconv('UTF-8', 'UTF-8//IGNORE', $str), ENT_QUOTES, 'UTF-8', TRUE);
		return $convert_return ? preg_replace('/\n/', '<br/>', $str) : $str;
	}
	
}

function & T($str, $args=NULL, $options=NULL){
	return Output::T($str, $args, $options);
}

function & HT($str, $args=NULL, $options=NULL, $convert_return=FALSE){
	return Output::HT($str, $args, $options, $convert_return);
}

function H($str, $convert_return=FALSE){
	return Output::H($str, $convert_return);
}

function E($str) {
	return rawurlencode($str);
}
