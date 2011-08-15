<?php

class Cache_APC implements Cache_Handler {

	function setup() {}

	function set($key, $value, $ttl) {
		return @apc_store($key, serialize($value), $ttl);
	}
	
	function get($key) {
		$ret = @unserialize(strval(@apc_fetch($key)));
		if ($ret === FALSE) return NULL;
		return $ret;
	}
	
	function remove($key) {
		return @apc_delete($key);
	}
	
	function flush() {
		//@apc_clear_cache();
	}
	
}

