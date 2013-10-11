<?php

class Cache_NoCache implements Cache_Handler {

	function setup() {}

	function set($key, $value, $ttl) {
	}
	
	function get($key) {
		return false;
	}
	
	function remove($key) {
	}
	
	function flush() {
	}
	
}

