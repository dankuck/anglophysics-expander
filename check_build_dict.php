<?php

require_once dirname(__FILE__) . '/bootstrappy.php';

if (! file_exists(APP_PATH . '/dict/nouns.txt'))
	require_once APP_PATH . '/build_dict.php';
