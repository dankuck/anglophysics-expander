<?php

class ConsoleEcho{

	public function __construct($expander){
		$this->start = microtime(true);
		$this->i = 0;
		$this->expander = $expander;
	}

	public function stepped($done){
		$this->i++;
		echo $this->i . '. ' . round(microtime(true) - $this->start, 2) . ' seconds, ' . count($this->expander->current_words()) . "\n";
		if ($done)
			echo "DONE\n";
	}
}