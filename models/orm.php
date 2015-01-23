<?php

final class ORM_Pool {

	const POOL_EXPIRES = 3;
	private static $POOL = array();

	private static function key($oname, $oid) {
		return $oname.'#'.$oid;
	}

	static $cleanup_timeout = 0;
	static function cleanup() {
		$now = time();
		if (self::$cleanup_timeout < $now) {
			foreach((array)self::$POOL as $k => $o) {
				if ($o->ref_expires > $now) {
					unset(self::$POOL[$k]);
				}
			}
			self::$cleanup_timeout = $now + self::POOL_EXPIRES;
		}
	}

	static function get($oname, $oid) {
		$key = self::key($oname, $oid);
		$o = self::$POOL[$key];
		return is_object($o) ? $o->object : NULL;
	}
	
	static function set($oname, $oid, $object, $ref_count=0) {
		$key = self::key($oname, $oid);

		$o = (object) self::$POOL[$key];
		if ($o->object === $object) {
			$o->ref_count += $ref_count;
		}
		else {
			$o->object = $object;
			$o->ref_count = $ref_count;
		}
			
		$o->ref_expires = time() + self::POOL_EXPIRES;
		self::$POOL[$key] = $o;

		self::cleanup();
	}
	
	static function ref($oname, $oid) {
		$key = self::key($oname, $oid);
		$o = self::$POOL[$key];
		if (isset($o)) {
			$o->ref_count++;
			$o->ref_expires = time() + self::POOL_EXPIRES;
		}
	}

	static function unref($oname, $oid) {
		$key = self::key($oname, $oid);
		$o = self::$POOL[$key];
		if (isset($o)) {
			$o->ref_count--;
			if ($o->ref_count <= 0) {
				return self::release($oname, $oid);
			}
			$o->ref_time = time();
			return $o->ref_count;
		}
		return 0;
	}

	static function release($oname, $oid=NULL) {
		if (is_object($oname) && $oid === NULL) {
			$object = $oname;
			$oid = $object->id;
			$oname = $object->name();
		}
		else {
			$object = NULL;
		}

		$key = self::key($oname, $oid);
		$o = self::$POOL[$key];
		if ($object === NULL || $object === $o->object) unset(self::$POOL[$key]);
		return max(0, $o->ref_count);
	}

}


abstract class _ORM_Model {

	const RELA_PREFIX = '_r_';
	const OBJ_NAME_SUFFIX = '_name';
	const OBJ_ID_SUFFIX = '_id';
	const JSON_SUFFIX = '_json';

	private $_name;
	private $_data = array('id'=>0);
	private $_update = array();
	private $_objects = array();
	
	private $_O;	
	function __sleep() {
		$this->_O = array(
			'name'=>$this->_name,
			'id'=>$this->id,
		);
		return array("\0_ORM_Model\0_O");
	}

	function __wakeup() {
		$name = $this->_O['name'];
		$id = $this->_O['id'];
		
		if ( !$name) return;

		$object = self::factory($name, $id);

		$this->_data = $object->_data;
		$this->_objects = array();
		$this->_update = array();
		$this->_name = $object->_name;
		
		$this->init();
	}

	function __clone()
	{
		/* NO.BUG #190 (xiaopei.li@2010.11.26) */
		$extra_data = Properties::factory($this)->data();

		$this->_objects=array();
		$this->_data['id'] = 0;
		$this->_update = array_merge((array)$this->_data, (array)$extra_data);

		$this->init();
	}

	function __call($method, $params) {
		if ($method == __FUNCTION__) return;
		return $this->trigger_event('call.'.$method, $params);
	}

	function __get($name){	
		return $this->get($name);
	}
	
	function __set($name, $value){
		$this->set($name, $value);
	}
	
	function __unset($name) {
		$this->set($name, NULL);
	}

	function __destruct() {
		$this->release_objects();
	}

	function init() {
	}

	static function class_exists($name) {
		$class_name=$name.MODEL_SUFFIX;
		return class_exists($class_name) && is_subclass_of($class_name, 'ORM_Model');
	}
	
	function get($name, $original=FALSE) {
		if (!$original && array_key_exists($name, $this->_update)) return $this->_update[$name];
	
		if (isset($this->_objects[$name])) return $this->_objects[$name];
		
		if (isset($this->_data[$name])) return $this->_data[$name];

		if ($name == 'id') return 0;

		$this_name = $this->name();

		//JSON类型数据反序列化
		$json_keys = self::json_keys($this_name);
		if (isset($json_keys[$name])) {
			$json_key = $name . self::JSON_SUFFIX;
			return @json_decode($this->_data[$json_key],true);
		}

		//如果schema中存在object 则从object_id/object_name获得实例化object
		$object_keys = self::object_keys($this_name);
		if (isset($object_keys[$name])) {
			$attr_oid = $this->_data[$name . self::OBJ_ID_SUFFIX];

			$attr_oname = $object_keys[$name]['oname'];
			if (!$attr_oname) {
				$attr_oname = $this->_data[$name.self::OBJ_NAME_SUFFIX];
			}
			
			if ($attr_oname && self::class_exists($attr_oname)) {
				//尝试从模型对象池中获得对象 防止对象间关联造成的死锁
				$pooled_object = NULL;
				if ($attr_oid) $pooled_object = ORM_Pool::get($attr_oname, $attr_oid);
				if ($pooled_object) {
					ORM_Pool::ref($attr_oname, $attr_oid);
					$this->_objects[$name] = $pooled_object;
				}
				else {
					// 奇怪的问题, 使用self::factory 系统无法通过autoload发现相应类
					$this->_objects[$name] = O($attr_oname, $attr_oid);
				}
				return $this->_objects[$name];
			}

		}

		if ($this->_data['id']) {
			$val = Properties::factory($this)->get($name);
			if ($val !== NULL) return $val;
		}

		$val = $this->trigger_event('get', $name);
		if ($val !== NULL) return $val;

		if (!$this->_data['id'] && method_exists($this, 'default_value')) {
			$val = $this->default_value($name);
			if ($val !== NULL) return $val;
		}

	}
	
	function set($name, $value = NULL){
		if (is_array($name)) {
			array_map(array($this, __FUNCTION__), array_keys($name), array_values($name));
		}
		else {
			if ($value === NULL && is_object($this->_objects[$name])) {
				$o = $this->_objects[$name];
				$this->_update[$name] = O($o->name());
			}
			else {
				$this->_update[$name] = $value;
			}
		}
		return $this;
	}
	
	function & to_assoc(array $keys, array $replaced_keys = NULL) {
		$arr = array();
		foreach($keys as $key) {
			$arr[$key] = $this->get($key);	
		}
		if ($replaced_keys) $arr = array_combine($replaced_keys, $arr);
		return $arr;
	}

	function name ($name=NULL) {
		return $name ? $this->_name = strtolower($name) : $this->_name;
	}
	
	function set_data($data) {
		if (is_array($data)) {
			$name = $this->_name;
			if ($this->_data['id']
				&& $this === ORM_Pool::get($name, $this->_data['id'])) {
				$ref_count = ORM_Pool::release($name, $this->_data['id']);
			}
			else {
				$ref_count = 0;
			}

			if ($data['id']) {
				ORM_Pool::set($name, $data['id'], $this, $ref_count);
			}

            unset($data['_extra']);

			$this->_data = $data;
		}
	}
	
	function get_data() {
		return $this->_data;
	}
	
	function touch() {
		$schema = self::schema($this);
		if (isset($schema['fields']['mtime'])) {
			$this->mtime = Date::time();
		}
		return $this;
	}
	
	private $_event_prefix = NULL;
	private function trigger_event() {
		$args = func_get_args();
		if (!$this->_event_prefix) {
			$this->_event_prefix = array();
			$name = $this->name();
			$real_name = self::real_name($name);
			if ($real_name != $name) {
				$this->_event_prefix[] = $name.'_model.';
			}
			$this->_event_prefix[] = $real_name.'_model.';
			$this->_event_prefix[] = 'orm_model.';
		}
		
		$event_name = array_shift($args);
		$events = array();
		foreach($this->_event_prefix as $prefix) {
			$events[] = $prefix.$event_name;
		}

		array_unshift($args, implode(' ', $events), $this);
		return call_user_func_array('Event::trigger', $args);		
	}
	
	private function _save_data($db, $name, &$data, $overwrite = FALSE) {
		
		$fields = $db->table_fields($name);

		$now = Date::time();
		//设置创建时间
		if(isset($fields['ctime']) && !$this->_data['ctime'] && !$data['ctime']){
			$data['ctime'] = $now;
		}
		//设置修改时间, 仅在第一次生成 之后由touch修改
		if(isset($fields['mtime']) && !$this->_data['mtime'] && !$data['mtime']){
			$data['mtime'] = $now;
		}
	
		$keys = $vals = $args = array();

				
		$args[] = $name;
		foreach($data as $k=>$v){
			$keys[]=$db->quote_ident($k);
			if (is_null($v)) {
			    $vals[] = 'NULL';
			}
			elseif (is_float($v)) {
			    $vals[]= '%f';
			    $args[]= $v;
			}
			elseif (is_bool($v)) {
			    $vals[]= '%d';
			    $args[]= $v ? 1 : 0;
			}
			elseif (is_int($v)) {
			    $vals[]= '%d';
			    $args[]= (int) $v;
			}
			else {
			    $vals[]= '"%s"';
			    $args[]= $v;
			}
		}
		
		$SQL = 'INSERT INTO `%s` ('.implode(',', $keys).') VALUES('.implode(',', $vals).')';

		$id = $data['id'];
		
		if ($overwrite || $id >0) {

			$SQL.=' ON DUPLICATE KEY UPDATE ';

			unset($data['id']);

			$pair = array('id=(@id:=id)');
			foreach($data as $k=>$v){
			    if (is_null($v)) {
			        $val = 'NULL';
			    }
			    elseif (is_float($v)) {
			        $val= '%f';
			        $args[]= $v;
			    }
			    elseif (is_bool($v)) {
			        $val= '%d';
			        $args[]= $v ? 1 : 0;
			    }
			    elseif (is_int($v)) {
			        $val= '%d';
			        $args[]= (int) $v;
			    }
			    else {
			        $val= '"%s"';
			        $args[]= $v;
			    }
				$pair[]='`'.$k.'`='.$val;
			}

			$SQL .= implode(',', $pair);

			$db->query('SET @id:=-1');

		}

		array_unshift($args, $SQL);
		
	    $ds = call_user_func_array(array($db, 'query'), $args);
		if (!$ds) return FALSE;
		
		if($overwrite || $id > 0){
			$s = call_user_func_array(array($db, 'rewrite'), $args);
			$id = $db->value('SELECT IF(@id=-1, LAST_INSERT_ID(), @id)');
		}else{
			$id = $db->insert_id();
		}

		$data['id'] = $id;
		
		return TRUE;
	}
	
	function save($overwrite = FALSE){

		//如果update为空直接返回
		if(!$this->_update) return TRUE;

		if (FALSE === $this->trigger_event('before_save', $this->_update))
			return FALSE;

		$name = $this->name();
		$old_data = array();

		$new_data = $this->_update;

		//序列化$this->_update中的数据
		$field_to_real_name = self::field_to_real_name($name);

		//将对象映像到xxx_name, xxx_id
		$object_keys = self::object_keys($name);
		
		/*
			Cheng.liu@2011.2.16
			序列化表中type为json的键值以便后续进行数据存储
		*/
		$json_keys = self::json_keys($name);
		$data = array();

        $extra_data = array();
		foreach ($new_data as $k=>$v) {
            if ($k == '_extra') continue;
			$old_data[$k] = $this->get($k, TRUE);
			if (isset($object_keys[$k])) {
				$kname = $k . self::OBJ_ID_SUFFIX;
				$rname = $field_to_real_name[$kname];
				$data[$rname][$kname] = is_null($v) ? 0 : (is_null($v->id) ? 0 : $v->id);
				if (!$object_keys[$k]['oname']) {
					$kname = $k . self::OBJ_NAME_SUFFIX;
					$data[$rname][$kname] = is_null($v) ? 'orm' : $v->name();
				}
			}
			elseif (isset($json_keys[$k])) {
				$kname = $k . self::JSON_SUFFIX;
				$rname = $field_to_real_name[$kname];
				$data[$rname][$kname] = @json_encode($v);
			}
			elseif (isset($field_to_real_name[$k])) {
				$rname = $field_to_real_name[$k];
				if (is_scalar($v)) $v = (string) $v;				
			    if ($schema['fields']['null'] && !$v) {
			        $data[$rname][$k] = NULL;
			    }
			    else {
				    $data[$rname][$k] = $v;
				}
			}
			else {
                //附加列
				$extra_data[$k] = $v;
			}
			
		}

		$old_id = $id = $this->_data['id'];
		$db = self::db($name);

		$success = TRUE;
		foreach ($data as $rname => &$d) {
			if ($id) $d['id'] = $id;
			$success = $this->_save_data($db, $rname, $d, $overwrite);
			if (!$success) break;
			if ($d['id']) $id = $d['id'];
		}
		
		if ($success) {
			foreach ($data as $rname => &$d) {
				$this->set_data($d + $this->_data);
			}

		}
		else {
			return FALSE;
		}
		
		if ($id && $id != $old_id) {
			$old_data['id'] = $old_id;
			$new_data['id'] = $id;
		}

		$this->_update = array();
		$this->release_objects();

        //success后，需要同步更新properties数据
        if ($this->id && count($extra_data)) Properties::factory($this)->set($extra_data)->save();

		if (FALSE === $this->trigger_event('saved', $old_data, $new_data)) {
			return FALSE;
		}

        Cache::factory()->remove($this->_cache_name());

		return TRUE;
	}

    function cache_name() {
        return $this->name(). '#'. $this->id;
    }

	
	function delete() {
	
		if ($this->id) {

			if (FALSE === $this->trigger_event('before_delete'))
				return FALSE;

			Properties::factory($this)->delete();

			$db = self::db($this->_name);
			$name = self::real_name($this->_name);
			$id = $this->id;
		
			$db->query('DELETE FROM `%s` WHERE `id`="%s"', $name, $id);

			if (FALSE === $this->trigger_event('deleted'))
				return FALSE;

		}

        Cache::factory()->remove($this->_cache_name());
		
		return TRUE;
	}

	// connect ($object)
	// connect ( array($oname, $oids))
	// connect ( array($oname, $oid))
	// connect ( array($object1, $object2, ....))
	function connect($object, $type=NULL, $approved=FALSE){

		if (is_array($object)) {
			if (count($object) == 2 && is_string($object[0])) {
				if (! $object[0]) return FALSE;
				if (is_array($object[1])) {
					foreach($object[1] as $oid) {
						$this->connect(self::factory($object[0],$oid), $type, $approved);
					}
				}
				else {
					$this->connect(self::factory($object[0],$object[1]), $type, $approved);
				}
			}
			else {
				foreach ($object as $o) {
					$this->connect($o, $type, $approved);
				}
			}
		}
		else {
			$name2 = $object->_name;
			$id2 = $object->id;
		}
		
		if(!$name2 || !$id2) return FALSE;
		
		$name1 = $this->_name;
		$id1 = $this->id;
		
		if (strcmp($name1, $name2) < 0) {
			$type = self::counterpart($type);
			list($name1, $name2) = array($name2, $name1);
			list($id1, $id2) = array($id2, $id1);
		}
		
		$db = self::db($name1);
		$conn_table = self::RELA_PREFIX.$name1.'_'.$name2;

		$db->prepare_table($conn_table,
			array(
				//fields
			'fields' => array(
					'id1'=>array('type'=>'bigint', 'null'=>FALSE),
					'id2'=>array('type'=>'bigint', 'null'=>FALSE),
					'type'=>array('type'=>'varchar(20)', 'null'=>FALSE),
					'approved'=>array('type'=>'int', 'null'=>FALSE, 'default'=>0),
				),
				//indexes
			'indexes' => array(
					'PRIMARY'=>array('type'=>'primary', 'fields'=>array('id1', 'id2', 'type')),
					'id1'=>array('fields'=>array('id1', 'type')),
					'id2'=>array('fields'=>array('id2', 'type')),
					'approved'=>array('fields'=>array('approved')),
				)
			)
		);

		return NULL != $db->query('INSERT INTO `%s` (`id1`, `id2`, `type`, `approved`) VALUES (%d, %d, "%s", %d) ON DUPLICATE KEY UPDATE `approved`=%d', $conn_table, $id1, $id2, $type, $approved, $approved);

	}
	
	function disconnect($object, $type=NULL, $approved=NULL){
		
		if (is_array($object)) {
			if (count($object) == 2 && is_string($object[0])) {
				if (! $object[0]) return;
				if (is_array($object[1])) {
					foreach($object[1] as $oid) {
						$this->disconnect(self::factory($object[0],$oid), $type, $approved);
					}
				}
				else {
					$this->disconnect(self::factory($object[0],$object[1]), $type, $approved);
				}
			}
			else {
				foreach ($object as $o) {
					$this->disconnect($o, $type, $approved);
				}
			}
		}
		else {
			$name2 = $object->_name;
			$id2 = $object->id;
		}
		
		if(!$name2 || !$id2) return;
		
		$name1 = $this->_name;
		$id1 = $this->id;

		if (strcmp($name1, $name2) < 0) {
			$type = self::counterpart($type);
			list($name1, $name2) = array($name2, $name1);
			list($id1, $id2) = array($id2, $id1);
		}
		
		$db = self::db($name1);
		$conn_table=self::RELA_PREFIX.$name1.'_'.$name2;
		
		if($db->table_exists($conn_table)){
			$where = array();
			$where[] = $db->rewrite('`id1`=%d', $id1);
			$where[] = $db->rewrite('`id2`=%d', $id2);
			if ($type != '*') {
				$where[] = $db->rewrite('`type`="%s"', $type);
			}
			if ($approved !== NULL) {
				$where[] = $db->rewrite('`approved`=%d', $approved ? TRUE : FALSE);
			}
			$where = ' WHERE '.implode(' AND ', $where);
			$db->query('DELETE FROM `%s`'.$where, $conn_table);

			$key = Misc::key($db->name(), $name1, $id1, $name2, $id2, $type);
			unset(self::$conn_cache[$key]);
		}
		
	}
	
	function enum_connections($object) {

		$name1 = $this->_name;
		$name2 = $object->_name;
		$id1 = $this->id;
		$id2 = $object->id;
		
		if (strcmp($name1, $name2) < 0) {
			list($name1, $name2) = array($name2, $name1);
			list($id1, $id2) = array($id2, $id1);
		}
		
		$db = self::db($name1);
		$table=self::RELA_PREFIX.$name1.'_'.$name2;
		
		$ret = array();
		$rs = $db->query('SELECT * FROM `%s` WHERE id1 = %d AND id2 = %d', $table, $id1, $id2);
		if ($rs) while ($row = $rs->row('assoc')) {
			$type = $row['type'];
			unset($row['id1']);
			unset($row['id2']);
			unset($row['type']);
			$ret[$type] = $row;
		}
		
		return $ret;
	}

	static function counterpart($type) {
		static $dict, $dict_r;
		if (NULL === $dict) {
			$dict = Config::get('orm.counterpart');
			if (!is_array($dict)) $dict = array();
			$dict_r = array_flip($dict);
		}
		
		if ($dict[$type]) return $dict[$type];
		if ($dict_r[$type]) return $dict_r[$type];
		
		return $type;
	}

	static $conn_cache;
	function connected_with($object, $type='', $approved_only=FALSE){

		$name1 = $this->_name;
		$name2 = $object->_name;
		$id1 = $this->id;
		$id2 = $object->id;
		
		if (strcmp($name1, $name2) < 0) {
			$type = self::counterpart($type);
			list($name1, $name2) = array($name2, $name1);
			list($id1, $id2) = array($id2, $id1);
		}
	
		$db = self::db($name1);
		$key = Misc::key($db->name(), $name1, $id1, $name2, $id2, $type);
		
		if(isset(self::$conn_cache[$key])) return $approved_only? self::$conn_cache[$key] : isset(self::$conn_cache[$key]);

		$table=self::RELA_PREFIX.$name1.'_'.$name2;

		self::$conn_cache[$key] = $db->value('SELECT `approved` FROM `%s` WHERE `id1`=%d AND `id2`=%d AND `type`="%s"', $table, $id1, $id2, $type);

		return $approved_only? self::$conn_cache[$key] : isset(self::$conn_cache[$key]);
	}

	static function & schema($object) {
		
		static $schema = array();
		
		if ($object instanceof ORM_Model) {
			$name = $object->name();
		} else {
			$name = strtolower((string)$object);
		}
		
		$name = self::real_name($name);
		
		if (!$schema[$name]) {
		
			$class_schema = Config::get("schema.{$name}");
			if (count($class_schema['fields']) <= 0) return NULL;

			if (!isset($class_schema['fields']['id'])) {
				$class_schema['fields']['id'] = array('type'=>'bigint', 'null'=>FALSE, 'auto_increment'=>TRUE);
			}

            //增加_extra列
            $class_schema['fields']['_extra'] = array('type'=> 'text', 'null'=> TRUE);

			if (!$class_schema['indexes']) {
				$class_schema['indexes'] = array();
			}
			
			if (!isset($class_schema['indexes']['PRIMARY'])) {
				$class_schema['indexes']['PRIMARY'] = array('type'=>'primary', 'fields'=>array('id'));
			}
						
			$object_keys = array();
			$json_keys = array();
			
			foreach ($class_schema['fields'] as $key => & $data) {
				switch ($data['type']) {
				case 'json':
					$json_key = $key . self::JSON_SUFFIX;
					$fields[$json_key] = array('type'=>'text', 'null'=>TRUE);
					$json_keys[$key] = TRUE;
					break;
				case 'object':
					if ($data['oname']) {
						$object_keys[$key]['oname'] = $data['oname'];
					}
					else {
						$oname = $key.self::OBJ_NAME_SUFFIX;
						$fields[$oname] = array('type'=>'varchar(40)', 'null'=>FALSE, 'default'=>'');
						$object_keys[$key]['convert'][] = $oname;
					}
					$oid = $key.self::OBJ_ID_SUFFIX;
					$fields[$oid] = array('type'=>'bigint', 'null'=>FALSE, 'default'=>0);
					$object_keys[$key]['convert'][] = $oid;
					break;
				default:
					$fields[$key] = $data;
				}
			}
			
			$indexes = $class_schema['indexes'];
			
			if (count($object_keys) > 0) {
				foreach ($indexes as $key => & $index) {
					$key_fields = array();
					foreach ((array) $index['fields'] as $field) {
						if ($object_keys[$field]) {
							array_splice($key_fields, count($key_fields), 0, $object_keys[$field]['convert']);
						} else {
							$key_fields[] = $field;
						}
					}
					$index['fields'] = $key_fields;
				}
			}
			
			$schema[$name] = array('fields' => $fields, 'indexes' => $indexes, 'object_keys'=>$object_keys, 'json_keys'=>$json_keys);

		}
		
		return $schema[$name];
		
	}

	protected function encode_objects(&$data) {
			
		$schema = self::schema($this);
		//如果schema中存在object 则从object_id/object_name获得实例化object
		if ($schema && count($schema['object_keys'])>0) foreach($schema['object_keys'] as $key=>$val) {

			if ($data[$key]) {
				$object = $data[$key];
				
				$oid_key = $key.self::OBJ_ID_SUFFIX;
				$data[$oid_key] = $object->id;
				
				if (!$val['oname']) {
					$oname_key = $key.self::OBJ_NAME_SUFFIX;
					$data[$oname_key] = $object->name();
				}
				
				unset($data[$key]);
			}

		}
		
		return $data;
		
	}

	function __toString() {
		return $this->_name.'#'.$this->id;
	}
	
	static function factory($name, $criteria=NULL, $no_fetch=FALSE) {

		$class_name=$name.MODEL_SUFFIX;
		if (class_exists($class_name) && is_subclass_of($class_name, 'ORM_Model')) {
			$object = new $class_name;
		}
		else {
			$object = new ORM_Model;
		}
		
		$object->name($name);

		if ($criteria) {

			$real_name = self::real_name($name);
			$real_names = array_diff(self::real_names($name), array($real_name));

			if ($no_fetch) {
				$data = (array) $criteria;
			}
			else {

				if (is_scalar($criteria)) {
					$criteria = array('id'=>$criteria);
				}

                //如果传递了 id
                //尝试 cache 获取 $data

                if ($criteria['id']) {
                    $cache_data = Cache::factory()->get($object->_cache_name());

                    if ($cache_data) {
                        $data = $cache_data;
                    }
                }

                if (!$data) {

                    $db = self::db($real_name);

                    $object->encode_objects($criteria);

                    //从数据库中获取该数据
                    foreach ($criteria as $k=>$v) {
                        $where[] = $db->quote_ident($k) . '=' . $db->quote($v);
                    }

                    $schema = self::schema($name);
                    //从schema中得到fields，优化查询
                    $fields = $schema['fields'] ? $db->quote_ident(array_keys($schema['fields'])) : $db->quote_ident('id');

                    // SELECT * from a JOIN b, c ON b.id=a.id AND c.id = b.id AND b.attr_b='xxx' WHERE a.attr_a = 'xxx';
                    $SQL = 'SELECT '.$fields.' FROM '.$db->quote_ident($real_name).' WHERE '.implode(' AND ', $where).' LIMIT 1';

                    $result = $db->query($SQL);
                    //只取第一条记录
                    if ($result) {
                        $data = (array) $result->row('assoc');
                    }
                    else {
                        $data = array();
                    }

                    if ($data['id']) {
                        Cache::factory()->set($object->_cache_name(), $data);
                    }
                }
			}

			$delete_me = FALSE;

			if ($data['id']) {
				$id = $data['id'];
			}
			
			if ($id && count($real_names) > 0) {

				foreach ($real_names as $rname) {
					
					$db = self::db($rname);
					$result = $db->query('SELECT * FROM `%s` WHERE `id`=%d', $rname, $id);
					$d = $result ? $result->row('assoc') : NULL;
					if ($d !== NULL) {
						$data += $d;
					}
					else {
						// 父类数据不存在
						$delete_me = TRUE;
						$delete_me_until = $rname;	//删除到该父类
						break;
					}
				}
				
				if ($delete_me) {
					// 如果父类数据不存在 删除相关数据
					foreach ($real_names as $rname) {
						if ($delete_me_until == $rname) break;
						$db = self::db($rname);
						$db->query('DELETE FROM `%s` WHERE `id`=%d', $rname, $id);
					}
					
					$data = array();
				}
	
			}

			//给object赋值
			$object->set_data($data);

		}
		
		//Object初始化
		$object->init();
		
		return $object;
	}

	static function parent_name($name) {
		static $pn_cache, $suffix;
		if (!isset($suffix)) {
			$suffix = strtolower(MODEL_SUFFIX);
		}
		if (!isset($pn_cache[$name])) {
			$pclass_name = @get_parent_class($name.MODEL_SUFFIX);
			$pname = '';
			if ($pclass_name !== FALSE) {
				$pclass_name = strtolower($pclass_name);
				$pos = strrpos($pclass_name, $suffix);
				if ($pos !== FALSE) {
					$pname = substr($pclass_name, 0, $pos);
				}
			}
			$pn_cache[$name] = $pname;
		}
		return $pn_cache[$name];
	}
	
	static function real_name($name) {
		static $rn_cache;
		if (!isset($rn_cache[$name])) {
			$schema = Config::get("schema.{$name}");
			if ($schema === NULL) {
				$pname = self::parent_name($name);
				$rname = $pname ? self::real_name($pname) : '';
			}
			else {
				$rname = $schema['extends'] ?: $name;
			}
			$rn_cache[$name] = $rname;
			$rn_cache[$rname] = $rname;
		}
		return $rn_cache[$name];
	}
	
	// 获得对象每个对象属性及对应的真实属性名
	static function & object_keys($name) {
		static $ok_cache;

		if (!isset($ok_cache[$name])) {
			$object_keys = array();
			foreach (self::real_names($name) as $rname) {
				$schema = self::schema($rname);
				if (isset($schema['object_keys'])) $object_keys += $schema['object_keys'];
			}
			$ok_cache[$name] = $object_keys;
		}

		return $ok_cache[$name];
	}

	static function & json_keys($name) {
		static $jk_cache;

		if (!isset($jk_cache[$name])) {
			$json_keys = array();
			foreach (self::real_names($name) as $rname) {
				$schema = self::schema($rname);
				if (isset($schema['json_keys'])) $json_keys += $schema['json_keys'];
			}
			$jk_cache[$name] = $json_keys;
		}

		return $jk_cache[$name];
	}

	// 获得对象每个field对应的真实表名
	static function field_to_real_name($name) {
		static $fr_cache;

		if (!isset($fr_cache[$name])) {
			$field_to_real_name = array();
			foreach (self::real_names($name) as $rname) {
				$fields = self::fields($rname);
				$field_to_real_name += array_fill_keys(array_keys($fields), $rname);
			}
			$fr_cache[$name] = $field_to_real_name;
		}

		return $fr_cache[$name];
	}
	
	static function real_names($name) {
		static $rn_cache;

		if (!isset($rn_cache[$name])) {
			$real_names = array();
			$rname = $name;
			do {
				$rname = self::real_name($rname);
				if ($rname == '') break;
				$real_names[] = $rname;
				$rname = self::parent_name($rname);
			}
			while ($rname);
			$rn_cache[$name] = $real_names;
		}

		return $rn_cache[$name];
	}

	static function fields($name, $refresh = FALSE) {
		return self::db($name)->table_fields(self::real_name($name), $refresh);
	}

	static function indexes($name, $refresh = FALSE) {
		return self::db($name)->table_indexes(self::real_name($name), $refresh);
	}
	
	private function release_objects() {
		foreach ($this->_objects as $object) {
			if (!$object->id) continue;
			if ($object === ORM_Pool::get($object->name(), $object->id)) {
				ORM_Pool::unref($object->name(), $object->id);
			}
		}
		$this->_objects = array();
	}
	
	private static $_table_prepared = array();
	static function db($name) {
		$db = Database::factory();
		return $db;
	}
	
	static function refetch($object) {
		return self::factory($object->name(), $object->id);
	}
	
	static function destroy($name) {
		$real_name = self::real_name($name);
		$db = self::db($real_name);
		$db->drop_table($real_name);
		unset(self::$_table_prepared[$real_name]);
		if ($name != $real_name) unset(self::$_table_prepared[$name]);
	}
	
	static function setup() {
	}

}

function O($name, $criteria=NULL, $no_fetch=FALSE) {
	return ORM_Model::factory($name, $criteria, $no_fetch);
}
