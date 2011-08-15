<?php

abstract class _Config {

	static $items = array();

	static function setup(){
		Config::load(SYS_PATH);
		Config::load(APP_PATH);
	}

	private static function _load($category, $filename, $do_not_overlap) {
		$config['#ROOT'] = self::$items;
		include($filename);
		unset($config['#ROOT']);

		if (!self::$items[$category]) self::$items[$category] = array();
		if ($do_not_overlap) {
			Misc::array_merge_deep($config, self::$items[$category]);
			self::$items[$category] = $config;
		}
		else {
			Misc::array_merge_deep(self::$items[$category], $config);
		}
	}

	static function load($path, $do_not_overlap = FALSE){
		$dh = @opendir($path.CONFIG_BASE);
		if($dh) {
			while (FALSE!==($name=readdir($dh))) {
				if($name[0]=='.') continue;
				if(!preg_match('/'.EXT.'$/', $name)) continue;
				$filename = $path.CONFIG_BASE.$name;
				if (is_file($filename)) {
					$category = preg_replace('/'.EXT.'$/','', $name);
					self::_load($category, $filename, $do_not_overlap);
				}
			}
			closedir($dh);
		}
	}
	
	static function shutdown(){
	}

	static function import(& $items){
		self::$items=array_merge(self::$items, $items);
	}
	
	static function & get($key, $default=NULL){
		list($category, $key) = explode('.', $key, 2);
		if (!$key) return self::$items[$category];
		$val = self::$items[$category][$key];
		
		if(isset($val)) return $val;
		return $default;
	}

	static function set($key, $val=NULL){
		list($category, $key) = explode('.', $key, 2);
		if ($val === NULL) unset(self::$items[$category][$key]);
		self::$items[$category][$key]=$val;
	}
	
	static function append($key, $val){
		list($category, $key) = explode('.', $key, 2);
		if (self::$items[$category][$key] === NULL) {
			self::$items[$category][$key] = $val;
		} 
		elseif (is_array(self::$items[$category][$key])) {
			self::$items[$category][$key][] = $val;
		}
		else {
			self::$items[$category][$key] .= $val;
		}
	}

}
