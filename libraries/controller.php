<?php

abstract class _Controller {

	static $CURRENT;

	protected $delegates = array();

	protected $ignore_extensions = array();

	protected $curr_extension;

	function index(){}
	
	function __call($method, $args) {
		switch($method) {
		case __CLASS__:
			return;
		default:
			if (is_array($delegates)) foreach($delegates as $delegate) {
				if(method_exists($delegate, $method)) {
					return call_user_func_array(array($delegate, $method), $args);
				}
			}
			return NULL;
		}
	}
	
	function _before_call($method, &$params) {
		$ext_arr = (array) $this->ignore_extensions[$method];
		if (count($ext_arr) > 0) {
			$ext = strtolower(end($params));
			if ($ext && in_array($ext, $ext_arr)) {
				// 符合条件的扩展名
				array_pop($params);
				Config::set('system.controller_params', $params);
				$this->curr_extension = $ext;
			}
		}
	}
	
	function _after_call($method, &$params) {
	}

}
