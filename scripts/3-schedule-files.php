<?php
/**
 * This script populates the following GTFS files: routes.txt, trips.txt, calendar.txt, calendar_dates.txt, stop_times.txt
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
include("scripts/processor/fetch_route.php");

$file_routes = "dist/routes.txt";
$file_trips = "dist/trips.txt";
$file_calendar = "dist/calendar.txt";
$file_calendar_dates = "dist/calendar_dates.txt";
$file_stop_times = "dist/stop_times";

// Returns hashmap. Key: route - Value: array of all possible dates
function getRoutesWithDates() {
	$hashmap = array(); // holds route_short_name => array of dates

	if(($handle = fopen('dist/routes.tmp.txt', 'r')) !== false)
	{
	    // get the first row, which contains the column-titles (if necessary)
	    $header = fgetcsv($handle);

	    // loop through the file line-by-line
	    while(($line = fgetcsv($handle)) !== false)
	    {
			$route_short_name = $line[0]; // $line is an array of the csv elements
			$date = $line[1];
			
			array_push($hashmap[$route_short_name], $date);

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
	foreach($calendar_dates as $calendar_date) {
		$csv = "";
		$csv .= $calendar_date["gtfs:service"] . ","; // service_id
		$csv .= $calendar_date["dct:date"] . ","; // date
		$csv .= $calendar_date["gtfs:dateAddition"]; // exception

		global $file_calendar_dates;
		appendCSV($file_calendar_dates,$csv);
	}
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

function checkCalendar($calendar, $date) {
	$matchDayOfWeek = FALSE;
	$matchTime = FALSE;

	// Check if day of week matches calendar
	$a = strptime($date, '%Y-%m-%d');
	$timestamp = mktime(0, 0, 0, $a['tm_mon']+1, $a['tm_mday'], $a['tm_year']+1900);
	$dayOfWeek = date("N", $timestamp); // ISO-8601 numeric representation of the day of the week

	if($dayOfWeek == 1 && $calendar["gtfs:monday"] == 1) $matchDayOfWeek = TRUE;
	else if($dayOfWeek == 2 && $calendar["gtfs:tuesday"] == 1) $matchDayOfWeek = TRUE;
	else if($dayOfWeek == 3 && $calendar["gtfs:wednesday"] == 1) $matchDayOfWeek = TRUE;
	else if($dayOfWeek == 4 && $calendar["gtfs:thursday"] == 1) $matchDayOfWeek = TRUE;
	else if($dayOfWeek == 5 && $calendar["gtfs:friday"] == 1) $matchDayOfWeek = TRUE;
	else if($dayOfWeek == 6 && $calendar["gtfs:saturday"] == 1) $matchDayOfWeek = TRUE;
	else if($dayOfWeek == 7 && $calendar["gtfs:sunday"] == 1) $matchDayOfWeek = TRUE;

	// Check that date is between start & end
	// Convert to timestamp
	$start_ts = strtotime($calendar["gtfs:startTime"]);
	$end_ts = strtotime($calendar["gtfs:endTime"]);
	$check_ts = strtotime($date);

	if(($check_ts >= $start_ts) && ($check_ts <= $end_ts)) $matchTime = TRUE;

	return $matchDayOfWeek && $matchTime;
}

function checkCalendarDates($calendar_dates, $date) {
	$isException = FALSE;

	foreach($calendar_dates as $calendar_date) {
		if($calendar_date["dct:date"] == $date && $calendar_date["gtfs:dateAddition"]>0)
			$isException = TRUE;
	}

	return $isException;
}

// header CSVs
makeHeaders();

$hashmap_route_dates = getRoutesWithDates();

foreach ($hashmap_route_dates as $route_short_name => $dates) {

	while(count($dates)) {
		$date = array_shift($dates);

		// processor
		$route = fetchRoute($route_short_name, $date);
		$route_entry = $route[0];
		$trip = $route[1];
		$calendar = $route[2];
		$calendar_dates = $route[3];
		$stop_times = $route[4];

		// content CSVs
		addRoute($route_entry);

		addTrip($trip);

		addCalendar($calendar);

		addCalendarDates($calendar_dates);

		addStopTimes($stop_times);

		// delete dates from $dates with same calendar and calendar_dates
		foreach($dates as $date)
			if(checkCalendar($calendar, $date) || checkCalendarDates($calendar_dates, $date)) unset($date);
	}
}
