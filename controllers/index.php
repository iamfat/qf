<?php

abstract class _Index_Controller extends Layout_Controller {
	
	function index() {
		$this->layout->body = V('body');
	}
	
}
