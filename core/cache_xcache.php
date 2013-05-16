<?php

class Cache_XCache implements Cache_Handler {

	function setup() {}

	function set($key, $value, $ttl) {
		@xcache_set($key, serialize($value), $ttl);
	}
	
	function get($key) {
		return unserialize(strval(@xcache_get($key)));
	}
	
	function remove($key) {
		return @xcache_unset($key);
	}
	
	function flush() {
	}
	
}

