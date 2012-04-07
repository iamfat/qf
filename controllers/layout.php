<?php

abstract class _Layout_Controller extends Controller {
	
	public $layout;
	protected $layout_name = 'layout';
	
	function _before_call($method, &$params) {
		parent::_before_call($method, $params);
		
		unset($_SESSION['page.vars']);	# 初始化页面变量
		
		$this->layout = V($this->layout_name);

		$this->add_js('jquery json livequery form');
		$this->add_js('q/core q/loader q/ajax q/browser');

		$this->add_css('reset text core');
		
	}

	function _after_call($method, &$params) {
		parent::_after_call($method, $params);		
		$this->layout->controller = $this;
		Event::bind('system.output', array($this->layout, 'render'));
	}
	
	private $loaded_js;
	
	protected $head_js;
	protected $body_js;
	
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
	
	protected $loaded_css;
	protected $css;
	
	function add_css($css_ser, $media=NULL) {
		
		if (is_array($css_ser)) {
			foreach ($css_ser as $c) {
				$this->add_css($c, $media);
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
			if ($media) {
				$this->css[] = array('file'=>$css_ser, 'media'=>$media);
			}
			else {
				$this->css[] = $css_ser;
			}
		}

		return $this;
	}
	
	function load_css() {
		$output = '';
		foreach ((array) $this->css as $f) {
			if (is_array($f)) {
				$media = $f['media'];
				$f = $f['file'];
			}
			else {
				$media = NULL;
			}

			if (FALSE === strpos($f, '://')) {
				$url = CSS::cache_file($f);
			}
			else {
				$url = $f;
			}

			if ($media) {
				$output .='<link href="'.H($url).'" rel="stylesheet" type="text/css" media="'.$media.'"/>';
			}
			else {
				$output .='<link href="'.H($url).'" rel="stylesheet" type="text/css" />';
			}
		}
		return $output;
	}
	
	function load_js($head = TRUE) {
		$js_ser_arr = $head ? $this->head_js : $this->body_js;
		return $js_ser_arr ? JS::load_sync($js_ser_arr) : '';
	}

}
