<?php

abstract class _View {

	protected $vars=array();
	protected $parent;
	protected $path;

	function __construct($path, $vars=NULL){
		
		$this->path = $path;
		if (is_array($vars)) {
			$this->vars = array_merge($this->vars, $vars);
		}

	}
	
	static function factory($path, $vars=NULL) {
		return new View($path, $vars);
	}
	
	static function setup() {
	}

	//返回子View
	function __get($key){
		return $this->vars[$key];
	}

	function __set($key, $value){
		if ($value === NULL) {
			if($value instanceof _View){
				$value->parent = NULL;
			}
			unset($this->vars[$key]);
		} else {
			$this->vars[$key] = $value;
			if($value instanceof _View){
				$value->parent = $this;
			}
		}
	}

	function __unset($key) {
		unset($this->vars[$key]);
	}

	function __isset($key) {
		return isset($this->vars[$key]);
	}
		
	function ob_clean(){
		unset($this->_ob_cache);
		return $this;
	}
	
	//返回View内容
	private $_ob_cache;
	
	private function _include_view($_path, $_vars) {
		if ($_path) {
			ob_start();
			extract($_vars);

			@include($_path);

			$output = ob_get_contents();
			ob_end_clean();
		}
		
		return $output;
	}
	
	function __toString(){

		if ($this->_ob_cache !== NULL) return $this->_ob_cache;
		
		//从$path里面获取category;
		list($category, $path) = explode(':', $this->path, 2);
		if (!$path) {
			$path = $category;
			$category = NULL;
		}

		$event = $category ? "view[{$category}:{$path}].prerender ":'';
		$event .= "view[{$path}].prerender view.prerender";

		Event::trigger($event, $this);

		$v = $this;
		$_vars = array();
		while ($v) {
			$_vars += $v->vars;
			$v = $v->parent;
		}

		$locale = Config::get('system.locale');
		
        if (isset($GLOBALS['view_map']) && is_array($GLOBALS['view_map'])) {
            $view_map = $GLOBALS['view_map'];
            if ($category && isset($view_map["$category:$path@$locale"])) {
                $_path = $view_map["$category:$path@$locale"];
            } elseif ($category && isset($view_map["$category:$path"])) {
                $_path = $view_map["$category:$path"]);
            } elseif (isset($view_map["$path@$locale"])) {
                $_path = $view_map["$path@$locale"]);
            } elseif (isset($view_map[$path])) {
                $_path = $view_map[$path]);
            }
        } else {
    		$_path = Core::file_exists(VIEW_BASE.'@'.$locale.'/'.$path.VEXT, $category);
    		if (!$_path) {
    			$_path=Core::file_exists(VIEW_BASE.$path.VEXT, $category);
    		}
        }

        if ($_path) {
    		$output = $this->_include_view($_path, $_vars);
        }

		$event = $category ? "view[{$category}:{$path}].postrender ":'';
		$event .= "view[{$path}].postrender view.postrender";
		
		$new_output = (string) Event::trigger($event, $this, $output);

		$output = $new_output ?: (string) $output;

		return $this->_ob_cache = $output;
					
	}
	
	function set($name, $value=NULL){
		if(is_array($name)){
			array_map(array($this, __FUNCTION__), array_keys($name), array_values($name));
			return $this;
		} else {
			$this->$name=$value;
		}
		
		return $this;
	}
	
	function render(){		
		echo $this;
	}
	
	function embed($view){
		$view = $view instanceof View ? $view: V($view);
		$view->parent = $this;
		return $view;
	}

}

function V($path, $vars=NULL) {
	return View::factory($path, $vars);
}
