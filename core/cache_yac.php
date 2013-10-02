<?php

class Cache_YAC implements Cache_Handler {

	private $_yac;

	function setup() {
		$this->_yac = new Yac();
	}

	function set($key, $value, $ttl) {
		$this->_yac->set($key, $value, $ttl);
	}
	
	function get($key) {
		$ret = $this->_yac->get($key);
		if ($ret === FALSE) return NULL;
		return $ret;
	}
	
	function remove($key) {
		return $this->_yac->delete($key);
	}
	
	function flush() {
		$this->_yac->flush();
	}
	
}

