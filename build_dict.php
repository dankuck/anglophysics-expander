<?php

require_once dirname(__FILE__) . '/bootstrappy.php';

system('aspell --lang=en create master ' . APP_PATH . '/dict/nouns < ' . APP_PATH . '/dict/nouns.txt');

