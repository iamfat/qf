<?php

abstract class _AJAX_Controller extends Controller {
	
	function _before_call($method, &$params){
		Event::bind('system.output', 'Output::AJAX');
		parent::_before_call($method, $params);
	}

	/* NO.BUG#242(xiaopei.li@2010.12.15) */
	function require_log_in(){
		JS::redirect(URI::url('error/401'));
	}
}
