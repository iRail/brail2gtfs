<?php
/**
 * This script converts the iRail routes to the GTFS routes.txt file
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */

require 'vendor/autoload.php';

use GuzzleHttp\Client;

include("scripts/processor/fetch_route.php");

$file_routes = "dist/routes.txt";
$file_trips = "dist/trips.txt";
$file_calendar = "dist/calendar.txt";
$file_calendar_dates = "dist/calendar_dates.txt";
$file_stop_times = "dist/stop_times";

// Returns hashmap with all distinct routes and one corresponding date
function getDistinctRoutesWithDate() {
	$hashmap = array(); // holds distinct-route_short_name, date pair
	
	if(($handle = fopen('dist/routes.tmp.txt', 'r')) !== false)
	{
	    // get the first row, which contains the column-titles (if necessary)
	    $header = fgetcsv($handle);

	    // loop through the file line-by-line
	    while(($line = fgetcsv($handle)) !== false)
	    {
			$route_short_name = $line[0]; // $line is an array of the csv elements
			$date = $line[1];
			
			// one date per route_short_name is enough for lookup
			if(!array_key_exists($route_short_name, $hashmap))
				$hashmap[$route_short_name] = $date;

	        // I don't know if this is really necessary, but it couldn't harm;
	        // see also: http://php.net/manual/en/features.gc.php
	        unset($line);
	    }
	    fclose($handle);
	}

	return $hashmap;
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

function addRoute($route_entry) {
	$csv = "";
	$csv .= $route_entry["@id"]; // route_id
	$csv .= $route_entry["gtfs:agency"] . ","; // agency_id
	$csv .= $route_entry["gtfs:longName"] . ","; // route_long_name
	$csv .= $route_entry["gtfs:shortName"] . ","; // route_short_name
	$csv .= $route_entry["gtfs:routeType"]; // route_type

	global $file_routes;
	appendCSV($file_routes,$csv);
}

function addTrip($trip) {
	$csv = "";
	$csv .= $trip["gtfs:route"] . ","; // route_id
	$csv .= $trip["gtfs:service"] . ","; // service_id
	$csv .= $trip["gtfs:trip"]; // trip_id

	global $file_trips;
	appendCSV($file_trips,$csv);
}

function addCalendar($calendar) {
	$csv = "";
	$csv .= $calendar["gtfs:service"] . ","; // service_id
	$csv .= $calendar["gtfs:monday"] . ","; // monday
	$csv .= $calendar["gtfs:tuesday"] . ","; // tuesday
	$csv .= $calendar["gtfs:wednesday"] . ","; // wednesday
	$csv .= $calendar["gtfs:thursday"] . ","; // thursday
	$csv .= $calendar["gtfs:friday"] . ","; // friday
	$csv .= $calendar["gtfs:saturday"] . ","; // saturday
	$csv .= $calendar["gtfs:sunday"] . ","; // sunday
	$csv .= $calendar["gtfs:startTime"] . ","; // start_date
	$csv .= $calendar["gtfs:endTime"]; // end_date

	global $file_calendar;
	appendCSV($file_calendar,$csv);
}

function addCalendarDates($calendar_dates) {
	$csv = "";
	$csv .= $calendar_dates["gtfs:service"] . ","; // service_id
	$csv .= $calendar_dates["dct:date"] . ","; // date
	$csv .= $calendar_dates["gtfs:dateAddition"]; // exception

	global $file_calendar_dates;
	appendCSV($file_calendar_dates,$csv);
}

function addStopTimes($stop_times) {
	$csv = "";
	$csv .= $stop_times["gtfs:trip"] . ","; // trip_id
	$csv .= $stop_times["gtfs:arrivalTime"] . ","; // arrival_time
	$csv .= $stop_times["gtfs:departureTime"] . ","; // departure_time
	$csv .= $stop_times["gtfs:stop"] . ","; // stop_id
	$csv .= $stop_times["gtfs:stopSequence"]; // stop_sequence

	global $file_stop_times;
	appendCSV($file_stop_times,$csv);
}

function makeHeaders() {
	global $file_routes, $file_trips, $file_calendar, $file_calendar_dates, $file_stop_times;

	// routes.txt
	$header = "route_id, agency_id, route_short_name, route_long_name,route_type";
	appendCSV($file_routes, $header);

	// trips.txt
	$header = "route_id, service_id, trip_id";
	appendCSV($file_trips, $header);

	// calendar.txt
	$header = "service_id, monday, tuesday, wednesday, thursday, friday, saturday, sunday, start_date, end_date";
	appendCSV($file_calendar, $header);

	// calendar_dates.txt
	$header = "service_id,date,exception";
	appendCSV($file_calendar_dates, $header);

	// stop_times.txt
	$header = "trip_id, arrival_time, departure_time, stop_id, stop_sequence";
	appendCSV($file_stop_times, $header);
}

// header CSV's
makeHeaders();

$hashmap_route_date = getDistinctRoutesWithDate();

foreach ($hashmap_route_date as $route_short_name => $date) {

	// processor
	$route = fetchRoute($route_short_name, $date);
	$route_entry = $route[0];
	$trip = $route[1];
	$calendar = $route[2];
	$calendar_dates = $route[3];
	$stop_times = $route[4];

	// content CSV's
	addRoute($route_entry);

	addTrip($trip);

	addCalendar($calendar);

	addCalendarDates($calendar_dates);

	addStopTimes($stop_times);
}


