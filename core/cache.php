<?php

interface Cache_Handler {
	function setup();
	function get($key);
	function set($key, $value, $ttl);
	function remove($key);
	function flush();
}

final class Cache {
	
	private $handler;

	static $CACHE_PREFIX;

	static function normalize_key($key) {
		return md5(self::$CACHE_PREFIX.':'.$key);
	}

	/*
	function log() {
		if (defined('QF_DEBUG')) {
			$args = func_get_args();
			error_log(call_user_func_array('sprintf', $args));
		}
	}
	*/

	function set($key, $value, $ttl=0) {
		if (!$this->handler) return FALSE;
		$key = self::normalize_key($key);
		//$this->log('cache::set(%s, %s)', $key, @json_encode($value));
		return $this->handler->set($key, $value, $ttl); 
	}
	
	function get($key) {
		if (!$this->handler) return NULL;
		$key = self::normalize_key($key);
		$value = $this->handler->get($key); 
		//$this->log('cache::get(%s) = %s', $key, @json_encode($value));
		return $value;
	}
	
	function remove($key) {
		if (!$this->handler) return FALSE;
		$key = self::normalize_key($key);
		//$this->log('cache::remove(%s)', $key);
		return $this->handler->remove($key); 
	}
	
	//清空缓冲
	function flush() {
		if ($this->handler) $this->handler->flush(); 
	}
	
	function __construct($name=NULL) {
		$class = 'Cache_'.$name;
		if (!class_exists($class, FALSE)) {
			require(dirname(__FILE__).'/'.strtolower($class).EXT);
		}
		$this->handler = new $class;
		$this->handler->setup();
	}

	private static $_caches;
	static function factory($name=NULL) {
		if ($name === NULL) $name = DEFAULT_CACHE;
		if (!isset(self::$_caches[$name])) {
			self::$_caches[$name] = new Cache($name);
		}
		return self::$_caches[$name];
	}

	static $locals = array();
	static function L($key, $value) {
		self::$locals[$key] = $value;
	}

	/**
		* @brief 根据路径返回缓冲路径名
		*
		* @param $path 原始路径
		*
		* @return 相对于DOC_ROOT的缓冲路径
	 */
	static function cache_filename($path) {
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		return 'cache/'.hash('md4', self::$CACHE_PREFIX . $path).'.'.$ext;
	}

	/**
		* @brief 缓冲内容到某原始路径对应的缓冲文件，无视该路径是否存在
		*
		* @param $path 原始路径
		* @param $content 缓冲的内容
		*
		* @return 无
	 */
	static function cache_content($path, $content) {
		$cache_file = self::cache_filename($path);
		$cache_path = ROOT_PATH.PUBLIC_BASE.$cache_file;
		$dir = dirname($cache_path);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($cache_path, $content);
	}

	/**
	 * @brief 返回相对于系统公共目录的缓冲路径, 如果该缓冲文件不存在
	 * 则复制文件到该处
	 * @param $path 原始路径
	 * @param $recache 是否无论如何都重新复制缓冲文件
	 *
	 * @return 相对DOC_ROOT的缓冲路径
	 */
	static function cache_file($path, $recache = FALSE) {
		$cache_file = self::cache_filename($path);
		$cache_path = ROOT_PATH.PUBLIC_BASE.$cache_file;
		if ($recache || !file_exists($cache_path)) {
			$dir = dirname($cache_path);
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			copy($path, $cache_path);
		}
		return $cache_file;
	}

	/**
		* @brief 移除某路径的缓冲文件
		*
		* @param $path: 相应的路径
		*
		* @return 无
	 */
	static function remove_cache_file($path) {
		$cache_file = self::cache_filename($path);
		$cache_path = ROOT_PATH.PUBLIC_BASE.$cache_file;
		@unlink($cache_path);
	}
}

function L($key) {
	return Cache::$locals[$key];
}

Cache::$CACHE_PREFIX = hash('md4', ROOT_PATH).':';
