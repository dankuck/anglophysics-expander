<?php

class ConsoleEcho{

	public function __construct($expander){
		$this->start = microtime(true);
		$this->i = 0;
		$this->expander = $expander;
	}

	public function stepped($done){
		$this->i++;
		echo $this->i . '. ' . round(microtime(true) - $this->start, 2) . ' seconds' . "\n" . $this->join($this->expander->last_added_words(), "\t", 80) . "\n";
		if ($done)
			echo "DONE\n";
	}

	public function join($words, $prefix, $length){
		$lines = "";
		while ($word = array_shift($words)){
			$potential = $line . $word . ', ';
			if (strlen($potential) <= $length){
				$line = $potential;
			}
			else{
				if ($line)
					$lines .= $line . "\n";
				$line = $prefix . $word;
			}
		}
		if ($line)
			$lines .= $line . "\n";
		return $lines;
	}
}
