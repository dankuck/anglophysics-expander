<?php

require_once dirname(__FILE__) . '/bootstrappy.php';

if (! file_exists(APP_ROOT . '/dict/nouns.txt'))
	require_once APP_ROOT . '/build_dict.php';
