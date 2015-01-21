<?php

if (!defined('REDIS_HOST')) define('REDIS_HOST', '127.0.0.1');
if (!defined('REDIS_PORT')) define('REDIS_PORT', 6379);

abstract class _Session_Redis implements Session_Handler {

    private $redis;

    static $SESSION_PREFIX = 'session';

    static function normalize_key($key) {
        return md5(self::$SESSION_PREFIX. ':'. $key);
    }

    function __construct() {

        $redis = new Redis;
        $redis->connect(REDIS_HOST, REDIS_PORT);

        $this->redis = $redis;

    }

    function read($id) {

        $key = self::normalize_key($id);

        $val = $this->redis->hmGet($key, [
            'data',
            'mtime',
        ]);

        if ($val) {
            $this->redis->hSet($key, 'mtime', Date::time());
        }

        return $val['data'];
    }

    function write($id, $data) {


        $key = self::normalize_key($id);

        $this->redis->sAdd('session', $key);

        return $this->redis->hmSet($key, [
            'data'=> $data,
            'mtime'=> Date::time(),
        ]);

    }

    function destroy($id) {

        $key = self::normalize_key($id);

        $this->redis->sRem('session', $key);

        return $this->redis->delete($key);

    }

    function gc($max_life_time) {

        if ($max_life_time == 0) return TRUE;

        $exp_time = Date::time() - $max_life_time;

        foreach($this->redis->sMembers('session') as $key) {
            $val = $this->redis->hmGet($key, [
                'data',
                'mtime',
            ]);

            if ($val) {
                if ($val['mtime'] <= $exp_time) {
                    $this->redis->delete($key);
                    $this->redis->sRem('session');
                }
            }
            else {
                $this->redis->sRem('session', $key);
            }

        }

        return TRUE;
    }
}
