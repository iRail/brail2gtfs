<?php
/**
 * This script converts the iRail routes to the GTFS routes.txt file
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */

require 'vendor/autoload.php';

use GuzzleHttp\Client;

$dist = "dist/routes2.txt";

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '180');

// Returns array of all distinct routes
function getDistinctRouteShortNames() {
	$route_short_names = array();
	$route_short_names_with_duplicates = array();
	
	if(($handle = fopen('dist/routes.tmp.txt', 'r')) !== false)
	{
	    // get the first row, which contains the column-titles (if necessary)
	    $header = fgetcsv($handle);

	    // loop through the file line-by-line
	    while(($data = fgetcsv($handle)) !== false)
	    {
			$route_short_name = $data[0]; //$line is an array of the csv elements
			array_push($route_short_names_with_duplicates, $route_short_name);

	        // I don't know if this is really necessary, but it couldn't harm;
	        // see also: http://php.net/manual/en/features.gc.php
	        unset($data);
	    }
	    fclose($handle);
	}

	$route_short_names = array_unique($route_short_names_with_duplicates);

	return $route_short_names;
}

// Returns info in JSON-format about a route
function getRouteInfo($day, $month, $fullyear, $shortName) {
	$client = new Client();

	$url = "http://www.belgianrail.be/jp/sncb-nmbs-routeplanner/trainsearch.exe/en?vtModeTs=weekday&productClassFilter=69&clientType=ANDROID&androidversion=3.1.10%20(31397)&hcount=0&maxResults=50&clientSystem=Android21&date=" . $day . "." . $month . "." . $fullyear . "&trainname=" . $shortName . "&clientDevice=Android%20SDK%20built%20for%20x86&htype=Android%20SDK%20built%20for%20x86&L=vs_json.vs_hap";
	$response = $client->get($url);

	$malformed_json = $response->getBody();
	// delete trailing ; character
	$json = json_decode(substr($malformed_json, 0, -1));

	if(isset($json->{"suggestions"}[0])) { // avoid 'Notice: Trying to get property of non-object'
		$route_info = $json->{"suggestions"}[0];
	} else { // Route not available today
		$route_info = NULL;
		// Start date
		$start_date = '2015-01-01';
		// End date → See https://github.com/iRail/brail2gtfs/issues/8
		$end_date = '2015-12-14';

		$date = strtotime($start_date);
		while($route_info == NULL && $date < strtotime($end_date)) {
			$day = date("d", $date);
			$month = date("m", $date);
			$fullyear = date("Y", $date);

			$route_info = getRouteInfo($day, $month, $fullyear, $shortName);

			$date = strtotime("+1 day", $date);
		}
	}

	return $route_info;
}

// Returns a long name [departureStation - arriveStation] of a route
function getRouteLongName($shortName) {
	// today
	$day = getDate()["mday"];
	$month = getDate()["mon"];
	$fullyear = getDate()["year"];

	$route_info = getRouteInfo($day, $month, $fullyear, $shortName);

	if($route_info != NULL)
		$route_long_name = $route_info->{"dep"} . " - " . $route_info->{"arr"};
	else // possible in one case: IC12243
		$route_long_name = "";

	return $route_long_name;
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = "route_id, agency_id, route_short_name, route_long_name,route_type";
appendCSV($dist, $header);

// content CSV
$route_short_names = getDistinctRouteShortNames();

$csv = "";
foreach ($route_short_names as $route_short_name) {

	$csv .= "http://irail.be/routes/NMBS/" . $route_short_name . ",";
	$csv .= "0" . ","; // agency_id is always 0
	$csv .= $route_short_name . ",";
	$csv .= getRouteLongName($route_short_name) . ",";
	$csv .= "2"; // route_type is always 2

	appendCSV($dist,$csv);
	$csv = "";
}