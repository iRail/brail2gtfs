<?php
/**
 * This script populates the following GTFS files: routes.txt, trips.txt, stop_times.txt
 *
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
require("vendor/autoload.php");

use iRail\brail2gtfs\RouteFetcher;

$file_routes = "dist/routes.txt";
$file_trips = "dist/trips.txt";
$file_stop_times = "dist/stop_times.txt";
$file_temp = 'dist/calendar_dates_temp.txt';
$file_calendar_dates = "dist/calendar_dates.txt";

$language = "nn"; // Dutch
// $language = "fr"; // French
// $language = "en"; // English

$serviceId_date_pairs = array(); // All the trips don't drive go in here

// Returns hashmap. Key: route_id - Value: array of dates with a distinct service_id
function init() {
	global $serviceId_date_pairs;
	$hashmap = array(); // holds route_short_name => array of dates
	$checkRouteAdd = array(); // holds route_id => bool if added to routes.txt

	if(($handle = fopen('dist/routes_info.tmp.txt', 'r')) !== false)
	{
	    // get the first row, which contains the column-titles (if necessary)
	    $header = fgetcsv($handle);

	    // loop through the file line-by-line
	    while(($line = fgetcsv($handle)) !== false)
	    {
			$route_short_name = $line[0]; // $line is an array of the csv elements
			$service_id = $line[1];
			$date = $line[2];
			
			if (!isset($hashmap[$route_short_name])) {
					$hashmap[$route_short_name] = array();
			}

			// Check if service_id has already been added
			if (!checkForServiceId($hashmap[$route_short_name], $service_id)) {
				// Extra check that service is active that day
				// To be 100% sure that all calendar_dates are driving, this should be called for all the calendar_dates
				// But will take huge performance loss
				$serviceId_date_pair = getServiceIdDatePair($route_short_name, $service_id, $date);
				if ($serviceId_date_pair != null) {
		        	array_push($serviceId_date_pairs, $serviceId_date_pair);
		        } else {
					$pair = array($date, $service_id);
					array_push($hashmap[$route_short_name], $pair);
				}
			}

	        // I don't know if this is really necessary, but it couldn't harm;
	        // see also: http://php.net/manual/en/features.gc.php
	        unset($line);
	    }
	    fclose($handle);
	}
}

function checkForServiceId($dateServiceIdPairs, $service_id) {
	$contains = false;

	foreach ($dateServiceIdPairs as $pair) {
		// $date = $pair[0];
		$id = $pair[1];
		// Already added
		if($service_id == $id) {
			$contains = true;
		}
	}

	return $contains;
}

// this function uses the routefetcher to check if there's a trip driving on a certain day by a route
function getServiceIdDatePair($route_short_name, $service_id, $date) {
	global $checkRouteAdd;

	// 1 - 1 mapping
	$trip_id = $route_short_name . $service_id . '1';
	global $language;

	// processor
	list($route, $stop_times, $serviceId_date_pair) = RouteFetcher::fetchRouteAndStopTimes($route_short_name, $date, $trip_id, $service_id, $language);

	if ($serviceId_date_pair == null && $route != null && $stop_times != null) {
		// Add already for performance
		// routes.txt
		if (!isset($checkRouteAdd[$route_short_name])) {
			addRoute($route);
			$checkRouteAdd[$route_short_name] = true;
		}

		// trips.txt
		$trip = generateTrip($route_short_name, $service_id, $trip_id);
        addTrip($trip);
    	
    	// stop_times.txt
    	addStopTimes($stop_times);
	}

	return $serviceId_date_pair;
}

// Some services don't drive so those have to be deleted from calendar_dates.txt
// To do this, we generate a temporary file where we put all the services that drive
function makeCorrectCalendarDates($serviceId_date_pairs) {
	global $file_temp, $file_calendar_dates;

	if(($handleRead = fopen($file_calendar_dates, 'r')) !== false && ($handleWrite = fopen($file_temp, 'w')) !== false)
	{
		// Header new calendar_dates.txt file
		fputcsv($handleWrite,"service_id,date,exception_type");

	    // get the first row, which contains the column-titles (if necessary)
	    $header = fgetcsv($handleRead);

	    // loop through the file line-by-line
	    while(($line = fgetcsv($handleRead)) !== false)
	    {
    		// Not all pairs have been found
	    	if (count($serviceId_date_pairs) > 0) {
				$service_id_ = $line[0]; // $line is an array of the csv elements
				$date_ = $line[1];
				
				$addOnce = true;
				foreach ($serviceId_date_pairs as $service_id => $date) {
					if ($service_id == $service_id_ && $date == $date_) {
						// Delete from pairs
						unset($serviceId_date_pairs[$service_id]);
					} else if ($addOnce) {
						// Write to new temporary CSV-file
						fputcsv($handleWrite, $line);
						$addOnce = false;
					}
				}
			// Just write the rest
			} else {
				fputcsv($handleWrite, $line);
			}

	        // I don't know if this is really necessary, but it couldn't harm;
	        // see also: http://php.net/manual/en/features.gc.php
	        unset($line);
	    }
	    fclose($handleRead);
	    fclose($handleWrite);

	    // Delete old calendar_dates.txt
	    if (unlink($file_calendar_dates)) {
	    	var_dump("Deleted calendar_dates.txt");
	    }

	    // Rename temporary file so we have a new calendar_dates.txt
	    rename($file_temp, $file_calendar_dates);
	    var_dump("New calendar_dates.txt is ready.");
	}
}

function generateTrip($shortName, $service_id, $trip_id) {
	$trip_entry = [
        "@id" => $trip_id, //Sadly, this is only a local identifier
        "@type" => "gtfs:Trip",
        "gtfs:route" => "routes:" . $shortName,
        "gtfs:service" => $service_id, //Sadly, this is only a local identifier, and we use the same id as the trip for service rules
    ];

    return $trip_entry;
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

function addRoute($route_entry) {
	$csv = "";
	$csv .= $route_entry["@id"] . ","; // route_id
	$csv .= $route_entry["gtfs:agency"] . ","; // agency_id
	$csv .= $route_entry["gtfs:shortName"] . ","; // route_short_name
	$csv .= $route_entry["gtfs:longName"] . ","; // route_long_name
	$csv .= $route_entry["gtfs:routeType"]; // route_type

	global $file_routes;
	appendCSV($file_routes,$csv);
}

function addTrip($trip) {
	$csv = "";
	$csv .= $trip["gtfs:route"] . ","; // route_id
	$csv .= $trip["gtfs:service"] . ","; // service_id
	$csv .= $trip["@id"]; // trip_id

	global $file_trips;
	appendCSV($file_trips,$csv);
}

function addStopTimes($stop_times) {
	foreach($stop_times as $stop_time) {
		$csv = "";
		$csv .= $stop_time["gtfs:trip"] . ","; // trip_id
		$csv .= $stop_time["gtfs:arrivalTime"] . ","; // arrival_time
		$csv .= $stop_time["gtfs:departureTime"] . ","; // departure_time
		$csv .= $stop_time["gtfs:stop"] . ","; // stop_id
		$csv .= $stop_time["gtfs:stopSequence"]; // stop_sequence

		global $file_stop_times;
		appendCSV($file_stop_times,$csv);
	}
}

function makeHeaders() {
	global $file_routes, $file_trips, $file_stop_times;

	// routes.txt
	$header = "route_id,agency_id,route_short_name,route_long_name,route_type";
	appendCSV($file_routes, $header);

	// trips.txt
	$header = "route_id,service_id,trip_id";
	appendCSV($file_trips, $header);

	// stop_times.txt
	$header = "trip_id,arrival_time,departure_time,stop_id,stop_sequence";
	appendCSV($file_stop_times, $header);
}

// header CSVs
makeHeaders();

init();

// Generate new calendar_dates.txt with services that definitly drive
if (count($serviceId_date_pairs) > 0) {
	makeCorrectCalendarDates($serviceId_date_pairs);
}