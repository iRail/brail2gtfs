<?php
/**
 * This script converts takes the list of routes which has been collected in script 2-routes.tmp.php into routes, trips, calendar dates, calendar entries and stop_times
 * 
 * Example usage: var_dump(fetchRoute("P8008","15.05.2015"));
 *
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */

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
            "@type" => "gtfs:Route",
            "gtfs:longName" => $object->suggestions[0]->dep . " - " . $object->suggestions[0]->arr,
            "gtfs:shortName" => $route,
            "gtfs:agency" => "0",
            "gtfs:routeType" => "gtfs:Rail", //→ 2 according to GTFS/CSV spec
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
    
    $trip = [];
    $calendar_dates = [];

    //In the first step, we're going to make it ourself a bit easier: the format uses for weekdays: "Mo - Fr" and for weekend: "Sa, Su"
    $string = str_replace("Mo - Fr","WW",$string); // workweek
    $string = str_replace("Sa, Su","WE",$string); // weekend
    
    //We're going to make it ourselves even more easy by saying a ';' has no semantic difference from a ',', so we can make that the same character
    $string = str_replace(";",",",$string);
    
    // The format can now be interpreted like a programming language
    // e.g., 13. Apr until 11. Dec 2015 WW, not 1., 14., 25. May, 21. Jul, 11. Nov
    // Strings / characters to parse:
    // "/^(.*?),(.*)/ → First part describes the calendar, last part describes the calendar_dates
    // "/, ((not|also) )?(.*)/g" → all matches are calendar_dates
    // In calendar:
    //  → if Until → start date and end date
    //  → if WW or WE → range only counts in the week or weekend, if nothing: always
    // In calendar_dates:
    //  → Until → create list of dates for which it counts
    //  → ...
    //
    // There's a difficulty with the dates: months or years are only mentioned at the last item.
    
    // First, explode on ','
    $expressions_string_array = explode(",",$string);
    $calendar_string = array_shift($expressions_string_array);

    //process calendar string
    $monday = true; $tuesday = true; $wednesday = true; $thursday = true; $friday = true; $saturday = true; $sunday = true;
    if (preg_match("/WE$/",$calendar_string)) {
        $monday = false;
        $tuesday = false;
        $wednesday = false;
        $thursday = false;
        $friday = false;
    } else if (preg_match("/WW$/",$calendar_string)) {
        $saturday = false;
        $sunday = false;
    }
    
    //check if there's an until and parse the interval
    $startDate = "15.12.2014";
    $endDate = "15.12.2015";
    if (preg_match("/(\d\d?)\. ([a-z]+ )?(\d\d\d\d )?until (\d\d?)\. ([a-z]+ )?(\d\d\d\d )?/i",$calendar_string, $matches)) {
        //Todo: parse time and put in startDate and endDate
        //var_dump($matches);
    }
    
    $calendar = [
        "@id" => "route + occurence",
        "@type" => "gtfs:CalendarRule",
        "gtfs:monday" => $monday,
        "gtfs:tuesday" => $tuesday,
        "gtfs:wednesday" => $wednesday,
        "gtfs:thursday" => $thursday,
        "gtfs:friday" => $friday,
        "gtfs:saturday" => $saturday,
        "gtfs:sunday" => $sunday,
        "gtfs:startDate" => $startDate, //these gtfs properties don't exist yet, but are going to be added
        "gtfs:endDate" => $endDate
    ];
    //process the rest
    for ($i = 0; $i < sizeof($expressions_string_array); $i ++) {
        $expressions_string = $expressions_string_array[$i];
        //detect "not" or "also", these are calendar_dates
        if (preg_match("/^\s?(not|also)\s(.*)/", $expressions_string)) {
            //todo
            //$calendar_dates = array_merge($calendar_dates, parseCalendarDates());
        }
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
