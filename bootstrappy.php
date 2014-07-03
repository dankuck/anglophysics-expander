<?php

define('APP_ROOT', dirname(__FILE__));

function autoload_default($class){
	$subpath = preg_replace('/\_/', '/', $class);
	$path = APP_ROOT . '/classes/' . $subpath . '.php';
	if (file_exists($path)){
		require $path;
	}
}

spl_autoload_register('autoload_default');

