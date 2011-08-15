<?php

abstract class _Layout_Controller extends Controller {
	
	public $layout;
	public $layout_name = 'layout';
	
	function _before_call($method, &$params) {
		parent::_before_call($method, $params);
		
		unset($_SESSION['page.vars']);	# 初始化页面变量
		
		$this->layout = V($this->layout_name);

		$this->add_js((array) Config::get('page.head_js'), TRUE);
		$this->add_js((array) Config::get('page.body_js'), FALSE);
		
		$this->add_css((array) Config::get('page.css'));
		
	}

	function _after_call($method, &$params) {
		parent::_after_call($method, $params);		
		$this->layout->controller = $this;
		Event::bind('system.output', array($this->layout, 'render'));
	}
	
	private $loaded_js;
	
	private $head_js;
	private $body_js;
	
	// add_js(array($js1, $js2), $top = TRUE);
	function add_js($js_ser, $head = TRUE, $mode = NULL) {
		
		if (is_array($js_ser)) {
			foreach ($js_ser as $j) {
				$this->add_js($j, $head);
			}
			
			return $this;
		}

		$js_ser = trim($js_ser);
		if (!$js_ser) return $this;

		$js_ser_arr = explode(' ', $js_ser);
		
		foreach ($js_ser_arr as $k => $js) {
			if ($this->loaded_js[$js]) {
				unset($js_ser_arr[$k]);
			}
			else {
				$this->loaded_js[$js] = TRUE;
			}
		}
		
		if (count($js_ser_arr) > 0) {
			$js_ser = implode(' ', $js_ser_arr);
			if ($head) {
				$this->head_js[] = array('file'=>$js_ser, 'mode'=>$mode);
			}
			else {
				$this->body_js[] = array('file'=>$js_ser, 'mode'=>$mode);
			}
		}

		return $this;
	}
	
	private $loaded_css;
	private $css;
	
	function add_css($css_ser) {
		
		if (is_array($css_ser)) {
			foreach ($css_ser as $c) {
				$this->add_css($c);
			}
			
			return $this;
		}
		
		$css_ser_arr = explode(' ', $css_ser);
		
		foreach ($css_ser_arr as $k => $css) {
			if (isset($this->loaded_css[$css])) {
				unset($css_ser_arr[$k]);
			}
			else {
				$this->loaded_css[$css] = TRUE;
			}
		}
		
		if (count($css_ser_arr) > 0) {
			$css_ser = implode(' ', $css_ser_arr);
			$this->css[] = $css_ser;
		}

		return $this;
	}
	
	function load_css() {
		$output = '';
		foreach ((array) $this->css as $f) {
			if (FALSE === strpos($f, '://')) {
				$url = CSS::cache_file($f);
			}
			else {
				$url = $f;
			}
			$output .='<link href="'.H($url).'" rel="stylesheet" type="text/css" />';
		}
		return $output;
	}
	
	function load_js($head = TRUE) {
		$js_ser_arr = $head ? $this->head_js : $this->body_js;
		return $js_ser_arr ? JS::load_sync($js_ser_arr) : '';
	}

	/* NO.BUG#242(xiaopei.li@2010.12.15) */
	function require_log_in(){
		URI::redirect('error/401');
	}
}
