<?php

/**
 *
 * @package    Core
 * @author     黄嘉
 * @copyright  (c) 2009 基理科技
 */

final class Core {
	
	static $PATHS = array();
	static $MODULE_BASES = array(); 
	static $G = array();
	static $LEGAL_MODULES;
	
	static function normalize_path($path) {
		if (FALSE === strpos($path, 'phar://')) {
			//尝试替换成phar
			$phar_path = dirname($path).'/'.basename($path, '.phar').'.phar';
			if (file_exists($phar_path)) {
				return 'phar://'.$phar_path.'/';
			}
		}
		
		return $path;
	}
	
	static function include_path($name, $path) {
		$path = self::normalize_path($path);
		unset(Core::$PATHS[$path]);
		Core::$PATHS = array($path=>$name) + Core::$PATHS;
	}

	static function module_path($module) {

		foreach (self::$MODULE_BASES as $base) {

			$mbase = $base.MODULE_BASE;

			$path = $mbase.$module;
			if (file_exists($path.'.phar')) {
				return 'phar://'.$path.'.phar/'; 
			}

			if (file_exists($path)) {
				return $path.'/';
			}

		}

		return $path.'/';
	}

	static function _check_mod_deps($a, $b) {
		if (in_array($a->name, $b->deps)) {
			return -1;
		}
		elseif (in_array($b->name, $a->deps)) {
			return 1;
		}
		return 0;
	}

	static function _module_deps($name, $path) {
		$dep_path = $path.'config/depmod'.EXT;
		@include($dep_path);
		return (array) $config[$name];
	}

	private static $_module_paths;
	static function _module_paths($base, $prefix=NULL) {

		$paths = self::$_module_paths[$base];
		if (!isset($paths)) {
			$paths = array();

			$mbase = $base.MODULE_BASE;

			$phars = glob($mbase.'*.phar');
			$excluded = array();
			foreach($phars as $phar) {
				$name = basename($phar, '.phar');
				$k = 'phar://'.$phar.'/';
				$paths[$k] = (object) array(
					'name' => $name,
					'deps' => Core::_module_deps($name, $k)
				);
				$excluded[$name] = TRUE;
			}

			$dirs = glob($mbase.'*', GLOB_ONLYDIR);
			foreach ($dirs as $dir) {
				$name = basename($dir);
				if (isset($excluded[$name])) continue;
				$k = $dir.'/';
				$paths[$k] = (object) array(
					'name' => $name,
					'deps' => Core::_module_deps($name, $k)
				);
			}

			//进行优先级排序
			uasort($paths, 'Core::_check_mod_deps');

			foreach ($paths as $k => $o) {
				$paths[$k] = $o->name;
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

	static function module_paths($prefix = NULL) {
		//搜索所有的module目录
		$paths = array();

		foreach (self::$MODULE_BASES as $base) {
			$paths += self::_module_paths($base, $prefix);
		}

		return $paths;
	}

	static function set_legal_modules($mods) {
		if (!self::$_module_paths) return;
		foreach (self::$_module_paths as $base => &$paths) {
			if ($paths) foreach ($paths as $p => $n) {
				if (!isset($mods[$n])) {
					unset($paths[$p]);
					unset(self::$PATHS[$p]);
				}
			}
		}

	}

	static function include_modules($base) {
		self::$MODULE_BASES[$base] = $base;
		self::$PATHS = array_reverse(self::_module_paths($base, '@')) + self::$PATHS;	
	}
	
	static function setup(){

		spl_autoload_register('Core::autoload');

		mb_internal_encoding('utf-8');
		mb_language('uni');

		self::include_path('system', SYS_PATH);

		//加载所有module的路径
		self::include_modules(ROOT_PATH);

		self::include_path('application', APP_PATH);

		Config::setup();

		Config::load(SYS_PATH);
		Config::load(APP_PATH);

		Input::setup();
		Output::setup();
		
		View::setup();
	}

	static function reload_config() {
		Config::clear();
		foreach (array_reverse(Core::$PATHS) as $p=>$n) {
			Config::load($p);
		}
	}
	
	static function bind_events() {	
		foreach ((array) Config::get('hooks') as $event => $hooks){
			$hooks = array_unique((array)$hooks);
			foreach ($hooks as $callback) {
				if(is_array($callback) && isset($callback['callback'])) {
					$weight = (int) $callback['weight'];
					$callback = $callback['callback'];
					$key = $callback['key'];
				}
				else {
					$weight = 0;
					$key = NULL;
				}
				Event::bind($event, $callback, $weight, $key);
			}
		}		
	}

	static function shutdown(){
		Output::shutdown();
		Input::shutdown();
		Config::shutdown();
	}

	//定义用于提取类文件名的正则表达类型
	static function autoload($class){
		
		//定义类后缀与类路径的对应关系
		static $CLASS_BASES = array(
			MODEL_SUFFIX => MODEL_BASE,
			WIDGET_SUFFIX => WIDGET_BASE,
			CONTROLLER_SUFFIX => CONTROLLER_BASE,
			AJAX_SUFFIX => CONTROLLER_BASE,
			'*' => LIBRARY_BASE
		);
		
		static $CLASS_PATTERN;

		if ($CLASS_PATTERN === NULL) {
			$SUFFIX1 = CONTROLLER_SUFFIX.'|'.AJAX_SUFFIX.'|'.VIEW_SUFFIX;
			$SUFFIX2 = MODEL_SUFFIX.'|'.WIDGET_SUFFIX;
		
			$CLASS_PATTERN = "/^(_)?(\w+?)(?:({$SUFFIX1})?|({$SUFFIX2})?)$/i";
		}


		$class = strtolower($class);
		$nocache = FALSE;
		if (preg_match($CLASS_PATTERN, $class, $parts)) {
	
			list(, $is_core, $name, $suffix1, $suffix2) = $parts;
	
			if ($suffix1) {
				$bases = $CLASS_BASES[$suffix1];
				$need_traverse = FALSE;
				$nocache = TRUE;
			} 
			elseif ($suffix2) {
				$bases = $CLASS_BASES[$suffix2];
				$need_traverse = TRUE;
			} 
			else {
				$bases = $CLASS_BASES['*'];
				$need_traverse = TRUE;
			}
	
			$scope = $is_core ? 'system': ($need_traverse ? '*' : NULL);

			if (!$nocache) {
				$cacher = Cache::factory();
				$cache_key = 'autoload_path:'.$class;
				$file = $cacher->get($cache_key);
				if ($file) {
					require_once($file);
					return;
				}
			}

			/*
			多重路径寻址 A_B_C
			a/b/c.php
			a/b_c.php
			a_b_c.php
			*/	
			$units = explode('_', $name);
			$paths[] = implode('/', $units);
			$rest = array_pop($units);
			while (count($units)>0) {
				$paths[] = implode('/', $units) . '_' . $rest;
				$rest = array_pop($units).'_'.$rest;
			}

			$file = Core::load($bases, $paths, $scope);
			if (class_exists($class, false)) {
				//缓冲类到文件的索引
				$nocache or $cacher->set($cache_key, $file);
			}
			elseif (!$is_core) {
				//如果不存在原始类 加载代用类
				$sub_paths = array();
				foreach ($paths as $path) {
					$sub_paths[] = '@/'.$path;
				}
				$file = Core::load($bases, $sub_paths, 'system');
				if (!$nocache && class_exists($class, false)) {
					$cacher->set($cache_key, $file);
				}
			}
		
		}
	
	}
	
	static function load($base, $name, $scope=NULL) {
		if (is_array($base)) {
			foreach($base as $b){
				$file = Core::load($b, $name, $scope);
				if ($file) return $file;
			}
		}
		elseif (is_array($name)) {
			foreach($name as $n){
				$file = Core::load($base, $n, $scope);
				if ($file) return $file;
			}
		}
		else {
			$file = Core::file_exists($base.$name.EXT, $scope);
			if ($file) {
				require_once($file);
				return $file;
			}
		}
		return FALSE;
	}
	
	static function file_exists($path, $scope=NULL) {

		foreach (self::file_paths($path, $scope) as $path) {
			if (file_exists($path)) return $path;
		}

		return NULL;

	}

	static function file_paths($path, $scope=NULL) {
		if ($scope) {
			$skip_implicit_modules = FALSE;
			if ($scope === '*') $scope = NULL;
			else {
				if (!is_array($scope)) $scope = explode(',', $scope);
				$scope = array_flip($scope);
			}
		}
		else {
			$skip_implicit_modules = TRUE;
		}

		$paths = array();	
		$has_scope = is_array($scope) && count($scope)>0;
		foreach (Core::$PATHS as $base=>$name) {
			if ($name[0] === '@') {
				if ($skip_implicit_modules === TRUE) continue;
				if ($has_scope) $name = substr($name, 1); //remove first @ in $name
			}
			if ($has_scope && !isset($scope[$name])) {
				continue;
			}
			$paths[] = $base.$path;
		}
		
		return $paths;
	}
	
	private static $mime_dispatchers = array();
	
	static function register_mime_dispatcher($mime, $dispatcher) {
		self::$mime_dispatchers[$mime] = $dispatcher;
	}
	
	static function dispatch() {
		
		$accepts = explode(',', $_SERVER['HTTP_ACCEPT']);
		while (NULL !== ($accept = array_pop($accepts))) {
			list($mime,) = explode(';', $accept, 2);
			$dispatcher = self::$mime_dispatchers[$mime];
			if ($dispatcher) {
				return call_user_func($dispatcher);
			}
		}
		
		return self::default_dispatcher();	
	}
	
	static function default_dispatcher() {
		if (Input::$AJAX && Input::$AJAX['widget']) {
			$widget = Widget::factory(Input::$AJAX['widget']);
			$method = 'on_'.(Input::$AJAX['object']?:'unknown').'_'.(Input::$AJAX['event']?:'unknown');
			if (method_exists($widget, $method)) {
				Event::bind('system.output', 'Output::AJAX');
				$widget->$method();
			}
			return;
		}

		$args = Input::args();
		$default_page = Config::get('system.default_page');
		if (!$default_page) $default_page = 'index';

		//从末端开始尝试
		/*
			home/page/edit.1
			home/page/index.php Index_Controller::edit(1)
			home/page/index.php Index_Controller::index('edit', 1)
			home/page.php		Page_Controller::edit(1)
			home/page.php		Page_Controller::index('edit', 1)
		*/
		
		$file = end($args);
		if(!preg_match('/[^\\\]\./', $file)){
			//有非法字符的只能是参数
			$path = implode('/', $args);
			// home/page/edit/index => index, NULL
			$candidates[($path ? $path.'/':'').$default_page] = array($default_page, NULL);	
			$candidates[$path] = array($file, NULL);	// home/page/edit => edit, NULL
		}
		
		if($args) {
			$params = array_pop($args);
			$file = $args ? end($args) : $default_page;
			$path = $args ? implode('/', $args) : $default_page;
			$candidates[$path] = array($file, $params);			// home/page.php => page, edit|1
		} else {
			$candidates[$default_page] = array($default_page, NULL);
		}

		$class = NULL;
		foreach($candidates as $path => $candidate){
			if(Core::load(CONTROLLER_BASE, $path)){
				$class = mb_convert_case($candidate[0], MB_CASE_TITLE);
				$params = array();
				if(preg_match_all('/(.*?[^\\\])\./', $candidate[1].'.', $parts)){
					foreach($parts[1] as $part) {
						$params[] = strtr($part, array('\.'=>'.'));
					}
				}
				Config::set('system.controller_path', $path);
				Config::set('system.controller_class', $class);
				break;
			}
		}
		
		if (!$class) URI::redirect('error/404');

		if (Input::$AJAX) {
			$class .= AJAX_SUFFIX;
			if (class_exists($class, false)) {
				$controller = new $class;
				$object = Input::$AJAX['object'];
				$event = Input::$AJAX['event'];
				$method = $params[0];
				if(!$method || $method[0]=='_'){
					$method = 'index_';
				}

				$method .='_'.( $object ? $object.'_':'') . $event;
				if(method_exists($controller, $method)){
					array_shift($params);
				} else {
					$method = 'index_'.( $object ? $object.'_':'') . $event;
					if(!method_exists($controller, $method)) $method = NULL;
				}
				
				if ($method) {
					Controller::$CURRENT = $controller;
					Config::set('system.controller_method', $method);
					Config::set('system.controller_params', $params);
					$controller->_before_call($method, $params);
					call_user_func_array(array($controller, $method), $params);
					$controller->_after_call($method, $params);
				}
			}
		} 
		else {

			$class .= CONTROLLER_SUFFIX;
			$controller = new $class;

			$method = $params[0];
			if($method && $method[0]!='_' && method_exists($controller, $method)){
				array_shift($params);
			} 
			elseif ($method && $method[0]!='_' && method_exists($controller, 'do_'.$method)) {
				$method = 'do_'.$method;
				array_shift($params);
			}
			else {
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
