<?php

class AnglophysicsExpander{

	public function __construct($base_words, $max_word_length = 8){
		$this->words_by_phase = array($base_words);
		$this->words = $base_words;
		$this->max_word_length = $max_word_length;
	}

	public function current_words(){
		return $this->words;
	}

	public function last_added_words(){
		return end($this->words_by_phase);
	}

	public function step(){
		$words = AnglophysicsExpander_WordGenerator::generate_potential_words($this->words, $this->max_word_length);
		$words = AnglophysicsExpander_ArrayRemainder::eliminate($words, $this->words);
		$words = AnglophysicsExpander_WordEliminator::eliminate_non_words($words);
		$words = AnglophysicsExpander_CombinationFinder::eliminate_uncombinable_words(array_merge($this->words, $words));
		$words = AnglophysicsExpander_ArrayRemainder::eliminate($words, $this->words);
		$this->words_by_phase[] = $words;
		$this->words = array_merge($this->words, $words);
	}

	public function expand($cb = null){
		do{
			$prior_count = count($this->words);
			$this->step();
			$found_more = count($this->words) > $prior_count;
			if (is_callable($cb))
				call_user_func_array($cb, array(! $found_more));
		} while($found_more);
	}
}

class AnglophysicsExpander_WordEliminator{

	public static function eliminate_non_words($words){
		$res = array();
		foreach ($words as $word){
			if (strlen($word) < 2)
				continue;
			$res[] = $word;
		}
		return $res;
	}
}

class AnglophysicsExpander_WordGenerator{

	public static function generate_potential_words($words, $max_length = 8){
		$letters = self::letters_from($words);
		$gen = array();
		$it = new AnglophysicsExpander_WordGenerator_Iterator($letters, $max_length);
		while (! $it->done()){
			$word = $it->next();
			if (self::nothing_starts_with($word)){
				$it->skip();
				continue;
			}
			$gen[] = $word;
		}
		return $gen;
	}

	public static function letters_from($words){
		$letters = array_unique(array_merge(preg_split('//', preg_replace('/[^a-z]/', '', strtolower(join('', $words))))));
		foreach ($letters as $i => $letter)
			if (! $letter){ // found the ''
				array_splice($letters, $i, 1);
				break;
			}
		return $letters;
	}

	/**
	 * Find out if we can skip any permutations that start with this
	 * @param  string $word - the potential beginning of some word
	 * @return boolean	- true if we're sure that no words start with $word
	 */
	public static function nothing_starts_with($word){
		return false;
	}
}

class AnglophysicsExpander_WordGenerator_Iterator{

	public function __construct($letters, $max_length = 8){
		$this->letters = array_merge(array(''), $letters);
		$this->current = array();
		for ($i = 0; $i < $max_length; $i++)
			$this->current[] = 0;
		$this->increment();
	}

	public function done(){
		return $this->done;
	}

	public function next(){
		if ($this->done)
			return;
		$word = '';
		foreach ($this->current as $i){
			$word .= $this->letters[$i];
		}
		$this->last_current = $this->current;
		$this->increment();
		return $word;
	}

	public function increment(){
		// find the first one that is zero and increment it. Or else just increment the last.
		$last = count($this->current) - 1;
		foreach ($this->current as $i => $v)
			if ($v == 0){
				$last = $i;
				break;
			}
		for ($i = $last; $i >= 0; $i--){
			$this->current[$i]++;
			if ($this->current[$i] < count($this->letters))
				return; // increment done.
			$this->current[$i] = 0; // increment this to 0 and go on to increment the next highest one.
		}
		$this->done = true; // if we haven't already returned, then we got all the way to the beginning of current without anything valid. We're done.
	}

	/**
	 * If a word comes out and you know that there are no words that start with that, we can skip it.
	 */
	public function skip(){
		// Start with the last current we had (the one they want to skip)
		$this->current = $this->last_current;
		// Then find the first zero and push it all the way past the last letter
		foreach ($this->current as $i => $v)
			if ($v == 0){
				$this->current[$i] = count($this->letters);
				break;
			}
		// Then let nature take its course.
		$this->increment();
	}
}

class AnglophysicsExpander_ArrayRemainder{

	/**
	 * Given two arrays, give an array containing items from the first that are not in the second
	 * @param  Array $array        The array from which items are removed. The variable is not changed.
	 * @param  Array $eliminations The array of items to remove from the first array
	 * @return Array               The elements of the first array that were not in the second array.
	 */
	public static function eliminate($array, $eliminations){
		$res = array();
		foreach ($array as $item)
			if (! in_array($item, $eliminations))
				$res[] = $item;
		return $res;
	}
}

class AnglophysicsExpander_CombinationFinder{

	public static function eliminate_uncombinable_words($words){
		return $words;
	}
}
