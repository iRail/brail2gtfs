<?php
/**
 * This script converts takes the list of routes which has been collected in script 2-routes.tmp.php into routes, trips, calendar dates, calendar entries and stop_times
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */

require '../vendor/autoload.php';

use GuzzleHttp\Client;

/**
 * Fetch Route fetches for a specific day an array of: a route object, a trip, a calendar, calendar dates, and stop times
 */
function fetchRoute ($route, $day) {
    $client = new Client();
    $url = "http://www.belgianrail.be/jp/sncb-nmbs-routeplanner/trainsearch.exe/en?vtModeTs=weekday&productClassFilter=69&clientType=ANDROID&androidversion=3.1.10%20(31397)&hcount=0&maxResults=50&clientSystem=Android21&date=" . $day ."&trainname=" . $route . "&clientDevice=Android%20SDK%20built%20for%20x86&htype=Android%20SDK%20built%20for%20x86&L=vs_json.vs_hap";
    $response = $client->get($url, [
	    'headers' => [
	        'User-Agent'     => 'iRail',
	    ]
	]);
    $body = $response->getBody();
    $body = rtrim($body, ';');
    $object = json_decode($body);
    
    if (isset($object) && is_array($object->suggestions) && isset($object->suggestions[0])) {
        $route_entry = [
            "@id" => "http://irail.be/routes/NMBS/" . $route,
            "@type" => "http://vocab.gtfs.org/terms#Route",
            "gtfs:longName" => $object->suggestions[0]->dep . " - " . $object->suggestions[0]->arr,
            "gtfs:shortName" => $route,
            "gtfs:agency" => "0",
            "gtfs:routeType" => "gtfs:Rail",
        ];
        
        list ($trip, $calendar, $calendar_dates) = parseVTString($object->suggestions[0]->vt);
        
        $stop_times = fetchStopTimes($object->suggestions[0]->journParam);
        
        return [$route_entry, $trip, $calendar, $calendar_dates, $stop_times];
    } else {
        //TODO: log new issue... Something went terribly wrong  - using monolog?
    }
}

// 
function parseVTString ($string) {
    //Example strings:
    // * 13. Apr until 11. Dec 2015 Mo - Fr; not 1., 14., 25. May, 21. Jul, 11. Nov
    // * 2. Feb until 12. Dec 2015; 29. Jun until 28. Aug 2015 Sa, Su; not 7., 8., 21., 22., 28., 29. Mar, 7. until 10. Apr 2015, 13. until 19. Apr 2015, 25., 26. Apr, 1. until 3. May 2015, 9., 10. May, 14. until 17. May 2015, 23. until 25. May 2015, 30., 31. May, 6., 7., 13., 14. Jun; also 21. Jul
    // * 13. Apr until 12. Jun 2015 Mo - Fr; not 1., 14., 25. May
    // * Mo - Fr, not 25. Dec, 1. Jan, 6. Apr, 1., 14., 25. May, 21. Jul, 11. Nov
    // * Sa, Su, not 24., 25. Jan, 7., 8. Feb; also 25. Dec, 1. Jan, 6. Apr, 1., 14., 25. May, 21. Jul, 11. Nov

    // The format can be interpreted like a programming language (e.g., we could use php lime and write a BNF of this first)
    // Strings / characters to parse:
    // ; : denotes that a new expression is about to follow (such as not or also)
    // , : denotes that the thing about to follow can be another day (weekday or specific date), possibly in the same month as the next value
    // not : denotes that something belongs to calendar_dates
    // also: denotes that something is exceptionally in it
    // - : denotes a range between days of the week
    // \d\d?\. Month until \d\d?\. Month Year : validity timestamp
    
    $trip = [];
    $calendar = [];
    $calendar_dates = [];
    
    // First, explode on ;
    $expressions_string_array = explode(";",$string);
    
    for ($i = 0; $i < sizeof($expressions_string_array); $i ++) {
        $expressions_string = $expressions_string_array[$i];
        //detect "not" or "also", these are calendar_dates
        
    }

    return [$trip, $calendar, $calendar_dates];    
}


function parseInterval ($string1, $string2, $year, $month) {

}


function parseDate ($string, $year, $month){

}


function parseCalendarDates() {

}


function fetchStopTimes ($queryString) {

    return [];
}

var_dump(fetchRoute("P8008","15.05.2015"));
