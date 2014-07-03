<?php

require_once dirname(__FILE__) . '/bootstrappy.php';

system('aspell --lang=en create master ' . APP_ROOT . '/dict/nouns < ' . APP_ROOT . '/dict/nouns.txt');

