<?php
//
// Programming Collective Intelligence by Toby Segaran in PHP
// Chapter 3 - Discovering Groups (Clusters)
// Translated from Python by Erik from Trejdify.com
// Everything except "Counting the Words in a Feed", "Drawing the Dendrogram", "Clusters of Preferences", "Viewing Data in Two Dimensions" 
//



//Load in the datafile
//Row 1. All the individual words
//Row 2->... - Col 1 is the name of the blog, Col 2->... is how many times the words appear in each blog 0->...
function read_file($filename) {
	
	$file = fopen($filename, 'r') or exit('Unable to open file!');

	//Read the file line for line
	//Counter to determine the first row which is different from the rest
	$counter = 0;
	
	$rownames = array();
	$data = array(); 
	
	while(!feof($file)) {
		//First line is the column titles
		if ($counter == 0) {
			$words = fgets($file);
			
			//Split by tab			
			$colnames = preg_split('/[\t]/',$words);
			
			$counter++;
		}
		else {
			//Same as above, but the first element in array is to be removed into rownames
			$all_data = fgets($file);
			
			//Split by tab			
			$all_data = preg_split('/[\t]/',$all_data);
			
			//Remove the first element from the array = name of the blog
			//First column in each row is the row name
			$rownames[] = array_shift($all_data);
			//The data for this row is the remainder of the row
			$data[] = $all_data;
		}
	}
	
	fclose($file);
	
	return array($rownames,$colnames,$data);

}

//Test
$blog_data = read_file('blogdata.txt');
$rownames 	= $blog_data[0];
$colnames 	= $blog_data[1];
$data 		= $blog_data[2];

//echo '<pre>', print_r($rownames) ,'</pre>';



//Define closeness as the Pearson Correlation from Chapter 2 - not the same code since all shared here
function pearson($v1,$v2) {
	//Simple sums
	$sum1 = array_sum($v1);
	$sum2 = array_sum($v2);
	
	//Sums of the squares
	$sum1Sq = 0;
	$sum2Sq = 0;
	foreach ($v1 as $v) {
		$sum1Sq += pow($v,2);
	}
	foreach ($v2 as $v) {
		$sum2Sq += pow($v,2);
	}
	
	//Sum of the products
	$pSum = 0;
	foreach ($v1 as $index => $v) {
		$pSum += $v * $v2[$index];
	}

	//Calculate r (Pearson score)
	$n = count($v1);
	$num = $pSum - (($sum1 * $sum2) / $n);
	$den = sqrt(($sum1Sq - (pow($sum1,2)/$n)) * ($sum2Sq - (pow($sum2,2)/$n)));
	
	//Avoid division by 0
	if ($den == 0) {
		$pearson_correlation = 0;
	}
	else {
		$pearson_correlation = $num/$den;
	}
	
	//Don't return the pearson correlation - we need the closeness
	if ($pearson_correlation == 0) {
		return 0;
	}
	//1-r to create a smaller distance between items that are more similar
	else {
		return 1 - $pearson_correlation;
	}

}

//Test
//echo pearson($data[0],$data[1]).'<br />';



//Each cluster is either a point in a tree with 2 branches, or an endpoint
class Bicluster {
	function __construct(
						$vec,
						$left = null,
						$right = null,
						$distance = 0,
						$id = null
						) {
		
		$this->left = $left;
		$this->right = $right;
		$this->vec = $vec;
		$this->distance = $distance;
		$this->id = $id;		
	}
}



//Method 1. Hierarchical Clustering. The main loop searches for the 2 best matches by trying every possible pair and calculating their correlation. The best pair is merged into a single cluster. The data form this new cluster is the average of the data for the 2 old clusters. This process is repeated until 1 cluster remains
function h_cluster($rows,$distance='pearson') {
	$distances = array();
	$current_clust_id = -1;
	//Holds the cluster numbers and the distance. Assoc array with numbers $distances[''.$zero.','.$one.''] = 0.1298;
	$distances = array();
	
	//Clusters are initially just the rows
	foreach ($rows as $index => $i) {
		$clust[] = new Bicluster($i,null,null,0,$index);
	}
	
	//Repeat until one large cluster remains consisting of the smaller clusters
	while (count($clust) > 1) {		
		//Reset each time
		$lowestpair = array(0,1);
		$closest = call_user_func($distance,$clust[0]->vec,$clust[1]->vec);
		
		//echo count($clust).'<br />';
		
		//Loop through every pair looking for the smallest distance
		for ($i = 0; $i < count($clust)-1; $i++) {
			for ($j = $i + 1; $j < count($clust); $j++) {
				//set_time_limit(0);
				
				//Distances is the cache of distance calculations
				if (!array_key_exists(''.$clust[$i]->id.','.$clust[$j]->id.'',$distances)) {
					$distances[''.$clust[$i]->id.','.$clust[$j]->id.''] = call_user_func($distance,$clust[$i]->vec,$clust[$j]->vec);						
				}
				
				$d = $distances[''.$clust[$i]->id.','.$clust[$j]->id.''];
				
				if ($d < $closest) {
					$closest = $d;
					$lowestpair = array($i,$j);
				}
				
			}
		}
		
		//Calculate the average of the new cluster
		$mergevec = array();
		foreach ($clust[0]->vec as $index => $item) {
			$mergevec[] = ($clust[$lowestpair[0]]->vec[$index] + $clust[$lowestpair[1]]->vec[$index]) / 2;
		}
		
		//Create the new cluster
		$newcluster = new Bicluster(
								$mergevec,
								$clust[$lowestpair[0]],
								$clust[$lowestpair[1]],
								$closest,
								$current_clust_id
								);
		
		//Cluster ids that weren't in the original set are negative
		$current_clust_id -= 1;
		unset($clust[$lowestpair[0]]);
		unset($clust[$lowestpair[1]]);
		array_push($clust, $newcluster);
		
		//Repair the index, unset removes the value and the index
		$clust = array_values($clust);
		
	}
	
	return $clust[0];
	
}

//Test
//$clust = h_cluster($data);



//Traverses the clustering tree recursively and prints it like a (messy) filesystem hierarchy
function print_clust($clust,$labels=null,$n=0) {
	//Indent to make a hierarchy layout
	for ($i = 0; $i < $n; $i++) {
		echo '&nbsp;&nbsp;';
	}
	
	if ($clust->id < 0) {
		//Negative id means that this is a branch
		echo '-<br />';
	}
	else {
		//Positive id means that this is an endpoint
		if ($labels == null) {
			echo $clust->id.'<br />';
		}
		else {
			echo $labels[$clust->id].'<br />';
		}
	}
	
	//Now print the right and left branches
	if ($clust->left != null) {
		print_clust($clust->left,$labels,$n++);
	}
	if ($clust->right != null) {
		print_clust($clust->right,$labels,$n++);
	}
}

//Test
$blognames = $rownames;

//print_clust($clust,$blognames);



//Column clustering
function rotate_matrix($data) {
	//set_time_limit(0);
	$newdata = array();
	
	for ($i = 0; $i < count($data[0]); $i++) {
		for ($j = 0; $j < count($data); $j++) {
			$newrow[] = $data[$j][$i]; 
		}
		array_push($newdata,$newrow);
	}
	
	return $newdata;
}

//Test
//$rdata = rotate_matrix($data);



//Method 2. K-Means Clustering. k - number of clusters that the caller would like returned. Faster than Hierarchical Clustering
function k_cluster($rows,$distance='pearson',$k=4) {
	set_time_limit(0);
	//Determine the minimum and maximum values for each point
	$ranges = array();
	for ($i = 0; $i < count($rows[0]); $i++) {
		$values = array();
		foreach ($rows as $index => $row) {
			$values[] = (int) $row[$i];
		}
		
		//$ranges[] = ''.min($values).','.max($values).'';
		$ranges[] = array(min($values),max($values));
	}
	
	//Create k randomly placed centroids
	$clusters = array();
	for ($j = 0; $j < $k; $j++) {
		for ($i = 0; $i < count($rows[0]); $i++) {
			$clusters[$j][] = (mt_rand(0,99999)/100000)*($ranges[$i][1]-$ranges[$i][0]) + $ranges[$i][0];
		}
	}
	
	$lastmatches = null;
	for ($t = 0; $t < 100; $t++) {
		echo 'Iteration '.$t.'<br />';
		
		$bestmatches = array();
		
		//Find which centroid is the closest for each row
		for ($j = 1; $j < count($rows); $j++) {
			$row = $rows[$j];
			$bestmatch = 0;
			for ($i = 0; $i < $k; $i++) {
				$d = call_user_func($distance,$clusters[$i],$row);
				if ($d < call_user_func($distance,$clusters[$bestmatch],$row)) {
					$bestmatch = $i; //Id of the centroid
				}
			}
			$bestmatches[$bestmatch][] = $j;
		}
		
		//If the results are the same as last time, this is complete
		if ($bestmatches == $lastmatches) {
			break;
		}
		
		$lastmatches = $bestmatches;
		
		//Move the centroids to the average of their members
		for ($i = 0; $i < $k; $i++) {
			$avgs = array();
			//Add init values to the array
			for ($s = 0; $s < count($rows[0]); $s++) {
				$avgs[] = 0;
			}
			
			if (count($bestmatches[$i]) > 0) {
				foreach ($bestmatches[$i] as $rowid) {
					for ($m = 0; $m < count($rows[$rowid]); $m++) {
						$avgs[$m] += $rows[$rowid][$m];
					}
				}
				
				for ($j = 0; $j < count($avgs); $j++) {
					$avgs[$j] = $avgs[$j] / count($bestmatches[$i]); 
				}
								
				$clusters[$i] = $avgs;
			}
		}
	}
	
	return $bestmatches;
}

//Test
$kclust = k_cluster($data,'pearson',10);

//Display the bloggers belonging to cluster 0
echo '<br />';
foreach ($kclust[0] as $index) {
	echo $blognames[$index].'<br />';
}

//echo '<pre>', print_r($kclust) ,'</pre>';

?>