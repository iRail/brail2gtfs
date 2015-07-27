<?php
/**
 * This script converts the iRail stations to the GTFS stops.txt file
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;

$configs = include('config.php');

$dist = "dist/stops.txt";

function getStops(){
	$client = new Client();
	$url = "https://irail.be/stations/NMBS";
	$response = $client->get($url, [
	    'headers' => [
	        'Accept'     => 'application/json',
	    ]
	]);

	$json = $response->getBody();

	return json_decode($json);
}

function getNumberAndLetterPairOfPlatforms($url) {
	$client = new Client();

	try {
		$response = $client->get($url, [
		    'headers' => [
		        'Accept'     => 'application/json',
		    ]
		]);
	} catch (RequestException $e) {
	    return NULL;
	} catch (ServerException $e) {
		return NULL;
	}

	$arrivals = json_decode($response->getBody())->{"@graph"};

	// Loop through train-arrivals and keep biggest platformnumber
	$maxNr = NULL;
	$maxLetter = NULL;
	for ($i=0; $i < count($arrivals); $i++) {
		$platform = $arrivals[$i]->{"platform"};
		if ($platform != "") {
			if (is_numeric($platform)) {
				// Regular platform number
				if ($platform > $maxNr) {
					$maxNr = $platform;
				}
			} else {
				// Platform is a letter
				if ($platform > $maxLetter) {
					$maxLetter = $platform;
				}
			}
		}		
	}

	return [$maxNr, $maxLetter];
}

function getStopName($parent_name, $platformNr) {
	global $configs;

	if ($configs["language"] == 'en') {
		$stop_name = $parent_name . ' platform ' . $platformNr;
	} else if ($configs["language"] == 'fr') {
		$stop_name = $parent_name . ' quai ' . $platformNr;
	} else if ($configs["language"] == 'de') {
		$stop_name = $parent_name . ' bahnsteig ' . $platformNr;
	} else {
		$stop_name = $parent_name . ' perron ' . $platformNr;
	}

	return $stop_name;
}

function addStop($stop_id, $stop_name, $stop_lat, $stop_lon, $stop_station_type) {
	global $dist; 

	$csv = "";
	$csv .= $stop_id . ",";
	$csv .= $stop_name . ",";
	$csv .= $stop_lat . ",";
	$csv .= $stop_lon . ",";
	$csv .= $stop_station_type;

	appendCSV($dist,$csv);
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = "stop_id,stop_name,stop_lat,stop_lon,location_type";
appendCSV($dist, $header);

// content
$stops = getStops()->{"@graph"};

for ($i=0; $i<count($stops); $i++) {
	$stop = $stops[$i];

	$parent_id = $stop->{"@id"};
	if (preg_match("/NMBS\/(\d+)/i", $parent_id, $matches)) {
        $parent_stop_id = 'stops:' . $matches[1];
    }
    $parent_name = $stop->{"name"};
    $parent_lat = $stop->{"latitude"};
    $parent_lon = $stop->{"longitude"};

    // what we describe are all parent_stations, which have "platforms"
    // Because we don't always the platforms of a trip, we need to add these parent_stations as normal stops
    $parent_station_type = 0;

    // Add parent station as stop
    addStop($parent_stop_id, $parent_name, $parent_lat, $parent_lon, $parent_station_type);

	// Search highest platformnumber of station
	// Add different platforms: stop_id of station + # + number of platform 
	$nrAndLetter = getNumberAndLetterPairOfPlatforms($parent_id);

	$nr = $nrAndLetter[0];
	$letter = $nrAndLetter[1];

	// Add stop for every platform with a number
	if ($nr != null) {
		$j = 1;
		while ($j <= $nr) {
			$stop_id = $parent_stop_id . ':' . $j;

			$stop_name = getStopName($parent_name, $j);
			$stop_type = 0;

			// Todo: get latitude and longitude of every platform
			addStop($stop_id, $stop_name, $parent_lat, $parent_lon, $stop_type);

			$j++;
		}
	}
	// Add stop for every platform with a letter
	if ($letter != null) {
		$j = 'A';
		while ($j <= $letter) {
			$stop_id = $parent_stop_id . ':' . $j;

			$stop_name = getStopName($parent_name, $j);
			$stop_type = 0;

			// Todo: get latitude and longitude of every platform
			addStop($stop_id, $stop_name, $parent_lat, $parent_lon, $stop_type);

			$j++;
		}
	}
}