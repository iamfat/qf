<?php

if (!defined('MEMCACHE_HOST')) define('MEMCACHE_HOST', '127.0.0.1');
if (!defined('MEMCACHE_PORT')) define('MEMCACHE_PORT', 11211);

class Cache_Memcache implements Cache_Handler {

	private $memcache;
	private $memcached = FALSE;

	function setup() {
		
		if (!$this->memcache) {
			if (class_exists('Memcache', FALSE)) {
				$memcache = new Memcache;
				$memcache->connect(MEMCACHE_HOST, MEMCACHE_PORT);
				$this->memcache = $memcache;
			}
			elseif (class_exists('Memcached', FALSE)) {
				$memcache = new Memcached(CACHE_PREFIX);
				$memcache->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
				$memcache->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_PHP);
				if (0 == count($memcache->getServerList())) {
					$memcache->addServer(MEMCACHE_HOST, MEMCACHE_PORT);
				}
				$this->memcached = TRUE;
				$this->memcache = $memcache;
			}
		}
		
	}

	function set($key, $value, $ttl) {
		if (!$this->memcache) return FALSE;
		if ($this->memcached) {
			return $this->memcache->set($key, $value, $ttl);
		}
		else {
			if (FALSE === $this->memcache->replace($key, $value, 0, $ttl)) {
				return $this->memcache->set($key, $value, 0, $ttl);
			}
			return FALSE;
		}
	}
	
	function get($key) {
		if (!$this->memcache) return FALSE;
		return $this->memcache->get($key);
	}
	
	function remove($key) {
		if (!$this->memcache) return FALSE;
		return $this->memcache->delete($key);
	}
	
	function flush() {
		if ($this->memcache) $this->memcache->flush();
	}
	
}

