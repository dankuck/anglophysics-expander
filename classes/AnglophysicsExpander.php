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
		$words = AnglophysicsExpander_ArrayRemainder::eliminate($words, $this->words); // we don't have to do this now, but it could speed up the following steps
		$words = AnglophysicsExpander_WordEliminator::eliminate_non_words($words);
		$words = AnglophysicsExpander_CombinationFinder::eliminate_uncombinable_words($this->words, $words);
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
			if (strlen($word) < 2) // no one-letter words, because none of them are the kind that this world cares about
				continue;
			if (! preg_match('/[aeiou]/', $word)) // no vowel-less words.
				continue;
			$res[] = $word;
		}
		$words = $res;
		exec("echo '" . join(' ', $words) . "' | aspell list --encoding=utf-8 --lang=en --dict-dir=" . APP_ROOT . "/dict --master=nouns", $bad);
		$words = AnglophysicsExpander_ArrayRemainder::eliminate($words, $bad);
		return $words;
	}
}

class AnglophysicsExpander_WordGenerator{

	public static function generate_potential_words($words, $max_length = 8){
		$letters = self::letters_from($words);
		$gen = array();
		$it = new AnglophysicsExpander_WordGenerator_Permutator($letters, $max_length);
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

class AnglophysicsExpander_WordGenerator_Permutator{

	public function __construct($letters, $max_length = 8){
		$this->letters = array_merge(array(''), $letters);
		$this->current = array();
		for ($i = 0; $i < $max_length; $i++){
			$this->current[$i] = 0;
		}
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
			// set this to 0 and go on to increment the next highest one.
			$this->current[$i] = 0; 
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

	public static function eliminate_uncombinable_words($start, $new_words){
		$letter_groups = self::letter_groups($start);
		$combinable = array();
		foreach ($letter_groups as $group){	
			$matches = array();
			foreach ($new_words as $new_word){
				$new_matcher = new AnglophysicsExpander_CombinationFinder_MatchyGrabby($group);
				if (! $new_matcher->eat($new_word))
					continue; // not gonna match any of the saved matches, if it doesn't even match the brand-new one.
				foreach ($matches as $matcher){
					$matcher->eat($new_word);
				}
				$matches[] = $new_matcher;
			}
			foreach ($start as $old_word){
				foreach ($matches as $matcher){
					$matcher->eat($old_word);
				}
			}
			foreach ($matches as $match)
				if ($match->done())
					$combinable = array_merge($combinable, $match->words());
		}
		return $combinable;
	}

	public static function letter_groups($start){
		$groups = array();
		for ($i = 2; $i <= count($start); $i++){
			$it = new AnglophysicsExpander_CombinationFinder_Permutator($start, $i);
			while (! $it->done()){
				$letters = array_unique(preg_split('//', join('', $it->next())));
				sort($letters);
				$letters = join('', $letters);
				$groups[$letters] = $letters;
			}
		}
		return array_values($groups);
	}
}

/**
 * Every unique order-less combination of words should come up once.
 * More than once is acceptable but wasteful.
 *
 * This works by keeping an array of queues.
 * Everytime increment() is called, it shifts the top item off of the last queue
 * If that queue is now too short for the following copy operation, it jumps up to the next-to-last queue (etc)
 * Once a queue shifts and remains long enough for this operation:
 * It copies the queue down to the next queue and shifts that one's top, 
 * And then repeats until the last queue is reached again.
 * If the last isn't reached, it's done.
 * If the first went empty it's done.
 *
 * So it looks like this:
 *
 * Initialization with array('X', 'Y', 'Z'), length=2:
 *
 * Array1 Array2
 * X      Y
 * Y      Z
 * Z
 *
 * INCREMENT:
 *
 * Array1 Array2
 * X      Z
 * Y      
 * Z
 *
 * INCREMENT: 
 * 
 * Array1 Array2
 * Y      Z
 * Z
 *
 * INCREMENT:
 *
 * DONE.
 * 
 *
 */
class AnglophysicsExpander_CombinationFinder_Permutator{

	public function __construct($words, $length){
		$this->words = array_merge(array(''), $words);
		$this->current = array();
		$this->current[0] = array_merge(array(array()), $words);
		for ($i = 1; $i < $length; $i++)
			$this->current[$i] = array();
		$this->increment();
	}

	public function done(){
		return $this->done;
	}

	public function next(){
		if ($this->done)
			return;
		$next = array();
		foreach ($this->current as $i){
			$next[] = $i[0];
		}
		$this->increment();
		return $next;
	}

	public function increment(){
		if ($this->done)
			return;
		for ($i = count($this->current) - 1; $i >= 0; $i--){
			array_shift($this->current[$i]);
			if (count($this->current[$i]) < count($this->current) - $i)
				continue; // go on to increment the next highest one.
			for ($i++; $i < count($this->current); $i++){
				$this->current[$i] = $this->current[$i - 1];
				array_shift($this->current[$i]);
				if (! $this->current[$i]){
					$this->done = true;
					return;
				}
			}
			return; // increment done.
		}
		$this->done = true; // if we haven't already returned, then we got all the way to the beginning of current without anything valid. We're done.
	}

}

class AnglophysicsExpander_CombinationFinder_MatchyGrabby{

	public function __construct($group){
		$this->words = array();
		$this->remaining = $group;
	} 

	public function eat($word){
		if (self::matches($word, $this->remaining)){
			$this->words[] = $word;
			$this->remaining = preg_replace('/[' . $word . ']/', '', $this->remaining);
			return true;
		}
		return false;
	}

	public function done(){
		return ! $this->remaining;
	}

	public function words(){
		return $this->words;
	}

	public static function matches($word, $match){
		for ($i = 0; $i < strlen($word); $i++){
			if (! preg_match('/' . $word[$i] . '/', $match)){
				return false;
			}
		}
		return true;
	}
}

