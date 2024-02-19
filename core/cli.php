<?php

define('CORE_PATH', dirname(__FILE__) . '/');

require CORE_PATH . 'def.php';
require CORE_PATH . 'exception.php';
require CORE_PATH . 'cache.php';
require CORE_PATH . 'core.php';

final class CLI
{
	static function shutdown()
	{
		Event::trigger('system.output');
		Event::trigger('system.shutdown');
		Core::shutdown();
	}

	static function exception($e)
	{
		$message = $e->getMessage();
		$file = File::relative_path($e->getFile());
		$line = $e->getLine();
		printf("\x1b[31;4mEXCEPTION\x1b[0m %s (\x1b[34m%s\x1b[0m:%d)\n", $message, $file, $line);
		if (defined('DEBUG')) {
			$trace = array_slice($e->getTrace(), 1, 5);
			foreach ($trace as $n => $t) {
				fprintf(
					STDERR,
					"%3d. %s%s() in (%s:%d)\n",
					$n + 1,
					$t['class'] ? $t['class'] . '::' : '',
					$t['function'],
					File::relative_path($t['file']),
					$t['line']
				);
			}
			fprintf(STDERR, "\n");
		}
	}

	static function error($errno, $errstr, $errfile, $errline)
	{
		static $errors = [
			E_ERROR => "\x1b[31;4mERROR\x1b[0m",
			E_CORE_ERROR => "\x1b[31;4mCORE_ERROR\x1b[0m",
			E_COMPILE_ERROR => "\x1b[31;4mCOMPILE_ERROR\x1b[0m",
			E_USER_ERROR => "\x1b[31;4mUSER_ERROR\x1b[0m",
			E_STRICT => "\x1b[31;4mSTRICT\x1b[0m",
			E_RECOVERABLE_ERROR => "\x1b[31;4mRECOVERABLE_ERROR\x1b[0m",
		];

		static $others = [
			E_WARNING => "\x1b[33mWARNING\x1b[0m",
			E_PARSE => "\x1b[31mPARSE\x1b[0m",
			E_NOTICE => "\x1b[32mNOTICE\x1b[0m",
			E_CORE_WARNING => "\x1b[33mCORE_WARNING\x1b[0m",
			E_COMPILE_WARNING => "\x1b[33mCOMPILE_WARNING\x1b[0m",
			E_USER_WARNING => "\x1b[33mUSER_WARNING\x1b[0m",
			E_USER_NOTICE => "\x1b[32mUSER_NOTICE\x1b[0m",
			E_STRICT => "\x1b[31;4mSTRICT\x1b[0m",
			E_DEPRECATED => "\x1b[30;1mDEPRECATED\x1b[0m",
			E_USER_DEPRECATED => "\x1b[30;1mUSER_DEPRECATED\x1b[0m",
		];

		if (isset($errors[$errno])) {
			fprintf(STDERR, sprintf("%s %s (\x1b[34m%s\x1b[0m:%d)\n", $errors[$errno], $errstr, $errfile, $errline));
		} else if (defined('DEBUG') && isset($others[$errno])) {
			fprintf(STDERR, sprintf("%s %s (\x1b[34m%s\x1b[0m:%d)\n", $others[$errno], $errstr, $errfile, $errline));
		}
		// throw new \ErrorException($errstr, $errno, 1, $errfile, $errline);
	}

	static function assertion($file, $line, $code)
	{
		throw new \ErrorException($code, 0, 1, $file, $line);
	}
}

register_shutdown_function('CLI::shutdown');
set_exception_handler('CLI::exception');
set_error_handler('CLI::error', E_ALL & ~E_NOTICE);

// assert_options(ASSERT_ACTIVE, 1);
// assert_options(ASSERT_WARNING, 0);
// assert_options(ASSERT_QUIET_EVAL, 1);
// assert_options(ASSERT_CALLBACK, 'CGI::assertion');


Core::setup();
Core::bind_events();

Event::trigger('system.setup');
Event::trigger('system.ready');
