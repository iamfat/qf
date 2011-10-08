<?php

abstract class _AJAX_Controller extends Controller {
	
	function _before_call($method, &$params){
		Event::bind('system.output', 'Output::AJAX');
		parent::_before_call($method, $params);
	}

}
