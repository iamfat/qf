<?php

class Cache_XCache implements Cache_Handler {

	function setup() {}

	function set($key, $value, $ttl) {
        if (!ini_get('xcache.var_size')) return;
		@xcache_set($key, serialize($value), $ttl);
	}
	
	function get($key) {
        if (!ini_get('xcache.var_size')) return FALSE;
		return unserialize(strval(@xcache_get($key)));
	}
	
	function remove($key) {
        if (!ini_get('xcache.var_size')) return;
		return @xcache_unset($key);
	}
	
	function flush() {
	}
	
}

