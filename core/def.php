<?php

if (PHP_SAPI == 'cli') {
	define('CLI_MODE', 1);
}

$phar_path = ROOT_PATH.'application.phar';
if (is_file($phar_path)) {
	define('APP_PATH', 'phar://'.ROOT_PATH.'application.phar/');
}
else {
	define('APP_PATH', ROOT_PATH.'application/');
}

define('MODULE_BASE', 'modules/');
define('MODULE_PATH', ROOT_PATH.MODULE_BASE);

define('CONFIG_BASE', 'config/');
define('CONTROLLER_BASE', 'controllers/');
define('LIBRARY_BASE', 'libraries/');
define('VIEW_BASE', 'views/');
define('MODEL_BASE', 'models/');
define('WIDGET_BASE', 'widgets/');
define('PRIVATE_BASE', 'private/');
define('PUBLIC_BASE', 'public/');
define('THIRD_BASE', '3rd/');
define('I18N_BASE', 'i18n/');

define('DEFAULT_VIEW', 'html');

define('EXT', '.php');
define('VEXT', '.phtml');

if (extension_loaded('redis')) {
    define('DEFAULT_CACHE', 'redis');
}
elseif (extension_loaded('yac')) {
    define('DEFAULT_CACHE', 'yac');
}
elseif (extension_loaded('xcache') && ini_get('xcache.var_size')) {
    define('DEFAULT_CACHE', 'xcache');
}
elseif (extension_loaded('apc')) {
    define('DEFAULT_CACHE', 'apc');
}

if (!defined('DEFAULT_CACHE')) {
	define('DEFAULT_CACHE', 'nocache');
}

//仅在注册目录搜索
define('CONTROLLER_SUFFIX', '_controller');
define('AJAX_SUFFIX', '_ajax_controller');
define('VIEW_SUFFIX', '_view');
//可在所有存在目录搜索
define('MODEL_SUFFIX', '_model');
define('WIDGET_SUFFIX', '_widget');


