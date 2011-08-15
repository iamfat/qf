<?php

class _Pagination_Widget extends Widget {
	
	function __construct($vars=array()){
		if (!is_array($vars)) $vars = array();
		$vars += array(
			'query_key'=>'st',
		);
		parent::__construct('pagination', $vars);
	}

}
