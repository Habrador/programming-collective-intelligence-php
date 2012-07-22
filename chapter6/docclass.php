<?php
//
// Programming Collective Intelligence by Toby Segaran in PHP
// Chapter 6 - Document Filtering
// Translated from Python by Erik from Trejdify.com
//



//
//Extract features from the text. Breaks up the text into words by dividing the text on any character that isn't a letter - and convert everything to lowercase
//
function get_words($doc) {
	//Split the words by non-alpha characters
	$splitter = preg_split('/[^A-Za-z]/', $doc);
	
	$words = array();
	//Lower case and remove short and long words and check if the word is not already in the array
	foreach ($splitter as $item) {		
		if (strlen($item) > 2 && strlen($item) < 20 && !array_key_exists($item, $words)) {
			$item = strtolower($item);
			
			$words[$item] = 1;
		}
	}	
	
	return $words;
}

//$test = 'Nobody owns the water u*er-n00Bs!, only I own the water';
//The python version in the book will keep n00Bs
//get_words($test);



//
//The class to represent the classifier that will learn how to classify a document by being trained
//
class Classifier {
	
	function __construct($get_features,$filename=null) {
		//Counts of feature/category combinations ('python',('bad' 0, 'good' 6)) python = feature, good = category
		$this->fc = array();
		//Counts of documents in each category
		$this->cc = array();
		$this->get_features = $get_features;
	}
	
	
	//Increase the count of a feature/category pair {'brown': {'good': 1, 'bad': 1} when 2 sentences trained
	function incf($f,$cat) {
		//Default values
		if (!isset($this->fc[$f][$cat])) {
			$this->fc[$f][$cat] = 1;
		}
		else {
			$this->fc[$f][$cat] += 1;
		}
	}
	
	
	//Increase the count of a category {'bad': 1, 'good': 1} when 2 sentences trained
	function incc($cat) {
		//Default values
		if (!isset($this->cc[$cat])) {
			$this->cc[$cat] = 1;
		}
		else {
			$this->cc[$cat] += 1;
		}
	}
	
	
	//The number of times a feature has appeared in a category
	function fcount($f,$cat) {
		if (array_key_exists($f, $this->fc) && array_key_exists($cat, $this->fc[$f])) {
			return $this->fc[$f][$cat];
		}
		else {
			return 0;
		}
	}
	
	
	//The number of items in a category
	function catcount($cat) {
		if (array_key_exists($cat, $this->cc)) {
			return $this->cc[$cat];
		}
		else {
			return 0;
		}
	}
	
	
	//The total number of items = number of good and bad douments
	function totalcount() {
		return array_sum($this->cc);
	}
	
	
	//The list of all categories, good,bad
	function categories() {
		return array_keys($this->cc);
	}
	
	
	//Train 
	function train($item,$cat) {
		$features = call_user_func($this->get_features,$item);
		
		//Increment the count for every feature with this category
		foreach ($features as $f => $count) {
			$this->incf($f,$cat);
		}
		
		//Increment the count for this category
		$this->incc($cat);
	}
	
	
	//Calculate the probability that a word is in a particular category
	function fprob($f,$cat) {
		if ($this->catcount($cat) == 0) {
			return 0;
		}
		//The total number of times this feature appeared in this category divided by the total number of items in this category
		else {
			return $this->fcount($f,$cat)/$this->catcount($cat);
		}
	}
	
	
	//Calculate the weighted probability = if the classifier has been trained with few examples and few words
	function weighted_prob($f,$cat,$prf,$weight=1,$ap=0.5) {
		//Calculate the current probability
		$basicprob = call_user_func(array($this, $prf),$f,$cat);
		
		//Count the number of times this feature has appeared in all categories
		//Wrong according to the errata online?
		$totals = 0;
		foreach($this->categories() as $c) {
			$totals += $this->fcount($f,$c);
		}
		
		//Calculate the weighted average
		$bp = (($weight * $ap) + ($totals * $basicprob)) / ($weight + $totals);
		
		return $bp;
	}
}

/*
//Create a new object
$cl = new Classifier('get_words');
$cl->train('the quick brown fox jumps over the lazy dog','good');
$cl->train('make quick money in the online casion','bad');

echo $cl->fcount('quick','good').'<br />';
echo $cl->fcount('quick','bad').'<br />';
echo $cl->fcount('python','bad').'<br />';
*/


//
//Sample training data
//
function sample_train($cl) {
	$cl->train('Nobody owns the water.', 'good');
	$cl->train('the quick rabbit jumps fences', 'good');
	$cl->train('buy pharmaceuticals now', 'bad');
	$cl->train('make quick money at the online casino', 'bad');
	$cl->train('the quick brown fox jumps', 'good');
}
/*
//Create a new object
$cl = new Classifier('get_words');

//Train the classifier with examples
sample_train($cl);

//P(quick|good) = 2/3
echo $cl->fprob('quick','good').'<br />';
//P(quick|bad) = 1/2
echo $cl->fprob('quick','bad').'<br />';

echo '<pre>', print_r($cl->fc), '</pre>';
echo '<pre>', print_r($cl->cc), '</pre>';
*/

/*
//
// Weighted probabilities
//
//Without weigthed prob
echo $cl->fprob('money','good').'<br />';
//With weigthed prob
echo $cl->weighted_prob('money','good','fprob').'<br />';
//Train the classifier with examples again
sample_train($cl);
echo $cl->weighted_prob('money','good','fprob').'<br />';
*/


//
//Classifiers to combine the individual word probabilities to get the probability that an entire document belongs in a given category 
//

//Classifier 1 - A naive (probabilities independent of each other) Bayesian classifier
class NaiveBayes extends Classifier {
	
	function __construct($get_features) {
		//Run the parent constructor
		parent::__construct($get_features);
		$this->threshold = array();
	}
	
	//P(Document|Category) document is sentence in this case
	function doc_prob($item,$cat) {
		$features = call_user_func($this->get_features,$item);
		
		//Multiply the probability of all the features together
		$p = 1;
		foreach ($features as $f => $count) {
			$p *= $this->weighted_prob($f,$cat,'fprob'); 
		}
		
		return $p;
	}
	
	
	//P(Category|Document) = P(Document|Category) * P(Category)) / P(Document) where P(Document) is not needed
	function prob($item,$cat) {
		$catprob = $this->catcount($cat) / $this->totalcount();
		
		$docprob = $this->doc_prob($item,$cat);
		
		return $catprob * $docprob;
	}
	
	
	//Set the threshold - the difference needed between 2 categories to be able to classify the document 
	function set_threshold($cat,$t) {
		$this->threshold[$cat] = $t;
	}
	
	
	//Get the threshold
	function get_threshold($cat) {
		if (!isset($this->threshold[$cat])) {
			return 1;
		}
		else {
			return $this->threshold[$cat];
		}
	}
	
	
	//Determine with the help of threshold the best category e.g. the prob of bad cat must be 3 times the prob good cat
	function classify($item,$default='unknown category') {
		$probs = array();
		
		//Find the category with the higest probability
		$max = 0;
		foreach ($this->categories() as $cat) {
			$probs[$cat] = $this->prob($item,$cat);
			if ($probs[$cat] > $max) {
				$max = $probs[$cat];
				$best = $cat;
			}
		}
		
		//Make sure the probability exceeds threshold*next best
		foreach ($probs as $cat => $probability) {
			if ($cat == $best) {
				continue;
			}
			if(($probs[$cat] * $this->get_threshold($best)) > $probs[$best]) {
				return $default;
			}
			else {
				return $best;
			}
		}
	}
}
/*
//Create a new object
$cl = new NaiveBayes('get_words');

//Train with examples
sample_train($cl);

echo $cl->prob('quick rabbit','good').'<br />';
echo $cl->prob('quick rabbit','bad').'<br />';

//A naive Bayesian classifier
echo $cl->classify('quick rabbit').'<br />';
echo $cl->classify('quick money').'<br />';
//Change the threshold
$cl->set_threshold('bad',3);
echo $cl->classify('quick money').'<br />';
*/

//Classifier 2 - The Fisher Method
class FisherClassifier extends Classifier {
	
	function __construct($get_features) {
		//Run the parent constructor
		parent::__construct($get_features);
		$this->minimums = array();
	}
	
	
	//The probability that an item with the specified feature belongs in the specified category
	function cprob($f,$cat) {
		//The frequency of this feature in this category
		$clf = $this->fprob($f,$cat);
		if ($clf == 0) {
			return 0;
		}
		
		//The frequency of this feature in all the categories
		$freqsum = 0;
		foreach ($this->categories() as $c) {
			$freqsum += $this->fprob($f,$c);
		}
		
		//The probability is the frequency in this category divided by the overall frequency
		$p = $clf / $freqsum;
		
		return $p;
	}
	
	
	//Combining the probabilities
	function fisherprob($item,$cat) {
		//Multiply all the probabilities together
		$p = 1;
		$features = call_user_func($this->get_features,$item);
		foreach ($features as $f => $count) {
			$p *= $this->weighted_prob($f,$cat,'cprob');
		}
		
		//Take the natural log and multiply by -2
		$fscore = -2 * log($p);
		
		//Use the inverse chi2 function to get a probability
		return $this->inv_chi2($fscore,count($features)*2); //Not *2 according to the errata online?
	}
	
	
	//The inverse chi-square
	function inv_chi2($chi,$df) {
		$m = $chi / 2;
		$sum = $term = exp(-$m);
		foreach (range(1, ((int) $df/2)-1) as $i) {
			$term *= $m / $i;
			$sum += $term;
		}
		
		return min($sum, 1);
	}
	
	
	//Classifying items - everything above eg 0.6 is good
	function set_minimum($cat,$min) {
		$this->minimums[$cat] = $min;
	}
	
	function get_minimum($cat) {
		if (!isset($this->minimums[$cat])) {
			return 0;
		}
		else {
			return $this->minimums[$cat];
		}
	}
	
	//To calculate the probabilities for each category and determine the best result that exceeds the specified minimum
	function classify($item,$default='unknown category') {
		//Loop through looking for the best result
		$best = $default;
		$max = 0;
		foreach ($this->categories() as $c) {
			$p = $this->fisherprob($item,$c);
			//Make sure it exceeds its minimum
			if ($p > $this->get_minimum($c) && $p > $max) {
				$best = $c;
				$max = $p;
			}
		}
		
		return $best;
	}
 	
}

//Create a new object
$cl = new FisherClassifier('get_words');

//Train with examples
sample_train($cl);
/*
echo $cl->cprob('quick','good').'<br />';
echo $cl->cprob('money','bad').'<br />';

echo $cl->weighted_prob('money','bad','cprob').'<br />';
*/
//Combining the probabilities
//echo $cl->fisherprob('quick rabbit','good').'<br />';
//echo $cl->fisherprob('quick rabbit','bad').'<br />';

//Classify the documents
echo $cl->classify('quick rabbit').'<br />';
echo $cl->classify('quick money').'<br />';
$cl->set_minimum('bad',0.8);
echo $cl->classify('quick money').'<br />';
$cl->set_minimum('good',0.4);
echo $cl->classify('quick money').'<br />';
?>