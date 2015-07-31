<?php
/**
 * This script converts the iRail stations to the GTFS stops.txt file
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
require 'vendor/autoload.php';

include_once ("includes/simple_html_dom.php");

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;

// set the default timezone to use. Available since PHP 5.1
date_default_timezone_set('UTC');

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

// Scrapes list of routes of the Belgian Rail website
function getServerData($stationId) {
	$request_options = array(
        "timeout" => "30",
        "useragent" => "iRail.be by Project iRail",
    );
	$currentDate = date('d/m/Y');
	$time = '10:35';
	$numberOfResults = '50';
	$scrapeURL = "http://www.belgianrail.be/jpm/sncb-nmbs-routeplanner/stboard.exe/nox"
	            . "?input=" . $stationId . "&date=" . $currentDate . "&time=" . $time . "&";

    $post_data = "maxJourneys=" . $numberOfResults . "&boardType=dep"
                . "&productsFilter=0111111000&start=yes";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $scrapeURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));  
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $request_options["timeout"]);
    curl_setopt($ch, CURLOPT_USERAGENT, $request_options["useragent"]);
    $result = curl_exec($ch);

    curl_close ($ch);

  return $result;
}

function getNumberAndLetterPairOfPlatforms($stationId) {

	$result = getServerData($stationId);

	$html = str_get_html($result);

	$maxNr = NULL;
	$maxLetter = NULL;

	$test = $html->getElementById('hfs_content');
    if (!is_object($test)) {
        var_dump('No connection or bad response.');
    } else {
        $nodes = $html->getElementById('hfs_content')->children;

        $i = 1; // Pointer to node
        while (count($nodes) > $i) {
            $node = $nodes[$i];

            if($node->{'attr'}['class'] != "journey") {
            	$i++;
            	continue; // row with no class-attribute contain no data
            }

            $platform = trim(array_shift($node->nodes[6]->_));
            $start = strpos($platform, ' ') + 1;
            $platform = substr($platform, $start);

            // Loop through train-arrivals and keep biggest platformnumber
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

            $i++;
        }
	}

	return [$maxNr, $maxLetter];
}

function addStop($stop_id, $stop_name, $stop_lat, $stop_lon, $platform_code, $parent_station, $location_type) {
	global $dist; 

	$csv = "";
	$csv .= $stop_id . ",";
	$csv .= $stop_name . ",";
	$csv .= $stop_lat . ",";
	$csv .= $stop_lon . ",";
	$csv .= $platform_code . ",";
	$csv .= $parent_station . ",";
	$csv .= $location_type;

	appendCSV($dist,$csv);
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = "stop_id,stop_name,stop_lat,stop_lon,platform_code,parent_station,location_type";
appendCSV($dist, $header);

// content
$stops = getStops()->{"@graph"};

for ($i=0; $i<count($stops); $i++) {
	$stop = $stops[$i];

	$parent_id = $stop->{"@id"};
	if (preg_match("/NMBS\/(\d+)/i", $parent_id, $matches)) {
		$stationId = $matches[1];
        $parent_stop_id = 'stops:' . $matches[1];
    }
    $parent_name = $stop->{"name"};
    $parent_lat = $stop->{"latitude"};
    $parent_lon = $stop->{"longitude"};

    // what we describe are all parent_stations, which have "platforms"
    // Because we don't always the platforms of a trip, we need to add these parent_stations as normal stops
    $parent_station_type = 1;

    // Add parent station as stop
    addStop($parent_stop_id, $parent_name, $parent_lat, $parent_lon, '', '', $parent_station_type);

    // Add stop when no platform number is known
    // Leave platform_code attribute empty
    addStop($parent_stop_id . ':0', $parent_name, $parent_lat, $parent_lon, '', $parent_stop_id, 0);

	// Search highest platformnumber of station
	// Add different platforms: stop_id of station + # + number of platform 
	$nrAndLetter = getNumberAndLetterPairOfPlatforms($stationId);

	$nr = $nrAndLetter[0];
	$letter = $nrAndLetter[1];

	// Add stop for every platform with a number
	if ($nr != null) {
		$j = 1;
		while ($j <= $nr) {
			$stop_id = $parent_stop_id . ':' . $j;

			$stop_name = $parent_name;
			$stop_type = 0;

			// Todo: get latitude and longitude of every platform
			addStop($stop_id, $stop_name, $parent_lat, $parent_lon, $j, $parent_stop_id, $stop_type);

			$j++;
		}
	}
	// Add stop for every platform with a letter
	if ($letter != null) {
		$j = 'A';
		while ($j <= $letter) {
			$stop_id = $parent_stop_id . ':' . $j;

			$stop_name = $parent_name;
			$stop_type = 0;

			// Todo: get latitude and longitude of every platform
			addStop($stop_id, $stop_name, $parent_lat, $parent_lon, $j, $parent_stop_id, $stop_type);

			$j++;
		}
	}
}