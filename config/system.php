<?php

$config['locale']='zh_CN';

$config['default_page'] = 'index';

$scheme = $_SERVER['HTTPS'] ? 'https':'http';
$config['base_url'] = $scheme.'://'.$_SERVER['HTTP_HOST'].preg_replace('/[^\/]*$/', '', $_SERVER['SCRIPT_NAME']);
$config['script_url'] = $scheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'/';

$config['enable_hooks'] = TRUE;

// $config['session_handler'] = 'buildin';
$config['session_lifetime'] = 0;	//浏览器关闭
$config['session_path'] = '/';
$config['session_domain'] = NULL;

$config['tmp_dir'] = sys_get_temp_dir().'/qf/';

$config['session_name'] = 'qfsession';

// $config['24hour'] = FALSE;
