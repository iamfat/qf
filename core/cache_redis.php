<?php

if (!defined('REDIS_HOST')) define('REDIS_HOST', '127.0.0.1');
if (!defined('REDIS_PORT')) define('REDIS_PORT', 6379);

class Cache_Redis implements Cache_Handler {

    private $redis;

    function setup() {

        $redis = new Redis;
        $redis->connect(REDIS_HOST, REDIS_PORT);
        $this->redis = $redis;

    }

    function set($key, $value, $ttl) {
        return $this->redis->set($key, @json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $ttl);
    }

    function get($key) {
        return @json_decode($this->redis->get($key), true);
    }

    function remove($key) {
        return $this->redis->delete($key);
    }

    function flush() {
        return $this->redis->flush();
    }
}
