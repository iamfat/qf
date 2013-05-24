<?php

define('CORE_PATH', dirname(__FILE__).'/');

require CORE_PATH.'def.php';
require CORE_PATH.'exception.php';
require CORE_PATH.'cache.php';
require CORE_PATH.'core.php';

final class CLI {
	static function shutdown() {
		Event::trigger('system.output');
		Event::trigger('system.shutdown');
		Core::shutdown();
	}

	static function exception($e) {
		$message = $e->getMessage();
		$file = File::relative_path($e->getFile());
		$line = $e->getLine();
		printf("[exception] \x1b[1m%s\x1b[0m (\x1b[34m%s\x1b[0m:$line)\n", $message, $file, $line);
		if (defined('DEBUG')) {
			$trace = array_slice($e->getTrace(), 1, 3);
			foreach ($trace as $n => $t) {
				fprintf(STDERR, "%3d. %s%s() in (%s:%d)\n", $n + 1,
								$t['class'] ? $t['class'].'::':'', 
								$t['function'],
								File::relative_path($t['file']),
								$t['line']);

			}
			fprintf(STDERR, "\n");
		}
	}

	static function error($errno , $errstr, $errfile, $errline, $errcontext) {
		error_log(sprintf("\x1b[31m\x1b[4mERROR\x1b[0m \x1b[1m%s (%s:%d) \x1b[0m", $errstr, $errfile, $errline));
		// throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
	}

	static function assertion($file, $line, $code) {
		throw new \ErrorException($code, 0, 1, $file, $line);
	}

}

register_shutdown_function ('CLI::shutdown');
set_exception_handler('CLI::exception');
set_error_handler('CLI::error', E_ALL & ~E_NOTICE);

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_QUIET_EVAL, 1);
assert_options(ASSERT_CALLBACK, 'CGI::assertion');


Core::setup();
Core::bind_events();

Event::trigger('system.setup');
Event::trigger('system.ready');
