<?php
//
// Programming Collective Intelligence by Toby Segaran in PHP
// Chapter 2 - Making Recommendations
// Translated from Python by Erik from Trejdify.com
// Everything except the del.icio.us link recommender and the movielens dataset 
//


//A dictionary of movie critics and their ratings of a small set of movies
$critics = array(
	'Lisa Rose' => 
		array('Lady in the Water' => 2.5, 'Snakes on a Plane' => 3.5, 'Just My Luck' => 3.0, 'Superman Returns' => 3.5, 'You, Me and Dupree' => 2.5, 'The Night Listener' => 3.0),
	'Gene Seymour' => 
		array('Lady in the Water' => 3.0, 'Snakes on a Plane' => 3.5, 'Just My Luck' => 1.5, 'Superman Returns' => 5.0, 'The Night Listener' => 3.0, 'You, Me and Dupree' => 3.5),
	'Michael Phillips' => 
		array('Lady in the Water' => 2.5, 'Snakes on a Plane' => 3.0, 'Superman Returns' => 3.5, 'The Night Listener' => 4.0),
	'Claudia Puig' => 
		array('Snakes on a Plane' => 3.5, 'Just My Luck' => 3.0,'The Night Listener' => 4.5, 'Superman Returns' => 4.0, 'You, Me and Dupree' => 2.5),
	'Mick LaSalle' => 
		array('Lady in the Water' => 3.0, 'Snakes on a Plane' => 4.0, 'Just My Luck' => 2.0, 'Superman Returns' => 3.0, 'The Night Listener' => 3.0,'You, Me and Dupree' => 2.0),
	'Jack Matthews' => 
		array('Lady in the Water' => 3.0, 'Snakes on a Plane' => 4.0,'The Night Listener' => 3.0, 'Superman Returns' => 5.0, 'You, Me and Dupree' => 3.5),
	'Toby' => 
		array('Snakes on a Plane' => 4.5,'You, Me and Dupree' => 1.0,'Superman Returns' => 4.0)
	);

//echo '<pre>', print_r($critics) ,'</pre>';



//
//Calculate a similarity score with Euclidean Distance, 1 is perfect, 0 is no similarity
//
function sim_euclidean($prefs,$person1,$person2) {
	//Get the list of shared movies between 2 persons
	$shared_items = array();
	foreach ($prefs[$person1] as $movie_person1 => $rating) {
		foreach ($prefs[$person2] as $movie_person2 => $rating) {
			if ($movie_person1 == $movie_person2) {
				$shared_items[$movie_person1] = 1;
			}
		}
	}

	//Check if they have any shared items
	if (count($shared_items) == 0) {
		$euclidean_distance = 0;
	}
	//Calculate the Euclidean Distance
	else {
		//Add up the squares of all the differences
		$sum_of_squares = 0;
		foreach($shared_items as $movie => $shared) {
			$sum_of_squares += pow($prefs[$person1][$movie]-$prefs[$person2][$movie],2);
		}
		
		$euclidean_distance = 1/(1 + sqrt($sum_of_squares)); // sqrt is not in the book, but in the errata online
	}
	
	return $euclidean_distance;
}

$person1 = 'Lisa Rose';
$person2 = 'Gene Seymour';

echo 'The similarity score calculated with the Euclidean Distance between '.$person1.' and '.$person2.' is: '.sim_euclidean($critics,$person1,$person2).'<br />';
echo 'The similarity score calculated with the Euclidean Distance between '.$person1.' and '.$person1.' is: '.sim_euclidean($critics,$person1,$person1).'<br />';
echo '<br />';



//
//Calculate a similarity score with Pearson Correlation, 1 is perfect, 0 is no similarity, -1 is negatively correlated
//
function sim_pearson($prefs,$person1,$person2) {
	//Get the list of shared movies between 2 persons
	$shared_items = array();
	foreach ($prefs[$person1] as $movie_person1 => $rating) {
		foreach ($prefs[$person2] as $movie_person2 => $rating) {
			if ($movie_person1 == $movie_person2) {
				$shared_items[$movie_person1] = 1;
			}
		}
	}
	
	//Find the number of elements
	$n = count($shared_items);
	
	//Check if they have any shared items
	if ($n == 0) {
		$pearson_correlation = 0;
	}
	//Calculate the Pearson Correlation
	else {
		
		$sum1 = 0;
		$sum2 = 0;
		$sum1Sq = 0;
		$sum2Sq = 0;
		$pSum = 0;
		foreach($shared_items as $movie => $shared) {
			//Add up all the preferences
			$sum1 += $prefs[$person1][$movie];
			$sum2 += $prefs[$person2][$movie];
			
			//Sum up the squares
			$sum1Sq += pow($prefs[$person1][$movie],2);
			$sum2Sq += pow($prefs[$person2][$movie],2);
			
			//Sum up the products
			$pSum += $prefs[$person1][$movie] * $prefs[$person2][$movie];
		}
		
		//Calculate Pearson score
		$num = $pSum - (($sum1 * $sum2) / $n);
		$den = sqrt(($sum1Sq - (pow($sum1,2)/$n)) * ($sum2Sq - (pow($sum2,2)/$n)));
		
		//Avoid division by 0
		if ($den == 0) {
			$pearson_correlation = 0;
		}
		else {
			$pearson_correlation = $num/$den;
		}
		
	}
	
	return $pearson_correlation;
}

$person1 = 'Lisa Rose';
$person2 = 'Gene Seymour';

echo 'The similarity score calculated with the Pearson Correlation between '.$person1.' and '.$person2.' is: '.sim_pearson($critics,$person1,$person2).'<br />';
echo 'The similarity score calculated with the Pearson Correlation between '.$person1.' and '.$person1.' is: '.sim_pearson($critics,$person1,$person1).'<br />';
echo '<br />';



//
//Find movie-critics that has a similar taste as a certain person
//
function top_matches($prefs,$person,$n,$sim_score='sim_pearson') {
	//Calculate the different Pearson Correlations and add them to an array
	$scores_array = array();
	foreach ($prefs as $other_person => $movies) {
		if ($person != $other_person) {
			$scores_array[$other_person] = call_user_func($sim_score,$prefs,$person,$other_person);
		}
	}
	
	//Sort the array so the highest score at the top
	asort($scores_array);
	$scores_array = array_reverse($scores_array);
	$scores_array = array_slice($scores_array,0,$n);
	
	return $scores_array;
	
}

$person = 'Toby';
echo 'If your name is '.$person.', then you should listen to the reviews by: '; print_r(top_matches($critics,$person,3));
echo '<br /><br />';



//
//Get recommendations for a person by using a weighted average of every other user's rankings
//
function get_recommendations($prefs,$person,$sim_score='sim_pearson') {
	//Create an array that holds the Total and SimSum for each movie
	$calc_array = array();
	//Loop through all person in the array
	foreach ($prefs as $other_person => $movies) {
		//Calculate the similarity score
		$sim = call_user_func($sim_score,$prefs,$person,$other_person);
		
		//Dont compare to yourself and ignore scores <= 0
		if ($person == $other_person || $sim <= 0) {
			continue;
		}
		
		//Loop though all the movies of the current critic
		foreach ($movies as $movie => $rating) {
			//Only score movies the person hasn't seen yet
			if (!array_key_exists($movie, $prefs[$person])) {
				
				//Add the movie to the array if it doesn't exists already in the array
				if (!array_key_exists($movie, $calc_array)) {
					$calc_array[$movie] = array();
					//Add default values
					$calc_array[$movie]['Total'] = 0;
					$calc_array[$movie]['SimSum'] = 0;
				}
				
				//Similarity * movie rating
				$calc_array[$movie]['Total'] += $sim * $rating;
				//Add to the total similarity
				$calc_array[$movie]['SimSum'] += $sim;
				
			}
		}
	}
	//echo '<pre>', print_r($calc_array) ,'</pre>';
	
	//Create the normalized list
	$recommendations = array();
	foreach ($calc_array as $movies => $values) {
		$recommendations[$movies] = $calc_array[$movies]['Total']/$calc_array[$movies]['SimSum'];
	}
	
	//Sort the array so the highest score at the top
	asort($recommendations);
	$recommendations = array_reverse($recommendations);
	
	return $recommendations;
}

$person = 'Toby';
echo 'If your name is '.$person.', then you should watch the following movies: '; print_r(get_recommendations($critics,$person));
echo '<br /><br />';



//
//Matching products
//
//Swap the movies with the critics
function transform_prefs($prefs) {
	$result = array();
	foreach ($prefs as $critic => $movies) {
		foreach ($movies as $movie => $rating) {
			//Add the movie to the array if it doesn't exists already in the array
			if (!array_key_exists($movie, $result)) {
				$result[$movie] = array();
			}
			$result[$movie][$critic] = $rating;
		}
	}
	
	return $result;
}

//echo '<pre>', print_r(transform_prefs($critics)) ,'</pre>' ;

$movie = 'Superman Returns';
$movies = transform_prefs($critics);

echo 'Movies similar to '.$movie.': '; print_r(top_matches($movies,$movie,5));
echo '<br /><br />';



//
//Find recommended critics for a movie
//
$movie = 'Just My Luck';
echo 'The following critics should give the movie '.$movie.' the ratings: '; print_r(get_recommendations($movies,$movie));
echo '<br /><br />';



//
//Build the item comparison set
//
function calculate_similar_items($prefs,$n=10) {
	//Create a dictionary of items showing which other items they are most similar to
	$result = array();
	
	//Invert the preference matrix to be item-centric
	$item_prefs = transform_prefs($prefs);
	
	$c = 0;
	
	foreach ($item_prefs as $item => $stuff) {
		//Status updated for large datasets
		$c += 1;
		if ($c % 100 == 0) {
			echo 'Still alive!';
		}
		//Find most similar items to this one
		$scores = top_matches($item_prefs,$item,$n,'sim_euclidean');
		$result[$item] = $scores;
	}
	
	return $result;
}

$item_sim = calculate_similar_items($critics);

//Not the same as in the book because of the missing sqrt when calculating euclidean distance
//echo '<pre>', print_r(calculate_similar_items($critics)) ,'</pre>';



//
//Get recommendations based on the item comparison set
//
function get_recommended_items($prefs,$item_match,$user) {
	$user_ratings = $prefs[$user];
	//Create an array that holds the Total and SimSum for each movie
	$calc_array = array();
	
	//Loop over items (movies) rated by the user
	foreach ($user_ratings as $movie => $rating) {
		
		//Loop over movies similar to this movie
		foreach ($item_match[$movie] as $movie_sim => $rating_sim) {
			
			//Ignore if this user has already rated this item
			if (array_key_exists($movie_sim, $prefs[$user])) {
				continue;
			}
			
			//Add the movie to the array if it doesn't exists already in the array
			if (!array_key_exists($movie_sim, $calc_array)) {
				$calc_array[$movie_sim] = array();
				//Add default values
				$calc_array[$movie_sim]['Total'] = 0;
				$calc_array[$movie_sim]['SimSum'] = 0;
			}
			
			//Similarity * movie rating
			$calc_array[$movie_sim]['Total'] += $rating * $rating_sim;
			//Add to the total similarity
			$calc_array[$movie_sim]['SimSum'] += $rating_sim;
			
		}
	}
	
	//Create the normalized list
	$recommendations = array();
	foreach ($calc_array as $movies => $values) {
		$recommendations[$movies] = $calc_array[$movies]['Total']/$calc_array[$movies]['SimSum'];
	}
	
	//Sort the array
	asort($recommendations);
	$recommendations = array_reverse($recommendations);
	
	return $recommendations;
	
}

//Not the same as in the book because of the missing sqrt when calculating euclidean distance
$person = 'Toby';
echo 'If your name is '.$person.' then you are recommended to following movies: '; print_r(get_recommended_items($critics,$item_sim,$person));

?>