<?php

abstract class _I18N {

	protected static $locale;
	protected static $items;
	protected static $bases;

	static function setup() {
		self::$locale = Config::get('system.locale', 'zh_CN');
 	}
 	
 	static function get_items(){
 		return self::$items;
 	}
 	
 	static function shutdown(){
 		self::$items = NULL;
 	}
	
	static function HT($domain=self::ALL_DOMAIN, $str, $args=NULL, $options=NULL, $convert_return=FALSE) {
		return Output::H(self::T($domain, $str, $args, $options), $convert_return);
	}
	
	static function T($domain=self::ALL_DOMAIN, $str, $args=NULL, $options=NULL) {
		if(is_array($str)){
			foreach($str as &$s){
				$s = self::T($domain, $s, $args, $options);
			}
			return $str;
		}
		$options['domain'] = $domain;
		return T($str, $args, $options);
	}
	
	static function convert($str, $options=NULL) {
		$options = (array) $options;
		
		$domain = $options['domain'] ?: NULL;
		
		self::load_domain($domain);
		
		//分离 翻译模块|:子分类
		/*
		成果	    = achievements
		成果|:title = Achievements
		成果|:short = Achvments
		
		*/

		$converted_str = self::$items[$domain][$str];
		if (isset($converted_str)) {
			return (string) $converted_str;
		}
		else {
			list($str, $sub) = explode("|:", $str, 2);
			$converted_str = self::$items[$domain][$str];
			if (isset($converted_str)) return (string) $converted_str;
		}
		
		return $str;
	}
	
	static function load_domain($domain) {
		if (!isset(self::$items[$domain])) {
			$cache = Cache::factory('memcache');
			$locale = self::$locale;
			$cache_key = Misc::key('i18n', $domain, $locale);
			$lang = Config::get('debug.i18n_nocache') ? NULL : $cache->get($cache_key);
			if ($lang === NULL) {
				foreach (array_reverse(Core::file_paths(I18N_BASE.$locale.EXT, $domain)) as $path) {
					if (file_exists($path)) include $path;
				}
				$lang = (array) $lang;
				$cache->set($cache_key, $lang);
			}
			self::$items[$domain] = (array) $lang;
		}
	}
	
	static function add_base($base) {
		self::$bases[$base] = $base;
	}
	
}
