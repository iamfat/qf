<?php

abstract class _ORM_Iterator implements Iterator, ArrayAccess, Countable {

	protected $db;

	protected $name;
	
	protected $current_id;
	protected $objects = array();
	
	protected $count;		//符合selector的数据总数
	protected $length;	//实际获得数据数
	
	protected $SQL;
	protected $count_SQL;

	function total_count(){return (int) $this->check_query(TRUE)->count;}
	function length(){return $this->count();}
	function name(){return $this->name;}

	function __construct($name, $SQL, $count_SQL=NULL, $db=NULL){
	
		$this->name = $name;
		$this->SQL = $SQL;
		$this->db = $db instanceof Database ? $db : Database::factory($db);

		if (!$count_SQL) {
			$count_SQL = preg_replace('/\bSQL_CALC_FOUND_ROWS\b/', '', $SQL);
			$count_SQL = preg_replace('/^(SELECT)\s(.+?)\s(FROM)\s/', '$1 COUNT($2) count $3', $count_SQL);
			$count_SQL = preg_replace('/ COUNT\((.+?)\.\*\) count/', ' COUNT($1.id) count', $count_SQL);
			$count_SQL = preg_replace('/\sORDER BY.+$/', '', $count_SQL);
			$count_SQL = preg_replace('/\sLIMIT.+$/', '', $count_SQL);
		}

		$this->count_SQL = $count_SQL;
		
		$this->check_query();
		
	}
	
	protected $is_fetched=FALSE;
	protected $is_counted=FALSE;
	protected function check_query($count_only=FALSE){
		if ($this->is_fetched) return $this;
		if ($count_only && $this->is_counted) return $this;
		
		$name = $this->name;
		$db = $this->db;
		
		if(!$this->is_counted) {
			$this->count = $this->count_SQL ? $db->value($this->count_SQL) : 0;
			$this->length = 0;
			$this->is_counted=TRUE;
		}
		
		if (!$count_only && !$this->is_fetched) {

			if ($this->SQL) {
				$result = $db->query($this->SQL);
				
				$objects = array();

				if ($result) {
					while ($row=$result->row('assoc')) {
						$objects[$row['id']] = O($name, $row['id']);
					}
				}
			
				$this->objects = $objects;
				$this->length = count($objects);
				$this->current_id = key($objects);
			}
	
			$this->is_fetched = TRUE;
		}
		

		return $this;

	}
	
	function delete_all() {
		$this->check_query();
		foreach ($this->objects as $object) {
			if (!$object->delete()) return FALSE;
		}
		return TRUE;
	}
	
	function sum($name) {
		$this->check_query();
		$sum = 0;
		foreach($this->objects as $object) {
			$sum += $object->$name;
		}
		return $sum;
	}
	
	// Iterator Start
	function rewind(){
		$this->check_query();
		reset($this->objects);
		$this->current_id = key($this->objects);
	}
	
	function current(){ 
		$this->check_query();
		return $this->objects[$this->current_id]; 
	}
	
	function key(){
		$this->check_query();
		return $this->current_id;
	}
	
	function next(){
		$this->check_query();
		next($this->objects);
		$this->current_id = key($this->objects);
		return $this->objects[$this->current_id];
	}
	
	function valid(){
		$this->check_query();
		return isset($this->objects[$this->current_id]);
	}
	// Iterator End

	// Countable Start
	function count(){
		return $this->check_query()->length;
	}
	// Countable End
	
	// ArrayAccess Start
	function offsetGet($id){
		$this->check_query();
		if($this->length>0){
			return $this->objects[$id];
		}
		return NULL;
	}
	
	function offsetUnset($id){
		$this->check_query();
		unset($this->objects[$id]);
		$this->count = $this->length = count($this->objects);
		if ($this->current_id==$id) $this->current_id = key($this->objects);
	}
	
	function offsetSet($id, $object){
		$this->check_query();
		$object->id=$id;
		$this->objects[$id]=$object;
		$this->count = $this->length = count($this->objects);
		$this->current_id=$id;
	}
	
	function offsetExists($id){
		$this->check_query();
		return isset($this->objects[$id]);
	}

	// ArrayAccess End

	function prepend($object){
		$this->check_query();

		if(is_array($object)){
			$object = O($this->name, $object, TRUE);
		}
		elseif ($object instanceof ORM_Iterator) {
			$object = $object->objects[$object->current_id];
		}
		
		if ($object instanceof ORM_Model && $object->id) {
			$this->objects = array($object->id => $object) + $this->objects;
		}
		
		$this->count = $this->length = count($this->objects);
		return $this;
	}

	function append($object){
		$this->check_query();

		if (is_array($object)) {
			$object = O($this->name, $object, TRUE);
		}
		elseif ($object instanceof ORM_Iterator) {
			$object = $object->objects[$object->current_id];
		}
		
		if ($object instanceof ORM_Model && $object->id) {
			$this->objects[$object->id] = $object;
		}
		
		$this->count = $this->length = count($this->objects);
		return $this;
	}
	
	function __toString() {
		return $this->name.'::__toString()';
	}
	
	function reverse() {
		$this->check_query();
		//反排
		$this->objects = array_reverse($this->objects);
		return $this;
	}

	function & to_assoc($key_name = 'id', $val_name = 'name') {
		$this->check_query();
		$assoc = array();
		foreach($this->objects as $o) {
			$assoc[$o->$key_name] = $o->$val_name;
		}
		return $assoc;
	}
	
}
