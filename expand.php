<?php

require_once dirname(__FILE__) . '/bootstrappy.php';

if ($argv[1])
	$base_words = preg_split('/\s/', join(' ', array_slice($argv, 1)));
else{
	// figure out php://input
}

$exp = new AnglophysicsExpander($base_words, 4);
$echo = new ConsoleEcho($exp);
$exp->set_callbacks(array(
	step => array($echo, 'stepped'),
	generate => array($echo, 'generate'),
	eliminate => array($echo, 'eliminate')
));
$echo->stepped(); 
$exp->expand();

echo "Results: " . count($exp->current_words()) . "\n";
