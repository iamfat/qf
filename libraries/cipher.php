<?php

abstract class _Cipher {

    static private function _get_iv() {
        return "\0\0\0\0\0\0\0\0";
    }

	static function encrypt($text, $salt, $base64=FALSE, $mode = 'blowfish') {
		if(!$salt) return $text;
		$code = @openssl_encrypt($text, $mode, $salt, !$base64, self::_get_iv());
		return $code;
	}

	static function decrypt($code, $salt, $base64=FALSE, $mode = 'blowfish') {
		if(!$salt) return $code;
		$text = @openssl_decrypt($code, $mode, $salt, !$base64, self::_get_iv());
		return $text;
	}
	
}
