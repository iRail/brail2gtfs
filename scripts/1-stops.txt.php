<?php
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
$header = "stop_id,stop_name,stop_lat,stop_long,location_type";
appendCSV($dist, $header);

// content
$stops = getStops()->{"@graph"};

$csv = "";
for($i=0; $i<count($stops); $i++){
	$stop = $stops[$i];

	$csv .= $stop->{"@id"} . ",";
	$csv .= $stop->{"name"} . ",";
	$csv .= $stop->{"latitude"} . ",";
	$csv .= $stop->{"longitude"} . ",";
	$csv .= "1"; // what we describe are all parent_stations, which have "platforms"

	appendCSV($dist,$csv);
	$csv = "";
}


