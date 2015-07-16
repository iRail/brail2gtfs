<?php
/**
 * This script converts takes the list of routes (routes_info.tmp.txt) which has been collected in script 2-calendar_dates.txt.php into route and stop_time entries
 * 
 *
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
namespace iRail\brail2gtfs;

include_once ("includes/simple_html_dom.php");

use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class RouteFetcher {

    /**
     * Fetch Route fetches for a specific date an array of: a route object and stop_times
     */
    static function fetchRouteAndStopTimes ($shortName, $date, $trip_id, $language) {
        date_default_timezone_set('UTC');

        $dateNMBS = date_create_from_format('Ymd', $date)->format('d/m/Y');
        $serverData = self::getServerData($dateNMBS, $shortName, $language);

        list($route_entry, $stop_times) = self::fetchInfo($serverData, $shortName, $trip_id, $dateNMBS);
        
        return [$route_entry, $stop_times];
    }

    static function fetchInfo($serverData, $shortName, $trip_id, $date) {
        // create a log channel
        $log = new Logger('route_info');
        $log->pushHandler(new StreamHandler('route_info.log', Logger::ERROR));

        $route_entry = null;
        $stopTimes = null;

        $html = str_get_html($serverData);

        $test = $html->getElementById('tq_trainroute_content_table_alteAnsicht');
        if (!is_object($test)) {
            $log->addError('Error with fetching route: ' . $shortName . ' on ' . $date . "\n");
        } else {

            $nodes = $html->getElementById('tq_trainroute_content_table_alteAnsicht')->getElementByTagName('table')->children;

            $stop_sequence = 1; // counter
            $stopTimes = array();
            $route_entry = array();

            // First node is just the header
            for($i=1; $i<count($nodes); $i++){
                $node = $nodes[$i];
                if(!count($node->attr)) continue; // row with no class-attribute contain no data
                
                // Arrival- and departuretimes
                // First stop
                if ($i == 1) {
                    // only departure
                    $departureTime = array_shift($node->children[1]->children[1]->nodes[0]->_);
                    $arrivalTime = "";
                    $departureStation = trim(array_shift($node->children[3]->nodes[0]->_));
                } 
                // Last stop
                else if ($i == count($nodes)-1) {
                    // only arrival
                    $departureTime = "";
                    $arrivalTime = array_shift($node->children[1]->children[0]->nodes[0]->_);
                    $arrivalStation = trim(array_shift($node->children[3]->children[0]->nodes[0]->_));
                } else {
                    $departureTime = array_shift($node->children[1]->children[0]->nodes[0]->_);
                    $arrivalTime = array_shift($node->children[1]->children[2]->nodes[0]->_);
                    // Todo: station and platform
                    // Todo: Find stop_id with stop_name
                }

                // array_push($stopTimes, self::generateStopTimesEntry($trip_id, $arrival_time, $departure_time, $stop_id, $stop_sequence));

                $stop_sequence++;
            }

            $route_entry = self::generateRouteEntry($shortName, $departureStation, $arrivalStation);
        }

        return [$route_entry, $stopTimes];
    }

    static function generateRouteEntry($shortName, $departureStation, $arrivalStation) {
        $route_entry = [
            "@id" => "http://irail.be/routes/NMBS/" . $shortName,
            "@type" => "gtfs:Route",
            "gtfs:longName" => $departureStation . " - " . $arrivalStation,
            "gtfs:shortName" => $shortName,
            "gtfs:agency" => "0",
            "gtfs:routeType" => "gtfs:Rail" //→ 2 according to GTFS/CSV spec
        ];

        return $route_entry;
    }

    static function generateStopTimesEntry($trip_id, $arrival_time, $departure_time, $stop_id, $stop_sequence) {
        $stoptimes_entry = [
            "gtfs:trip" => $trip_id,
            "gtfs:arrivalTime" => $arrival_time,
            "gtfs:departureTime" => $departure_time,
            "gtfs:stop" => $stop_id,
            "gtfs:stopSequence" => $stop_sequence
        ];

        return $stoptimes_entry;
    }

    // Scrapes a route of the Belgian Rail website
    static function getServerData($date, $shortName, $language) {
        $request_options = array(
            "timeout" => "30",
            "useragent" => "GTFS by Project iRail",
        );

        $scrapeURL = "http://www.belgianrail.be/jp/sncb-nmbs-routeplanner/trainsearch.exe/" . $language . "ld=std&seqnr=1&ident=at.02043113.1429435556&";

        $post_data = "trainname=" . $shortName . "&start=Zoeken&selectDate=oneday&date=" . $date . "&realtimeMode=Show";

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
}

