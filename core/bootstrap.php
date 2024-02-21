<?php

define('CORE_PATH', dirname(__FILE__) . '/');

require CORE_PATH . 'def.php';
require CORE_PATH . 'exception.php';
require CORE_PATH . 'cache.php';
require CORE_PATH . 'core.php';

final class CGI
{
	static function shutdown()
	{
		Event::trigger('system.output');		// 系统显示事件
		Event::trigger('system.shutdown');		// 系统关闭事件
		Core::shutdown();						// 内核关闭
	}

	static function exception($e)
	{
		$message = $e->getMessage();
		if ($message) {
			$file = File::relative_path($e->getFile());
			$line = $e->getLine();
			error_log(sprintf("EXCEPTION %s (%s:%d)\n", $message, $file, $line));
			if (defined('DEBUG')) {
				$trace = array_slice($e->getTrace(), 1, 5);
				foreach ($trace as $n => $t) {
					error_log(sprintf(
						"    %d) %s%s() (%s:%d)",
						$n + 1,
						$t['class'] ? $t['class'] . '::' : '',
						$t['function'],
						File::relative_path($t['file']),
						$t['line']
					));
				}
			}
		}

		if (PHP_SAPI != 'cli') {
			while (@ob_end_clean());	//清空之前的所有显示
			header('HTTP/1.1 500 Internal Server Error');
		}
	}

	static function error($errno, $errstr, $errfile, $errline)
	{
		static $errors = [
			E_ERROR => "ERROR",
			E_CORE_ERROR => "CORE_ERROR",
			E_COMPILE_ERROR => "COMPILE_ERROR",
			E_USER_ERROR => "USER_ERROR",
			E_STRICT => "STRICT",
			E_RECOVERABLE_ERROR => "RECOVERABLE_ERROR",
		];

		static $others = [
			E_WARNING => "WARNING",
			E_PARSE => "PARSE",
			E_NOTICE => "NOTICE",
			E_CORE_WARNING => "CORE_WARNING",
			E_COMPILE_WARNING => "COMPILE_WARNING",
			E_USER_WARNING => "USER_WARNING",
			E_USER_NOTICE => "USER_NOTICE",
			E_STRICT => "STRICT",
			E_DEPRECATED => "DEPRECATED",
			E_USER_DEPRECATED => "USER_DEPRECATED",
		];

		if (isset($errors[$errno])) {
			error_log(sprintf("%s %s (%s:%d)\n", $errors[$errno], $errstr, $errfile, $errline));
		} else if (defined('DEBUG') && DEBUG === 'warning' && isset($others[$errno])) {
			error_log(STDERR, sprintf("%s %s (%s:%d)\n", $others[$errno], $errstr, $errfile, $errline));
		}
		// throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
	}

	static function assertion($file, $line, $code)
	{
		throw new ErrorException($code, 0, 1, $file, $line);
	}
}

register_shutdown_function('CGI::shutdown');
set_exception_handler('CGI::exception');
error_reporting(E_ALL & ~E_NOTICE);
set_error_handler('CGI::error', E_ALL & ~E_NOTICE);

// assert_options(ASSERT_ACTIVE, 1);
// assert_options(ASSERT_WARNING, 0);
// assert_options(ASSERT_QUIET_EVAL, 1);
// assert_options(ASSERT_CALLBACK, 'CGI::assertion');

Core::setup();						// 内核启动
Core::bind_events();				// 绑定内核事件

Event::trigger('system.setup');		// 系统初始化事件
Event::trigger('system.ready');		// 系统就绪事件

Core::dispatch();					// 分派控制器
