<?php

abstract class _Error_Controller extends Layout_Controller {

	function index($code = 404) {
		switch ($code) {
		case 401:
			header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
			header("Status: 401 Unauthorized");
			break;
		case 404:
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			header("Status: 404 Not Found");
			break;
		}
		$this->layout->body = V('errors/'.$code);
	}

}
