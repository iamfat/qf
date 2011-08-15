<?php

class _Button_Widget extends Widget {
	
	function __construct($vars=array()){
		
		if (!is_array($vars)) $vars = array();

		$vars += array(
			'title'=>T('未命名'),
			'href'=>'#',
			'type'=>'normal',
		);

		parent::__construct('button', $vars);

	}
	
}
