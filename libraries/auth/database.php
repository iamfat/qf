<?php

abstract class _Auth_Database implements Auth_Handler {

	private $db_name;
	private $table;
	private $options;

	function __construct(array $opt){

		$this->options = $opt;
		$this->db_name = $opt['database.name'];
		$this->table = $opt['database.table'] ?: '_auth';

		$db = Database::factory($this->db_name);		
		if(!$db->table_exists($this->table)){
			$fields = array(
				'token'=>array('type'=>'varchar(80)', 'null'=>FALSE, 'default'=>''),
				'password'=>array('type'=>'varchar(32)', 'null'=>FALSE, 'default'=>''),				
			);
			$indexes = array(
				'primary'=>array('type'=>'primary', 'fields'=>array('token')),
			);
			$db->create_table(
				$this->table, 
				$fields, $indexes,
				$opt['database.engine']
			);
		}
	}
	
	private static function encode($password){
		return md5($password);
	}
	
	function verify($token, $password){
		$db = Database::factory($this->db_name);
		return NULL != $db->value('SELECT `token` FROM `%s` WHERE `token`="%s" AND `password`="%s"', $this->table, $token, self::encode($password));
	}
	
	function change_password($token, $password){
		$db = Database::factory($this->db_name);
		return FALSE != $db->query('UPDATE `%s` SET `password`="%s" WHERE `token`="%s"', $this->table, self::encode($password), $token);
	}
	
	function change_token($token, $token_new){
		$db = Database::factory($this->db_name);
		return FALSE != $db->query('UPDATE `%s` SET `token`="%s" WHERE `token`="%s"', $this->table, $token_new, $token);
	}
	
	function add($token, $password){
		$db = Database::factory($this->db_name);
		return FALSE != $db->query('INSERT INTO `%s` (`token`, `password`) VALUES("%s", "%s")', $this->table, $token, self::encode($password));
	}
	
	function remove($token){
		$db = Database::factory($this->db_name);
		return FALSE != $db->query('DELETE FROM `%s` WHERE `token`="%s"', $this->table, $token);
	}
	
}
