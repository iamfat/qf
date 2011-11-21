<?php

class _Tabs_Widget extends Widget {
	
	function __construct($vars = array()){
		if (!is_array($vars)) $vars = array();

		$vars += array(
			'tabs'=>array(),
		);
		parent::__construct('tabs', $vars);
	}

	function sort_tabs() {
		$tabs = $this->vars['tabs'];
		$order = array();
		$weight = array();
		$i = 0;
		foreach ($tabs as $k => $v) {
			$order[$k] = $i++;
			$weight[$k] = $tabs[$k]['weight'];
		}

		uksort($tabs, function($ak, $bk) use($weight, $order) {
			$aw = $weight[$ak];
			$bw = $weight[$bk];

			if ($aw != $bw) {
				return $aw - $bw;
			}
			return $order[$ak] - $order[$bk];
		});
		
		$this->vars['tabs'] = $tabs;
	}

	function add_tab($tid, $data){

		$this->vars['tabs'][$tid] = $data;
		
		//tabs排序
		$this->sort_tabs();
		
		return $this;

	}
	
	function get_tab($tid) {
		return $this->vars['tabs'][$tid];
	}
	
	function set_tab($tid, $tab) {
		$this->vars['tabs'][$tid] = $tab;
		$this->sort_tabs();
		return $this;
	}

	function select($tid){

		if ($this->tab_event) {
			Event::trigger($this->tab_event, $this);
		}
		
		if($this->vars['tabs']) {

			if (!isset($this->vars['tabs'][$tid])){
				$tid = key($this->vars['tabs']);
			}
			$this->vars['tabs'][$tid]['active'] = TRUE;
			$this->vars['selected'] = $tid;
		
			if ($this->content_event) {
				Event::trigger_one($this->content_event, $tid, $this);
			}
		
		}
		
		return $this;
	}
	
	private $tab_event;
	private $content_event;


	function content_event($name) {
		$this->content_event = $name;
		return $this;
	}
	
	function tab_event($name) {
		$this->tab_event = $name;
		return $this;
	}
	
}
