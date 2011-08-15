<?php

define('CORE_PATH', dirname(__FILE__).'/');

require CORE_PATH.'def.php';
require CORE_PATH.'exception.php';
require CORE_PATH.'cache.php';
require CORE_PATH.'core.php';

final class CGI {
	static function shutdown() {
		Event::trigger('system.output');		// 系统显示事件
		Event::trigger('system.shutdown');		// 系统关闭事件
		Core::shutdown();						// 内核关闭
	}

	static function exception($e) {
		while(@ob_end_clean());	//清空之前的所有显示
		$message = $e->getMessage();
		if ($message) {
			error_log($message);
		}
		header('HTTP/1.1 500 Internal Server Error');
	}

}

register_shutdown_function('CGI::shutdown');
set_exception_handler('CGI::exception');

Core::setup();						// 内核启动
Core::bind_events();				// 绑定内核事件

Event::trigger('system.setup');		// 系统初始化事件
Event::trigger('system.ready');		// 系统就绪事件

Core::dispatch();					// 分派控制器

