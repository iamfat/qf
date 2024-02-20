<?php

abstract class _Event
{
    public static $events = array();

    private $queue = array();
    private $sorted = false;
    private $name;
    private $debug;

    public $return_value;
    public $next;

    private $stop_propagation;

    private function _sort()
    {
        uasort($this->queue, function ($a, $b) {
            if ($a['weight'] != $b['weight']) {
                return $a['weight'] > $b['weight'];
            }

            return $a['order'] > $b['order'];
        });
        $this->sorted = true;
    }

    public function __construct($name)
    {
        $this->name = $name;
    }

    public static $trigger_stack = 0;
    protected function _trigger()
    {

        if (!$this->sorted) {
            $this->_sort();
        }

        $args = func_get_args();
        array_unshift($args, $this);

        foreach ($this->queue as &$hook) {
            if ($this->debug) {
                $prefix  = self::$trigger_stack === 0 ? 'event: ' : '       ' . implode('', array_fill(0, self::$trigger_stack, '  '));
                error_log($prefix . $this->name . ' > ' . @json_encode($hook['callback']));
                self::$trigger_stack++;
            }
            $success = call_user_func_array($hook['callback'], $args);
            if ($this->debug) {
                self::$trigger_stack--;
                $indent = '       ' . implode('', array_fill(0, self::$trigger_stack, '  '));
				if (false === $success) {
					$this->stop_propagation = true;
					error_log($indent . '  aborted.');
					break;
				} else {
					error_log($indent . '  done.');
				}
            } else if (false === $success) {
                $this->stop_propagation = true;
                break;
            }
        }
    }

    protected function _trigger_one()
    {
        if (!$this->sorted) {
            $this->_sort();
        }

        $args = func_get_args();
        $key = $args[0];
        $args[0] = $this;

        $hook = $this->queue[$key];
        if (isset($hook)) {
            if (false === call_user_func_array($hook['callback'], $args)) {
                $this->stop_propagation = true;
            }
        }
    }

    protected function _bind($callback, $weight = 0, $key = null)
    {

        $event = array('weight' => $weight, 'callback' => $callback);

        if (!$key) {
            if (is_string($callback)) {
                $key = $callback;
            } else {
                $class = $callback[0];
                if (is_object($class)) {
                    $class = get_class($class);
                }
                $key = $class . '.' . $callback[1];
            }
        }

        $key = strtolower($key);
        if (!isset($this->queue[$key])) {
            $event['order'] = count($this->queue);
        }
        $this->queue[$key] =  &$event;
    }

    public static function factory($name, $ensure = true)
    {
        $e = Event::$events[$name];
        if (!$e && $ensure) {
            $e = Event::$events[$name] = new Event($name);
        }
        return $e;
    }

    public static function &extract_names($selector)
    {
        return is_array($selector) ? $selector : explode(' ', $selector);
    }

    public static function bind($selector, $callback, $weight = 0, $key = null)
    {
        foreach (Event::extract_names($selector) as $name) {
            Event::factory($name)->_bind($callback, $weight, $key);
        }
    }

    protected static function &call_wrapper($selector, $method, &$params)
    {
        $retval = null;
        foreach (Event::extract_names($selector) as $name) {
            $e = Event::factory($name, false);
            if ($e) {
                $e->stop_propagation = false;
                $e->return_value = $retval;
                $e->debug = !!Config::get('debug.event:' . $name);
                call_user_func_array(array($e, $method), $params);
                $retval = $e->return_value;
                if ($e->stop_propagation) {
                    if ($retval === null) {
                        $retval = false;
                    }
                    break;
                }
                $e->debug = false;
            }
        }
        return $retval;
    }

    public static function trigger()
    {
        $args = func_get_args();
        $selector = array_shift($args);
        return Event::call_wrapper($selector, '_trigger', $args);
    }

    public static function trigger_one()
    {
        $args = func_get_args();
        $selector = array_shift($args);
        return Event::call_wrapper($selector, '_trigger_one', $args);
    }
}
