<?php
/**
 * This script converts the iRail stations to the GTFS stops.txt file
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
require 'vendor/autoload.php';

use GuzzleHttp\Client;

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

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = "stop_id,stop_name,stop_lat,stop_lon,location_type";
appendCSV($dist, $header);

// content
$stops = getStops()->{"@graph"};

$csv = "";
for($i=0; $i<count($stops); $i++){
	$stop = $stops[$i];

	if (preg_match("/NMBS\/(\d+)/i", $stop->{"@id"}, $matches)) {
        $stop_id = 'stops:' . $matches[1];
    }

	$csv .= $stop_id . ",";
	$csv .= $stop->{"name"} . ",";
	$csv .= $stop->{"latitude"} . ",";
	$csv .= $stop->{"longitude"} . ",";
	$csv .= "0"; // what we describe are all parent_stations, which have "platforms"

	appendCSV($dist,$csv);
	$csv = "";
}