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
		error_log($e->getMessage());
	}

}

register_shutdown_function ('CLI::shutdown');
set_exception_handler('CLI::exception');

Core::setup();
Core::bind_events();

Event::trigger('system.setup');
Event::trigger('system.ready');
