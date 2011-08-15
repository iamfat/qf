<?php

abstract class _Unit_Test {

	const ANSI_RED = "\033[31m";
	const ANSI_GREEN = "\033[32m";
	const ANSI_RESET = "\033[0m";
	const ANSI_HIGHLIGHT = "\033[1m";
	
	static function test($name, $return_output = FALSE) {
		if ($return_output) {
			ob_start();
			@include (self::test_path($name));
			$output = ob_get_contents();
			ob_end_clean();
			return $output;
		}
		else {
			@include (self::test_path($name));
		}
	}
	
	static function test_root() {
		return ROOT_PATH.'unit_tests/scripts/';
	}
	
	static function test_path($name) {
		return ROOT_PATH."unit_tests/scripts/$name.php";
	}
	
	static function expect_path($name) {
		return ROOT_PATH."unit_tests/expects/$name.out";
	}
	
	static function echo_title() {
		echo Unit_Test::ANSI_HIGHLIGHT;
		$args = func_get_args();
		if (count($args) > 0) call_user_func_array('printf', $args);
		echo "\n".str_repeat('=', 80)."\n";
		echo Unit_Test::ANSI_RESET;
	}
	
	static function echo_text() {
		$args = func_get_args();
		call_user_func_array('printf', $args);
		echo Unit_Test::ANSI_RESET;
		echo "\n";
	}
	
	static function echo_assert($name, $expr, $debug=NULL) {
		
		echo Unit_Test::ANSI_RESET;
		
		echo "测试 ($name) ... ";
		if ($expr) {
			echo Unit_Test::ANSI_GREEN;
			echo "SUCCESS";
			echo Unit_Test::ANSI_RESET;
		}
		else {
			echo Unit_Test::ANSI_RED;
			echo "FAILED";
			echo Unit_Test::ANSI_RESET;
			if ($debug) {
				echo "\n";
				echo $debug;
			}
		}
		echo "\n";
	}
	
	static function echo_endl() {
		echo "\n";
	}
	
}
