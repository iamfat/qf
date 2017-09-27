<?php

final class Database_MySQL implements Database_Handler {

	private $_existing_tables = NULL;
	private $_prepared_tables = NULL;
	private $_table_fields = NULL;
	private $_table_indexes = NULL;

	private $_info;

	private $_handle;

	function __construct($info){
		
		$this->_info = $info;
		
		$this->connect();
	}
	
	function disconnect() {
		$this->_handle->close();
	}
	
	function connect() {
		$this->_handle = new mysqli(
			$this->_info['host'], 
			$this->_info['user'], $this->_info['password'],
			$this->_info['db'],
			$this->_info['port']
		);
		
		if ($this->_handle->connect_errno) {
            throw new Error_Exception('database connect error');
		} 
		else {
			$this->_handle->set_charset('utf8');
		}

	}
	
	function is_connected() {
		return $this->_handle->connect_errno == 0;
	}

	function escape($s) {
		return $this->_handle->escape_string($s);
	}

	function quote_ident($s){
		if (is_array($s)) {
			foreach($s as &$i){
				$i = $this->quote_ident($i);
			}
			return implode(',', $s);
		}		
		return '`'.$this->escape($s).'`';
	}
	
	function quote($s) {
		if(is_array($s)){
			foreach($s as &$i){
				$i=$this->quote($i);
			}			
			return implode(',', $s);
		}
		elseif (is_bool($s) || is_int($s) || is_float($s)) {
			return $s;
		}
		return '\''.$this->escape($s).'\'';
	}

	function rewrite(){
		$args = func_get_args();	
		$SQL = array_shift($args);
		foreach($args as $k=>&$v){
			if (is_array($v)) {
				$v=$this->quote($v);
			}
			elseif (is_bool($v) || is_int($v) || is_float($v)){
			} 
			else {
				$v=$this->escape($v);
			} 
		}
		return vsprintf($SQL, $args);	
	}
	
	function query($SQL) {
		$retried = 0;
		while (TRUE) {
			$result = @$this->_handle->query($SQL);
			if (is_object($result)) return new DBResult_MySQL($result);
			if ($this->_handle->errno != 2006) break;
			if ($retried > 0) {
				error_log('database gone away!');
				die;
			}
			$this->connect();
			$retried ++;
		}

		return $result;
	}

	function exec($SQL) {
		$retried = 0;
		while (TRUE) {
			$result = @$this->_handle->multi_query($SQL);
			if ($result) return;
			if ($this->_handle->errno != 2006) break;
			if ($retried > 0) {
				error_log('database gone away!');
				die;
			}
			$this->connect();
			$retried ++;
		}
	}

	function insert_id() {
		return @$this->_handle->insert_id;
	}

	function affected_rows() {
		return @$this->_handle->affected_rows;
	}

	function table_exists($tbl_name){
		if(!$this->_existing_tables){
			$this->_existing_tables=array();
			$rs = $this->query('SHOW TABLES');
			while ($r = $rs->row('num')) {
				$this->_existing_tables[$r[0]]=TRUE;
			}
		}
		return isset($this->_existing_tables[$tbl_name]);
	}

	private static function standardize_field_type($type) {
		// 确保小写
		$type = strtolower($type);
		// 移除多余空格
		$type = preg_replace('/\s+/', ' ', $type); 
		// 去除多级整数的长度说明
		$type = preg_replace('/\b(tinyint|smallint|mediumint|bigint|int)\s*\(\s*\d+\s*\)/', '$1', $type);
		
		return $type;
	}
	
	function prepare_table($tbl_name, $schema) {
		$remove_nonexistent = Config::get('database.remove_nonexistent', TRUE);
		
		$fields = (array) $schema['fields'];
		//用indexes
		$indexes = (array) $schema['indexes'];
		
		if(!isset($this->_prepared_tables[$tbl_name])) {
			if (!$this->table_exists($tbl_name)){
				$this->create_table($tbl_name, $fields, $indexes, $schema['engine']);
				$ret = $this->table_exists($tbl_name);
			}
			else {
				$ret = TRUE;
				$field_sql = array();
				//检查所有Fields
				$curr_fields = $this->table_fields($tbl_name);
				$missing_fields = array_diff_key($fields, $curr_fields);
				foreach($missing_fields as $key=>$field) {
					$field_sql[]='ADD '.$this->field_sql($key, $field);
				}
				
				foreach($curr_fields as $key=>$curr_field) {
					$field = $fields[$key];
					if ($field) {
						$curr_type = self::standardize_field_type($curr_field['type']);
						$type = self::standardize_field_type($field['type']);
						if ( $type !== $curr_type
							|| $field['null'] != $curr_field['null']
							|| $field['default'] != $curr_field['default']
							|| $field['auto_increment'] != $curr_field['auto_increment']) {
							$field_sql[] = sprintf('CHANGE %s %s'
								, $this->quote_ident($key)
								, $this->field_sql($key, $field));
						}
					}
					elseif ($remove_nonexistent) {
						$field_sql[] = sprintf('DROP %s', $this->quote_ident($key) );
					}
					/*
					elseif ($key[0] != '@') {
						$nkey = '@'.$key;
						while (isset($curr_fields[$nkey])) {
							$nkey .= '_';
						}

						$field_sql[] = sprintf('CHANGE %s %s'
							, $this->quote_ident($key)
							, $this->field_sql($nkey, $curr_field));
					}
					*/
				}
	
				$curr_indexes = $this->table_indexes($tbl_name);
				$missing_indexes = array_diff_key($indexes, $curr_indexes);
				foreach($missing_indexes as $key=>$val) {
					$field_sql[] = sprintf('ADD %s '
						, $this->alter_index_sql($key, $val));
				}
				
				foreach($curr_indexes as $key=>$curr_val) {
					$val = & $indexes[$key];
					if ($val) {
						if ( $val['type'] != $curr_val['type']
							|| array_diff($val, $curr_val)) {
							$field_sql[]=sprintf('DROP %s, ADD %s'
								, $this->alter_index_sql($key, $curr_val, TRUE)
								, $this->alter_index_sql($key, $val));
						}
					}
					else/*if ($remove_nonexistent)*/ {
						$field_sql[]=sprintf('DROP INDEX %s', $this->quote_ident($key) );
					}
				}
	
				if (count($field_sql)>0) {
					$quote_tbl_name = $this->quote_ident($tbl_name);
					$quote_field_sql = implode(', ', $field_sql);
					$ret = $this->query("ALTER TABLE $quote_tbl_name $quote_field_sql");
				}
			}
			$this->_prepared_tables[$tbl_name] = $ret;
		}
		return $this->_prepared_tables[$tbl_name];
	}

	function table_fields($name, $refresh = FALSE) {
		
		if($refresh || !isset($this->_table_fields[$name])){
			$ds = $this->query($this->rewrite('SHOW FIELDS FROM `%s`', $name));
			$fields=array();
			if($ds) while($field = $ds->row('object')){
				$fields[$field->Field]=array(
					'type'=>$field->Type,
					'default'=>$field->Default,
					'key'=>$field->Key,
					'null'=>$field->Null == 'NO' ? FALSE : TRUE,
					'auto_increment'=>FALSE !== strpos($field->Extra, 'auto_increment'),
				);
			}
			$this->_table_fields[$name] = $fields;
		}

		return $this->_table_fields[$name];
	}

	function table_indexes($name, $refresh = FALSE) {
		
		if ($refresh || !isset($this->_table_indexes[$name])) {
			$ds=$this->query($this->rewrite('SHOW INDEX FROM `%s`', $name));
			$indexes=array();
			if($ds) while($row = $ds->row('object')) {
				$indexes[$row->Key_name]['fields'][] = $row->Column_name;
				if (!$row->Non_unique) {
					$indexes[$row->Key_name]['type'] = $row->Key_name == 'PRIMARY' ? 'primary' : 'unique';
				}
			}
			$this->_table_indexes[$name] = $indexes;
		}

		return $this->_table_indexes[$name];
	}

	private function field_sql($key, &$field) {
		return sprintf('%s %s%s%s%s'
				, $this->quote_ident($key)
				, $field['type']
				, $field['null']? '': ' NOT NULL'
				, isset($field['default']) ? ' DEFAULT '.$this->quote($field['default']):''
				, $field['auto_increment'] ? ' AUTO_INCREMENT':''
				);
	}
	
	private function index_sql($key, &$val, $no_fields = FALSE) {
		switch($val['type']){
		case 'primary':
			$type='PRIMARY KEY';
			break;
		case 'unique':
			$type='UNIQUE KEY '. $this->quote_ident($key);
			break;
		default:
			$type='KEY '. $this->quote_ident($key);
		}
		
		if ($no_fields) {
			return $type;
		}
		else {
			return sprintf('%s (%s)', $type, $this->quote_ident($val['fields']));
		}
	}
	
	private function alter_index_sql($key, &$val, $drop = FALSE) {
		switch($val['type']){
		case 'primary':
			$type='PRIMARY ';
			break;
		case 'unique':
			$type=($drop ? 'INDEX' : 'UNIQUE'). $this->quote_ident($key);
			break;
		default:
			$type='INDEX '. $this->quote_ident($key);
		}
		
		if ($drop) {
			return $type;
		}
		else {
			return sprintf('%s (%s)', $type, $this->quote_ident($val['fields']));
		}
	}
	
	function create_table($tbl_name, $fields, $indexes, $engine = NULL) {
		 
		if ($this->table_exists($tbl_name) || count($fields) <= 0) return FALSE;
		
		if ($engine === NULL) {
			$engine = Config::get('database.default_engine', 'MYISAM');
		}
		
		$field_sql=array();
		$index_sql=array();
		foreach($fields as $key=>$field){
			$field_sql[]= $this->field_sql($key, $field);
		}
		
		if (is_array($indexes)) foreach($indexes as $key=>$val){
			$index_sql[] = $this->index_sql($key, $val);
		}
		
		$SQL = sprintf('CREATE TABLE %s ( %s ) ENGINE = %s DEFAULT CHARSET = utf8', $this->quote_ident($tbl_name), implode(',', array_merge($field_sql, $index_sql)), $engine);
		$rs=$this->query($SQL);
		
		if($rs){
			$this->_existing_tables[$tbl_name]=TRUE;
		}
		
		return $rs !== NULL;
	
	}

	function begin_transaction() {
		@$this->_handle->autocommit(FALSE);
	}
	
	function commit() {
		@$this->_handle->commit();
		@$this->_handle->autocommit(TRUE);
	}
	
	function rollback() {
		@$this->_handle->rollback();
		@$this->_handle->autocommit(TRUE);
	}
	
	function drop_table() {
		$tables = func_get_args();
		$this->query('DROP TABLE '.$this->quote_ident($tables));
		foreach ($tables as $table) {
			unset($this->_existing_tables[$table]);
			unset($this->_prepared_tables[$table]);
			unset($this->_table_fields[$table]);
			unset($this->_table_indexes[$table]);
		}
		
	}
	
	function snapshot($filename, $tables) {
		
		$tables = (array)$tables;
		foreach ($tables as &$table) {
			$table = escapeshellarg($table);
		}
	
		$table_str = implode(' ', $tables);
	
		$dump_command = sprintf('/usr/bin/mysqldump -h %s -u %s %s %s %s > %s', 
				escapeshellarg($this->_info['host']),
				escapeshellarg($this->_info['user']),
				$this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
				escapeshellarg($this->_info['db']),
				$table_str,
				escapeshellarg($filename)
				);	
		exec($dump_command, $output, $ret);
		return $ret == 0;
	}
	
	function empty_database() {
		$rs = $this->query('SHOW TABLES');
		while ($r = $rs->row('num')) {
			$tables[] = $r[0];
		}
		$this->query('DROP TABLE '.$this->quote_ident($tables));
	}
	
	function restore($filename, $tables) {
		
		$import_command = sprintf('/usr/bin/mysql -h %s -u %s %s %s < %s', 
				escapeshellarg($this->_info['host']),
				escapeshellarg($this->_info['user']),
				$this->_info['password'] ? '-p'.escapeshellarg($this->_info['password']) :'',
				escapeshellarg($this->_info['db']),
				escapeshellarg($filename)
				);	
		exec($import_command, $output, $ret);
		
		return $ret == 0;
	}
	
}

class DBResult_MySQL implements DBResult {
	private $_result;
	
	function __construct($result){
		$this->_result=$result;
	}
	
	function rows($mode='object') {
		$rows = array();
		while ($row = $this->row($mode)) {
			$rows[] = $row;
		}
		return $rows;
	}
	
	function row($mode='object'){
		if ($mode == 'assoc') {
			return $this->_result->fetch_assoc();
		}elseif ($mode == 'num') {
			return $this->_result->fetch_row();
		}elseif ($mode == 'object') {
			return $this->_result->fetch_object();
		}		
		return $this->_result->fetch_array(MYSQL_BOTH);
	}
	
	function count(){
		return is_object($this->_result) ? $this->_result->num_rows : 0;
	}

	function value(){
		$r = $this->row('num');
		if(!$r)return NULL;
		return $r[0];
	}
	
	function object(){
		$r = $this->row('object');
		if(!$r)return NULL;
		return $r;
	}
	
	function __destruct(){
		if (is_object($this->_result)) $this->_result->free();
	}

}
