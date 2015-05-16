<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client();
$url = "https://irail.be/stations/NMBS";
$response = $client->get($url, [
    'headers' => [
        'Accept'     => 'application/json',
    ]
]);

$json = $response->getBody();
echo $json;