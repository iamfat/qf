<?php

interface Database_Handler {
	function __construct($info);
	function disconnect();
	function escape($s);
	function quote($s);
	function quote_ident($s);
	function query($SQL);
	function exec($SQL);
	function insert_id();
	function affected_rows();
	function table_exists($tbl);
	function prepare_table($tbl, $schema);
	function table_fields($name, $refresh);
	function table_indexes($name, $refresh);
	function create_table($tbl, $fields, $indexes, $engine);
	function begin_transaction();
	function commit();
	function rollback();
	function snapshot($filename, $tbls);
	function empty_database();
	function drop_table();
	function restore($filename, $tables);
}

interface DBResult {
	function rows($mode/*='object'*/);
	function row($mode/*='object'*/);
	function count();
	function value();
}

final class Database {

	static $DB = array();
	static $query_count = 0;
	static $cache_hits = 0;

	const TIMEOUT_VAL = 2;
	public $timeout = 0;

	private $_handle;
	private $_url;
	private $_info;
	
	private $_name;

	static function & factory($name=NULL) {
	
		if(!$name) $name = Config::get('database.default');
	
		if(!isset(self::$DB[$name])){
			$url = Config::get('database.'.$name.'.url');
			if (!$url) {
				$dbname = Config::get('database.'.$name.'.db');
				if (!$dbname) $dbname = Config::get('database.prefix') . $name;
				$url = strtr(Config::get('database.root'), array('%database' => $dbname));
			}
			self::$DB[$name] = new Database($url);
			self::$DB[$name]->name($name);
		}
	
		return self::$DB[$name];
	}	
	
	static function shutdown($name=NULL) {
		if (!$name) $name = Config::get('database.default');
	
		if (isset(self::$DB[$name])) {
			self::$DB[$name]->disconnect();
			unset(self::$DB[$name]);
		}
	}

	static function reset() {
		foreach ((array) self::$DB as $name => $db) {
			$db->disconnect();
		}
		self::$DB = array();
	}

	static function cleanup_timeout() {
		$now = time();
		$dbs = (array) self::$DB;
		foreach ($dbs as $name => $db) {
			if ($db->timeout < $now) {
				$db->disconnect();
				unset(self::$DB[$name]);
			}
		}
	}
	
	function __construct($url=NULL){
		$this->_url = $url;
		$url = parse_url($url);

		$this->_info['handler'] = $url['scheme'];	
		$this->_info['host']= urldecode($url['host']);
		$this->_info['port'] = (int)$url['port'];
		$this->_info['db'] = substr(urldecode($url['path']), 1);
		$this->_info['user'] = urldecode($url['user']);
		$this->_info['password']  = isset($url['pass']) ? urldecode($url['pass']) : NULL;
		
		$this->timeout = time() + self::TIMEOUT_VAL;

		$this->connect();
	}

	function info() {
		return $this->_info;
	}
	
	function disconnect() {
		$this->_handle->disconnect();
	}
	
	function connect() {
		$handler = 'Database_'.$this->_info['handler'];
		$this->_handle = new $handler($this->_info);
	}
	
	function name($name = NULL) { return is_null($name) ? $this->_name : $this->_name = $name; }
	
	function url() { return $this->_url; }

	function is_connected() {
		return $this->_handle->is_connected();
	}

	function escape($s) {
		return $this->_handle->escape($s);
	}

	function make_ident() {
		$args = func_get_args();
		$ident = array();
		foreach($args as $arg) {
			$ident[] = $this->quote_ident($arg);
		}
		return implode('.', $ident);
	}
	
	function quote_ident($s){
		return $this->_handle->quote_ident($s);
	}
	
	function quote($s) {
		return $this->_handle->quote($s);
	}
	
	function rewrite(){
		$args=func_get_args();	
		$SQL=array_shift($args);
		foreach($args as $k=>&$v){
			if (is_bool($s) && is_numeric($s)){
			} 
			elseif (is_string($v) && !is_numeric($v)) {
				$v=$this->escape($v);
			} 
			elseif (is_array($v)){
				$v=$this->quote($v);
			}
		}
		return vsprintf($SQL, $args);	
	}

	function query() {
		
		$args=func_get_args();
		if(func_num_args()>1){
			$SQL=call_user_func_array(array($this, 'rewrite'), $args);
		}else{
			$SQL=$args[0];
		}
		//去掉不必要的换行符
		$SQL = preg_replace('/[\n\r\t]+/', ' ', $SQL);
	
		if (Config::get('debug.database', FALSE)) { 
			Log::add($SQL, 'database');
		}
			
		self::$query_count++;

		$this->timeout = time() + self::TIMEOUT_VAL;

		return $this->_handle->query($SQL);
	}

	function exec($SQL) {
		return $this->_handle->exec($SQL);
	}

	function insert_id() {
		return $this->_handle->insert_id();
	}

	function affected_rows() {
		return $this->_handle->affected_rows();
	}

	function value() {
		$args=func_get_args();
		$result = call_user_func_array(array($this,'query'), $args);
		return $result ? $result->value():NULL;
	}
	
	function table_exists($tbl_name) {
		return $this->_handle->table_exists($tbl_name);
	}

	function prepare_table($tbl_name, $schema) {
		return $this->_handle->prepare_table($tbl_name, $schema);
	}

	function table_fields($name, $refresh = FALSE) {
		return $this->_handle->table_fields($name, $refresh);
	}

	function table_indexes($name, $refresh = FALSE) {
		return $this->_handle->table_indexes($name, $refresh);
	}

	function create_table($tbl_name, $fields, $indexes, $engine = NULL) {
		if ($this->table_exists($tbl_name) || count($fields) <= 0) return FALSE;
		return $this->_handle->create_table($tbl_name, $fields, $indexes, $engine);	
	}

	private $_trans_in_progress = FALSE;
	function begin_transaction() {
		$this->_handle->begin_transaction();
		$this->_trans_in_progress = TRUE;
	}
	
	function commit() {
		if ($this->_trans_in_progress) {
			$this->_handle->commit();
			$this->_trans_in_progress = FALSE;
		}
	}
	
	function rollback() {
		if ($this->_trans_in_progress) {
			$this->_handle->rollback();
			$this->_trans_in_progress = FALSE;
		}
	}
	
	function snapshot($filename, $tables = NULL) {

		if (is_string($tables)) $tables = array($tables);
		else $tables = (array)$tables;
		
		return $this->_handle->snapshot($filename, $tables);
	}
	
	function empty_database() {
		return $this->_handle->empty_database();
	}

	function drop_table() {
		$args = func_get_args();
		return call_user_func_array(array($this->_handle, 'drop_table'), $args);
	}

	function restore($filename, &$retore_filename=NULL, $tables=NULL) {
		$retore_filename = $filename.'.restore'.uniqid();
		if (!$this->snapshot($retore_filename)) return FALSE;
		
		if (is_string($tables)) $tables = array($tables);
		else $tables = (array)$tables;

		if (count($tables) > 0) {
			foreach ($tables as $table) {
				$this->drop_table($table);
			}
		}
		else {
			$this->empty_database();
		}
		
		return $this->_handle->restore($filename, $tables);
	}
	
}
