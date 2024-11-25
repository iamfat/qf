<?php

/**
 *
 * @package    Core
 * @author     黄嘉
 * @copyright  (c) 2009 基理科技
 */

final class Core
{

	static $PATHS        = array();
	static $MODULE_BASES = array();
	static $G            = array();
	static $LEGAL_MODULES;

	public static function normalize_path($path)
	{
		if (false === strpos($path, 'phar://')) {
			//尝试替换成phar
			$phar_path = dirname($path) . '/' . basename($path, '.phar') . '.phar';
			if (file_exists($phar_path)) {
				return 'phar://' . $phar_path . '/';
			}
		}

		return $path;
	}

	public static function include_path($name, $path)
	{
		$path = self::normalize_path($path);
		unset(Core::$PATHS[$path]);
		Core::$PATHS = array($path => $name) + Core::$PATHS;
	}

	public static function module_path($module)
	{

		foreach (self::$MODULE_BASES as $base) {

			$mbase = $base . MODULE_BASE;

			$path = $mbase . $module;
			if (file_exists($path . '.phar')) {
				return 'phar://' . $path . '.phar/';
			}

			if (file_exists($path)) {
				return $path . '/';
			}
		}

		return $path . '/';
	}

	public static function _module_deps($name, $path)
	{
		$dep_path = $path . 'config/depmod' . EXT;
		!file_exists($dep_path) or @include $dep_path;
		return (array) $config[$name];
	}

	private static $_module_paths;
	public static function _module_paths($base, $prefix = null)
	{

		$paths = self::$_module_paths[$base];
		if (!isset($paths)) {
			$inf = [];

			$mbase = $base . MODULE_BASE;

			$phars    = glob($mbase . '*.phar');
			$excluded = array();
			foreach ($phars as $phar) {
				$name    = basename($phar, '.phar');
				$k       = 'phar://' . $phar . '/';
				$inf[$k] = (object) array(
					'name' => $name,
					'deps' => Core::_module_deps($name, $k),
				);
				$excluded[$name] = true;
			}

			$dirs = glob($mbase . '*', GLOB_ONLYDIR);
			foreach ($dirs as $dir) {
				$name = basename($dir);
				if (isset($excluded[$name])) {
					continue;
				}

				$k       = $dir . '/';
				$inf[$k] = (object) array(
					'name' => $name,
					'deps' => Core::_module_deps($name, $k),
				);
			}

			//进行优先级排序
			$paths = [];

			$import = function ($path, $o) use (&$paths, $inf, &$import) {
				if ($o->deps) {
					foreach ($o->deps as $dep) {
						foreach ($inf as $k => $oo) {
							if ($dep != $oo->name) {
								continue;
							}

							if (isset($paths[$k])) {
								continue;
							}

							$import($k, $oo);
						}
					}
				}
				$paths[$path] = $o->name;
			};

			foreach ($inf as $k => $o) {
				if (isset($paths[$k])) {
					continue;
				}

				$import($k, $o);
			}

			self::$_module_paths[$base] = $paths;
		}

		if ($prefix) {
			foreach ($paths as $path => $name) {
				$paths[$path] = $prefix . $name;
			}
			return $paths;
		}

		return $paths;
	}

	public static function module_paths($prefix = null)
	{
		//搜索所有的module目录
		$paths = array();

		foreach (self::$MODULE_BASES as $base) {
			$paths += self::_module_paths($base, $prefix);
		}

		return $paths;
	}

	public static function set_legal_modules($mods)
	{
		if (!self::$_module_paths) {
			return;
		}

		foreach (self::$_module_paths as $base => &$paths) {
			if ($paths) {
				foreach ($paths as $p => $n) {
					if (!isset($mods[$n])) {
						unset($paths[$p]);
						unset(self::$PATHS[$p]);
					}
				}
			}
		}
	}

	public static function include_modules($base)
	{
		self::$MODULE_BASES[$base] = $base;
		self::$PATHS               = array_reverse(self::_module_paths($base, '@')) + self::$PATHS;
	}

	public static function setup()
	{

		spl_autoload_register('Core::autoload');

		setlocale(LC_ALL, 'UTF8');
		mb_internal_encoding('utf-8');
		mb_language('uni');

		self::include_path('system', SYS_PATH);

		//加载所有module的路径
		self::include_modules(ROOT_PATH);

		self::include_path('application', APP_PATH);

		Config::load(SYS_PATH);
		Config::load(APP_PATH);

		Input::setup();
		Output::setup();

		View::setup();
	}

	public static function reload_config()
	{
		Config::clear();
		foreach (array_reverse(Core::$PATHS) as $p => $n) {
			Config::load($p);
		}
	}

	public static function bind_events()
	{
		foreach ((array) Config::get('hooks') as $event => $hooks) {
			foreach ((array) $hooks as $callback) {
				if (is_array($callback) && isset($callback['callback'])) {
					$weight   = (int) $callback['weight'];
					$key      = $callback['key'];
					$callback = $callback['callback'];
				} else {
					$weight = 0;
					$key    = null;
				}
				Event::bind($event, $callback, $weight, $key);
			}
		}
	}

	public static function shutdown()
	{
		Output::shutdown();
		Input::shutdown();
		Config::shutdown();
	}

	//定义用于提取类文件名的正则表达类型
	public static function autoload($class)
	{

		//定义类后缀与类路径的对应关系
		static $CLASS_BASES = array(
			MODEL_SUFFIX      => MODEL_BASE,
			WIDGET_SUFFIX     => WIDGET_BASE,
			CONTROLLER_SUFFIX => CONTROLLER_BASE,
			AJAX_SUFFIX       => CONTROLLER_BASE,
			'*'               => LIBRARY_BASE,
		);

		static $CLASS_PATTERN;

		if ($CLASS_PATTERN === null) {
			$SUFFIX1 = CONTROLLER_SUFFIX . '|' . AJAX_SUFFIX . '|' . VIEW_SUFFIX;
			$SUFFIX2 = MODEL_SUFFIX . '|' . WIDGET_SUFFIX;

			$CLASS_PATTERN = "/^(_)?(\w+?)(?:({$SUFFIX1})?|({$SUFFIX2})?)$/i";
		}

		$class = strtolower($class);

		$nocache = false;

		if (preg_match($CLASS_PATTERN, $class, $parts)) {

			list(, $is_core, $name, $suffix1, $suffix2) = $parts;

			if ($suffix1 != CONTROLLER_SUFFIX && isset($GLOBALS['class_map']) && is_array($GLOBALS['class_map'])) {
				$class_map = $GLOBALS['class_map'];
				if (isset($class_map[$class])) {
					require_once $class_map[$class];
				}

				return;
			}

			if ($suffix1) {
				$bases         = $CLASS_BASES[$suffix1];
				$need_traverse = false;
				$nocache       = true;
			} elseif ($suffix2) {
				$bases         = $CLASS_BASES[$suffix2];
				$need_traverse = true;
			} else {
				$bases         = $CLASS_BASES['*'];
				$need_traverse = true;
			}

			$scope = $is_core ? 'system' : ($need_traverse ? '*' : null);

			if (!$nocache) {
				$cacher    = Cache::factory();
				$cache_key = 'autoload_path:' . $class;
				$file      = $cacher->get($cache_key);
				if ($file) {
					require_once $file;
					return;
				}
			}

			/*
            多重路径寻址 A_B_C
            a/b/c.php
            a/b_c.php
            a_b_c.php
             */
			$units   = explode('_', $name);
			$paths[] = implode('/', $units);
			$rest    = array_pop($units);
			while (count($units) > 0) {
				$paths[] = implode('/', $units) . '_' . $rest;
				$rest    = array_pop($units) . '_' . $rest;
			}

			$file = Core::load($bases, $paths, $scope);
			if (class_exists($class, false)) {
				//缓冲类到文件的索引
				$nocache or $cacher->set($cache_key, $file);
			} elseif (!$is_core) {
				//如果不存在原始类 加载代用类
				$sub_paths = array();
				foreach ($paths as $path) {
					$sub_paths[] = '@/' . $path;
				}
				$file = Core::load($bases, $sub_paths, 'system');
				if (!$nocache && class_exists($class, false)) {
					$cacher->set($cache_key, $file);
				}
			}
		}
	}

	public static function load($base, $name, $scope = null)
	{
		if (is_array($base)) {
			foreach ($base as $b) {
				$file = Core::load($b, $name, $scope);
				if ($file) {
					return $file;
				}
			}
		} elseif (is_array($name)) {
			foreach ($name as $n) {
				$file = Core::load($base, $n, $scope);
				if ($file) {
					return $file;
				}
			}
		} else {
			$file = Core::file_exists($base . $name . EXT, $scope);
			if ($file) {
				require_once $file;
				return $file;
			}
		}
		return false;
	}

	public static function file_exists($path, $scope = null)
	{

		foreach (self::file_paths($path, $scope) as $path) {
			if (file_exists($path)) {
				return $path;
			}
		}

		return null;
	}

	public static function file_paths($path, $scope = null)
	{
		if ($scope) {
			$skip_implicit_modules = false;
			if ($scope === '*') {
				$scope = null;
			} else {
				if (!is_array($scope)) {
					$scope = explode(',', $scope);
				}

				$scope = array_flip($scope);
			}
		} else {
			$skip_implicit_modules = true;
		}

		$paths     = array();
		$has_scope = is_array($scope) && count($scope) > 0;
		foreach (Core::$PATHS as $base => $name) {
			if ($name[0] === '@') {
				if ($skip_implicit_modules === true) {
					continue;
				}

				if ($has_scope) {
					$name = substr($name, 1);
				}
				//remove first @ in $name
			}
			if ($has_scope && !isset($scope[$name])) {
				continue;
			}
			$paths[] = $base . $path;
		}

		return $paths;
	}

	private static $mime_dispatchers = array();

	public static function register_mime_dispatcher($mime, $dispatcher)
	{
		self::$mime_dispatchers[$mime] = $dispatcher;
	}

	public static function dispatch()
	{

		$accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
		while (null !== ($accept = array_pop($accepts))) {
			list($mime) = explode(';', $accept, 2);
			$dispatcher = self::$mime_dispatchers[$mime];
			if ($dispatcher) {
				return call_user_func($dispatcher);
			}
		}

		return self::default_dispatcher();
	}

	public static function default_dispatcher()
	{
		if (Input::$AJAX && Input::$AJAX['widget']) {
			$widget = Widget::factory(Input::$AJAX['widget']);
			$method = 'on_' . (Input::$AJAX['object'] ?: 'unknown') . '_' . (Input::$AJAX['event'] ?: 'unknown');
			if (method_exists($widget, $method)) {
				Event::bind('system.output', 'Output::AJAX');
				$widget->$method();
			}
			return;
		}

		$args         = Input::args();
		$default_page = Config::get('system.default_page');
		if (!$default_page) {
			$default_page = 'index';
		}

		//从末端开始尝试
		/*
        home/page/edit.1
        home/page/index.php Index_Controller::edit(1)
        home/page/index.php Index_Controller::index('edit', 1)
        home/page.php        Page_Controller::edit(1)
        home/page.php        Page_Controller::index('edit', 1)
         */

		$file = end($args);
		if (!preg_match('/[^\\\\]\./', $file)) {
			//有非法字符的只能是参数
			$path = implode('/', $args);
			// home/page/edit/index => index, NULL
			$candidates[($path ? $path . '/' : '') . $default_page] = array($default_page, null);
			$candidates[$path]                                      = array($file, null); // home/page/edit => edit, NULL
		}

		if ($args) {
			$params            = array_pop($args);
			$file              = $args ? end($args) : $default_page;
			$path              = $args ? implode('/', $args) : $default_page;
			$candidates[$path] = array($file, $params); // home/page.php => page, edit|1
		} else {
			$candidates[$default_page] = array($default_page, null);
		}

		$controlName = reset(Input::args());
		foreach (Config::get("router.{$controlName}", []) as $regxp => $value) {
			// 伪路由
			// 访问home/page/edit.1
			// 如果在config/router.php 中配置了
			// $config['home'] = [
			// 	'^home\/{baz}\/edit' => ['path' => foo, 'file' => bar, 'params' => ['baz']]
			// ];
			// 那么可以加载自定义foo路径下bar, 将['baz' => 'page']作为参数
			// 依次调用foo::file foo::index 方法

			$subject = implode('/', Input::args());
			if ($subject && preg_match($regxp, $subject, $matches)) {
				$candidates[$value['path']] = [$value['file']];
				$matchParams = [];
				if ($value['params']) foreach ($value['params'] as $key) {
					$matchParams[] = $matches[$key];
				}
				$candidates[$value['path']][] = join('.', $matchParams);
				break;
			}
		}

		$class = null;
		foreach ($candidates as $path => $candidate) {
			if (Core::load(CONTROLLER_BASE, $path)) {
				$class  = str_replace(['/', '-'], '_', $path);
				$params = array();
				if (preg_match_all('/(.*?[^\\\\])\./', $candidate[1] . '.', $parts)) {
					foreach ($parts[1] as $part) {
						$params[] = strtr($part, array('\.' => '.'));
					}
				}
				Config::set('system.controller_path', $path);
				Config::set('system.controller_class', $class);
				break;
			}
		}

		if (!$class) {
			URI::redirect('error/404');
		}

		if (Input::$AJAX) {
			$class .= AJAX_SUFFIX;

			if (!class_exists($class, false)) {
				Core::load(CONTROLLER_BASE, 'ajax');
				$class = 'AJAX' . CONTROLLER_SUFFIX;
			}

			$controller = new $class;
			$object     = Input::$AJAX['object'];
			$event      = Input::$AJAX['event'];
			$method     = $params[0];
			if (!$method || $method[0] == '_') {
				$method = 'index_';
			}

			$method .= '_' . ($object ? $object . '_' : '') . $event;
			if (method_exists($controller, $method)) {
				array_shift($params);
			} else {
				$method = 'index_' . ($object ? $object . '_' : '') . $event;
				if (!method_exists($controller, $method)) {
					$method = null;
				}
			}

			if ($method) {
				Controller::$CURRENT = $controller;
				Config::set('system.controller_method', $method);
				Config::set('system.controller_params', $params);
				$controller->_before_call($method, $params);
				call_user_func_array(array($controller, $method), $params);
				$controller->_after_call($method, $params);
			}
		} else {

			$class .= CONTROLLER_SUFFIX;
			$controller = new $class;

			$method = $params[0];
			if ($method && $method[0] != '_' && method_exists($controller, $method)) {
				array_shift($params);
			} elseif ($method && $method[0] != '_' && method_exists($controller, 'do_' . $method)) {
				$method = 'do_' . $method;
				array_shift($params);
			} else {
				$method = 'index';
			}

			Controller::$CURRENT = $controller;
			Config::set('system.controller_method', $method);
			Config::set('system.controller_params', $params);
			$controller->_before_call($method, $params);
			call_user_func_array(array($controller, $method), $params);
			$controller->_after_call($method, $params);
		}
	}
}
