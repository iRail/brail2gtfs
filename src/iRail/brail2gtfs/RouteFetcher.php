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
namespace iRail\brail2gtfs;

use GuzzleHttp\Client;

class RouteFetcher {

    /**
     * Fetch Route fetches for a specific day an array of: a route object, a trip, a calendar, calendar dates, and stop times
     */
    static function fetchRoute ($route, $day) {
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

            $trip_template = [
                "@id" => $object->suggestions[0]->id, //Sadly, this is only a local identifier
                "@type" => "gtfs:Trip",
                "gtfs:route" => "http://irail.be/routes/NMBS/" . $route,
                "gtfs:service" => $object->suggestions[0]->id, //Sadly, this is only a local identifier, and we use the same id as the trip for service rules
            ];
        
            list ($trips, $calendars, $calendar_dates) = self::parseVTString($object->suggestions[0]->vt, $trip_template);
        
            $stop_times = self::fetchStopTimes($object->suggestions[0]->journParam, $object->suggestions[0]->id);
        
            return [$route_entry, $trips, $calendars, $calendar_dates, $stop_times];
        } else {
            //TODO: log new issue... Something went terribly wrong  - using monolog?
        }
    }

    // id is both the trip id and the service id in our case: we're not going to reuse services across different trips
    static function parseVTString ($string, $trip) {
        //Example strings:
        // * 13. Apr until 11. Dec 2015 Mo - Fr; not 1., 14., 25. May, 21. Jul, 11. Nov
        // * 2. Feb until 12. Dec 2015; 29. Jun until 28. Aug 2015 Sa, Su; not 7., 8., 21., 22., 28., 29. Mar, 7. until 10. Apr 2015, 13. until 19. Apr 2015, 25., 26. Apr, 1. until 3. May 2015, 9., 10. May, 14. until 17. May 2015, 23. until 25. May 2015, 30., 31. May, 6., 7., 13., 14. Jun; also 21. Jul
        // * 20. Apr until 12. Dec 2015; 25. Apr until 14. Jun 2015 Mo - Fr; 29. Jun until 28. Aug 2015 Sa, Su; not 1., 14., 15., 25. May; also 21. Jul
        // * 13. Apr until 12. Jun 2015 Mo - Fr; not 1., 14., 25. May
        // * Mo - Fr, not 25. Dec, 1. Jan, 6. Apr, 1., 14., 25. May, 21. Jul, 11. Nov
        // * Sa, Su, not 24., 25. Jan, 7., 8. Feb; also 25. Dec, 1. Jan, 6. Apr, 1., 14., 25. May, 21. Jul, 11. Nov

        $trips = [];
        $calendar_dates = [];


        //TODO: this code is not working. Strings like "Mo - We, Fr" also occur!
        //In the first step, we're going to make it ourself a bit easier: the format uses for weekdays: "Mo - Fr" and for weekend: "Sa, Su"
        $string = str_replace("Mo - Fr","WW",$string); // workweek
        $string = str_replace("Sa, Su","WE",$string); // weekend
    
        //We're going to make it ourselves even more easy by saying a ';' has no semantic difference from a ',', so we can make that the same character
        $string = str_replace(";",",",$string);
    
        // The format can now be interpreted like a programming language
        // e.g.,
        //  * 13. Apr until 11. Dec 2015 WW, not 1., 14., 25. May, 21. Jul, 11. Nov
        //  * 20. Apr until 12. Dec 2015, 25. Apr until 14. Jun 2015 WW, 29. Jun until 28. Aug 2015 WE, not 1., 14., 15., 25. May; also 21. Jul
        // Strings / characters to parse:
        // "/^(.*?),(.*)/ → First part describes the calendar, last part describes the calendar_dates
        // "/, ((not|also) )?(.*)/g" → all matches are calendar_dates
        // In calendar:
        //  → if Until → start date and end date
        //  → if WW or WE → range only counts in the week or weekend, if nothing: always
        // In calendar_dates:
        //  → See futher on
        
        // First, let's search for intervals among the comma separated values
        $expressions_string_array = explode(",",$string);
    
        // first one is for sure part of the calendar.txt and we can parse it into a calendar
        $calendars = [
            self::parseCalendarString(array_shift($expressions_string_array),$trip["@id"])
        ];
    
        // Now it's possible that we find more expressions that will denote multiple trips
        // the next ones are maybe part of the calendar.txt, but then we will recognize this because of the "until" keyword and when it's not preceded by also or not
        while (preg_match("/^(?!(not|also)).*until.*/", trim($expressions_string_array[0]))) {
            array_push($calendars, self::parseCalendarString(array_shift($expressions_string_array),$trip["@id"]));
        }

        var_dump($calendars);
        exit();
    

        // Now it's a matter of making sure the calendars are disjunct: no overlapping pieces should exist, otherwise we cannot serialise it into GTFS.
        // We should now create the right service ids for the right interval
    
    
        // Process the rest to calendar dates... This can get quite difficult
        //  → Until → create list of dates for which it counts
        //  → ...
        $calendar_dates_string = implode(",",$expressions_string_array);
    
        //now look for not and also and split these in separate chunks
    
    
        for ($i = 0; $i < sizeof($expressions_string_array); $i ++) {
            $expressions_string = $expressions_string_array[$i];
            //detect "not" or "also", these are calendar_dates
            if (preg_match("/^\s?(not|also)\s(.*)/", $expressions_string)) {
                //todo
                //$calendar_dates = array_merge($calendar_dates, parseCalendarDates());
            }
        }

        return [$trips, $calendar, $calendar_dates];    
    }

    static function parseCalendarString ($calendar_string, $id) {
        //process the first calendar string for calendar.txt
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
        $startDate = "2014-12-15";
        $endDate = "2015-12-15";
        if (preg_match("/(\d\d?)\. ([a-z]+ )?(\d\d\d\d )?until (\d\d?)\. ([a-z]+ )?(\d\d\d\d )?/i",$calendar_string, $matches)) {
            list ($startDate, $endDate) = self::parseInterval(trim($matches[1]),trim($matches[2]),trim($matches[3]), trim($matches[4]),trim($matches[5]),trim($matches[6]),"",2015);
        }
    
        $calendar = [
            "gtfs:service" => $id, //this is just a local identifier sadly
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

        return $calendar;
    }


    static function parseInterval ($day1, $month1, $year1, $day2, $month2, $year2, $defaultmonth, $defaultyear) {
        if ($year2 === "") {
            $year2 = $defaultyear;
        }
        if ($year1 === "") {
            $year1 = $year2;
        }
        if ($month2 === "") {
            $month2 = $defaultmonth;
        }
        if ($month1 === "") {
            $month1 = $month2;
        }
        return [
            $year1 . "-" . self::monthToNumber($month1) . "-" . $day1,
            $year2 . "-" . self::monthToNumber($month2) . "-" . $day2,
        ];
    }

    static function monthToNumber($str) {
        $months = ["Jan" => "01","Feb" => "01","Mar" => "03","Apr" => "04","May" => "05","Jun" => "06","Jul" => "07","Aug" => "08","Sep" => "09","Oct" => "10","Nov" => "11","Dec" => "12"];
        return $months[$str];
    }


    static function parseDate ($string, $year, $month){

    }


    static function parseCalendarDates() {

    }


    static function fetchStopTimes ($queryString, $id) {

        return [];
    }
}

