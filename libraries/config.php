<?php

abstract class _Config {

	static $items = array();

	static function setup(){}

	private static function _load($category, $filename) {
		if (!isset(self::$items[$category])) self::$items[$category] = array();

		$config = & self::$items[$category];
		$config['#ROOT'] = & self::$items;
		include($filename);
		unset($config['#ROOT']);
	}

	static function load($path, $category=NULL){
		if ($category) {
			self::_load($category, $path.CONFIG_BASE.$category.EXT);
		}
		else {
			$files = glob($path.CONFIG_BASE.'*'.EXT);
			foreach ($files as $file) {
				if (!is_file($file)) continue;
				$category = basename($file, EXT);
				self::_load($category, $file);
			}
		}
	}
	
	static function shutdown(){
	}

	static function export() {
		return self::$items;
	}

	static function import(& $items){
		self::$items = $items;
	}

	static function clear() {
		self::$items = array();	//清空
	}
	
	static function & get($key, $default=NULL){
		list($category, $key) = explode('.', $key, 2);
		if (!$key) return self::$items[$category];
		$val = self::$items[$category][$key];
		
		if(isset($val)) return $val;
		return $default;
	}

	static function set($key, $val){
		list($category, $key) = explode('.', $key, 2);
		if ($key) {
			if ($val === NULL) {
				unset(self::$items[$category][$key]);
			}
			else {
				self::$items[$category][$key]=$val;
			}
		}
		else {
			if ($val === NULL) {
				unset(self::$items[$category]);
			}
			else {
				self::$items[$category];
			}
		}
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
