<?php

class AnglophysicsExpander{

	public function __construct($base_words, $max_word_length = 8){
		$this->words_by_phase = array($base_words);
		$this->words = $base_words;
		$this->max_word_length = $max_word_length;
	}

	public function set_callbacks($cbs){
		$this->step_cb = $cbs['step'];
		$this->generate_cb = $cbs['generate'];
		$this->eliminate_cb = $cbs['eliminate'];
	}

	public function current_words(){
		return $this->words;
	}

	public function current_words_by_phase(){
		return $this->words_by_phase;
	}

	public function last_added_words(){
		return end($this->words_by_phase);
	}

	private function generate(){
		if ($this->generated)
			return $this->generated;
		return $this->generated = AnglophysicsExpander_WordGenerator::generate_potential_words($this->words, $this->max_word_length);
	}

	public function step(){
		$words = $this->generate();
		$this->cb('generate', array($words));
		$words = AnglophysicsExpander_ArrayRemainder::eliminate($words, $this->words); // we don't have to do this now, but it could speed up the following steps
		$this->cb('eliminate', array('known', $words));
		$words = AnglophysicsExpander_WordEliminator::eliminate_non_words($words);
		$this->cb('eliminate', array('not-words', $words));
		$words = AnglophysicsExpander_CombinationFinder::eliminate_uncombinable_words($this->words, $words);
		$this->cb('eliminate', array('uncombinable', $words));
		$words = AnglophysicsExpander_ArrayRemainder::eliminate($words, $this->words);
		$this->cb('eliminate', array('known', $words));
		$this->words_by_phase[] = $words;
		$this->words = array_merge($this->words, $words);
	}

	public function expand(){
		do{
			$prior_count = count($this->words);
			$this->step($cb);
			$found_more = count($this->words) > $prior_count;
			$this->cb('step', array(! $found_more));
		} while($found_more);
	}

	private function cb($type, $params){
		$field = $type . '_cb';
		if (is_callable($this->$field))
			call_user_func_array($this->$field, $params);
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
			if (preg_match('/(.)\1{2,2}/', $word)) // no run-ons of more than two of the same char
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
		$letter_groups = self::letter_groups($start, true);
		$combinable = array();
		foreach ($letter_groups as $group){	
			$half_matches = array();
			$done_matches = array();
			$base = new AnglophysicsExpander_CombinationFinder_MatchyGrabby($group);
			foreach ($new_words as $new_word){
				if (! $new_match = $base->with($new_word))
					continue; // not gonna match any of the saved matches, if it doesn't even match the brand-new one.
				foreach ($half_matches as $matcher){
					if ($copy_match = $matcher->with($new_word)){
						if ($copy_match->done())
							$done_matches[] = $copy_match;
						else
							$half_matches[] = $copy_match;
					}
				}
				if ($new_match->done())
					$done_matches[] = $new_match;
				else
					$half_matches[] = $new_match;
			}
			// Note: This could violate the rule that you can't get out the same thing you put in.
			// But it's hard to rule out this:
			// rock+art == rat+cork
			// We don't know which one made the group and we don't want to increase our work by separating those out.
			// So just ignore this possibility.
			foreach ($start as $old_word){
				foreach ($half_matches as $matcher){
					if ($copy_match = $matcher->with($old_word)){
						if ($copy_match->done())
							$done_matches[] = $copy_match;
						else
							$half_matches[] = $copy_match;
					}
				}
			}
			foreach ($done_matches as $match){
				$combinable = array_merge($combinable, $match->words());
				if (count($combinable) >= count($new_words)){ // maybe we found them all
					$combinable = AnglophysicsExpander_ArrayRemainder::eliminate(array_unique($combinable), $start);
					if (count($combinable) == count($new_words))
						return $combinable; // Oh, we found them all. Yay!
				}
			}
		}
		return $combinable = AnglophysicsExpander_ArrayRemainder::eliminate(array_unique($combinable), $start);
	}

	public static function letter_groups($start, $return_array = false){
		if (! $return_array)
			return new AnglophysicsExpander_CombinationFinder_LetterGrouper($start);
		$groups = array();
		$start = AnglophysicsExpander_CombinationFinder_LetterGrouper::simplify_start($start);
		$max = count($start);
		if ($max > 5)
			$max = 5;
		for ($i = 1; $i <= $max; $i++){
			$it = new AnglophysicsExpander_CombinationFinder_Permutator($start, $i);
			while (! $it->done()){
				$letters = preg_split('//', join('', $it->next()));
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
				$this->current[$i] = array_slice($this->current[$i - 1], 1);
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

	public function with($word){
		if ($this->done())
			return null;
		$remaining = $this->remaining;
		for ($i = 0; $i < strlen($word); $i++){
			if (! preg_match('/' . $word[$i] . '/', $remaining)){
				return null;
			}
			$remaining = preg_replace('/' . $word[$i] . '/', '', $remaining, 1);
		}
		$copy = new self($remaining);
		$copy->words = array_merge($this->words, array($word));
		return $copy;
	}

	public function done(){
		return ! $this->remaining;
	}

	public function words(){
		return $this->words;
	}
}

class AnglophysicsExpander_CombinationFinder_LetterGrouper
implements Iterator{

	public function __construct($start){
		$this->start = self::simplify_start($start);
	}

	public function rewind(){
		$this->seen = array();
		$this->max = count($this->start);
		if ($this->max > 5)
			$this->max = 5;
		$this->it_size = 0;
		$this->next();
	}

	public function current(){
		return $this->current;
	}

	public function key(){
		return $this->current;
	}

	public function next(){
		do{
			if (! $this->it || ($this->it->done() && $this->it_size < $this->max)){
				$this->it = new AnglophysicsExpander_CombinationFinder_Permutator($this->start, ++$this->it_size);
			}
			$letters = preg_split('//', join('', $this->it->next()));
			sort($letters);
			$letters = join('', $letters);
		} while ($this->seen[$letters]); // already seen that one? go around again.
		$this->seen[$letters] = 1;
		$this->current = $letters;
	}

	public function valid(){
		return !! $this->current;
	}

	public function done(){
		if (! $this->it)
			return false;
		if ($this->it->done() && $this->it_size >= $this->max)
			return true;
		return false;
	}

	public static function simplify_start($start){
		$simple = array();
		foreach ($start as $word){
			$letters = preg_split('//', $word);
			sort($letters);
			$letters = join('', $letters);
			$simple[$word] = true;
		}
		return array_keys($simple);
	}
}
