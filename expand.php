
if ($argv[1])
	$base_words = preg_split('/\s/', join(' ', array_slice($argv, 1)));
else{
	// figure out php://input
}

$exp = new AnglophysicsExpander($base_words);
$exp->expand();
echo join("\n", $exp->current_words());
