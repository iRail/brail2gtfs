<?php
/**
 * TODO: php unit
 * Right now, you can run this by the command line to test the fetch route function
 */
require '../vendor/autoload.php';
use iRail\brail2gtfs\RouteFetcher;

var_dump(RouteFetcher::fetchRoute("P8008","15.05.2015"));
