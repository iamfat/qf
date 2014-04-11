<?php

abstract class _Properties {
	
	private $_items;
	private $_object;
	
	private $_updated=FALSE;

	private static $_cache=array();
		
	static function setup(){}
	
	function __construct($object){
		
		$this->_object = $object;

		$name = $object->name();
		$id = $object->id;
		
		$db = ORM_Model::db($name);

		//$data = @unserialize(@base64_decode($code)?:$code);
		// TO BE REMOVED: 这两种形式 哪种兼容性更好一些? Jia Huang @ 2010.12.19
		//

        $this->_items = @json_decode($db->value('SELECT `_extra` FROM `%s` WHERE `id`=%d', $name, $id), TRUE);
	}
	
	function & __get($name) {
		return $this->get($name);
	}
	
	function __set($name, $value){
		$this->set($name, $value);
	}
	
	function get($name) {
		if(isset($this->_items[$name])) return $this->_items[$name];
		return NULL;
	}

	function data()
	{
		/* NO.BUG #190 (xiaopei.li@2010.11.26) */
		return $this->_items;
	}
	
	function set($name, $value=NULL) {
		if (is_array($name)) {
			array_map(array($this, __FUNCTION__), array_keys($name), array_values($name));
		} else {
			if($value===NULL){
				unset($this->_items[$name]);
			}else{
				$this->_items[$name] = $value;
			}
			$this->_updated = TRUE;
		}
		return $this;
	}
	
	function delete() {
		$name = $this->_object->name();
		$db = ORM_Model::db($name);

		$id = $this->_object->id;

        $db->query('UPDATE `%s` SET `_extra` = NULL WHERE `id` = %d', $name, $id);

		return $this;
	}
	
	function save(){
		if($this->_updated){
			$name = $this->_object->name();

            //修正表结构
            $db = ORM_Model::db($name);

            $id = $this->_object->id;

            $db->query('INSERT INTO `%1$s` (`id`, `_extra`) VALUES (%2$d, "%3$s") ON DUPLICATE KEY UPDATE `_extra`="%3$s"', $name, $id, @json_encode($this->_items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

			$this->_updated = FALSE;
			
		}
		return $this;
	}
	
	static function factory($object) {
		
		if (!($object instanceof ORM_Model) || !$object->id) {
			throw new Error_Exception(T('无法识别的对象!'));
		}
		
		if (defined('CLI_MODE')) return new Properties($object);

		$key = (string) $object;
		if (!self::$_cache[$key]) {
			self::$_cache[$key] = new Properties($object);
		}
		
		return self::$_cache[$key];
	}

	function object() {
		return $this->_object;
	}

}

function P($object) {
	return Properties::factory($object);
}
