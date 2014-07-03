<?php

require dirname(__FILE__) . '/bootstrappy.php';

system('aspell --lang=en create master ./dict/nouns < ./dict/nouns.txt');

