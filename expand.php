<?php

require dirname(__FILE__) . '/bootstrappy.php';

if ($argv[1])
	$base_words = preg_split('/\s/', join(' ', array_slice($argv, 1)));
else{
	// figure out php://input
}

$exp = new AnglophysicsExpander($base_words);
$echo = new ConsoleEcho($exp);
$exp->expand(array($echo, 'stepped'));
echo join("\n", $exp->current_words());
