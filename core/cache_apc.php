<?php

class Cache_APC implements Cache_Handler {

	function setup() {}

	function set($key, $value, $ttl) {
		return @apc_store($key, serialize($value), $ttl);
	}
	
	function get($key) {
		return @unserialize(strval(@apc_fetch($key)));
	}
	
	function remove($key) {
		return @apc_delete($key);
	}
	
	function flush() {
		//@apc_clear_cache();
	}
	
}

