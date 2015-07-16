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

include_once ("../../../includes/simple_html_dom.php");

use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class RouteFetcher {

    /**
     * Fetch Route fetches for a specific date an array of: a route object and stop_times
     */
    static function fetchRouteAndStopTimes ($shortName, $date, $trip_id) {
        $dateNMBS = date('Ymd', $date);
        $serverData = getServerData($dateNMBS, $shortName);

        list($route_entry, $stop_times) = self::fetchInfo($serverData, $shortName, $trip_id);

        return [$route_entry, $stop_times];
    }

    static function fetchInfo($serverData, $shortName, $trip_id) {
        // create a log channel
        $log = new Logger('route_info');
        $log->pushHandler(new StreamHandler('route_info.log', Logger::ERROR));

        $stops = array();
        $html = str_get_html($serverData);
        $nodes = $html->getElementById('tq_trainroute_content_table_alteAnsicht')->getElementByTagName('table')->children;

        if (is_object($nodes)) {

            // First node is just the header
            for($i=1; $i<count($nodes); $i++){
                $node = $nodes[$i];
                if(!count($node->attr)) continue; // row with no class-attribute contain no data
                
                // Todo                
                $departureStation = "";
                $arrivalStation = "";

                
            }            
        } else {
            $log->addError('Caught exception: ' .  $e->getMessage() . "\n" . 'Route: ' . $shortName . "\n");
            $route_entry = null;
        }

        return $route_entry;
    }

    static function generateRouteEntry($shortName, $departureStation, $arrivalStation) {
        $route_entry = [
            "@id" => "http://irail.be/routes/NMBS/" . $shortName,
            "@type" => "gtfs:Route",
            "gtfs:longName" => $departureStation . " - " . $arrivalStation,
            "gtfs:shortName" => $shortName,
            "gtfs:agency" => "0",
            "gtfs:routeType" => "gtfs:Rail", //→ 2 according to GTFS/CSV spec
        ];

        return $route_entry;
    }

    static function generateStopTimesEntry() {

    }

    static function fetchStopTimes ($serverData, $trip_id) {
        
        return [];
    }

    // Scrapes a route of the Belgian Rail website
    static function getServerData($date, $shortName) {
        $request_options = array(
                "timeout" => "30",
                "useragent" => "iRail.be by Project iRail",
                );
        $scrapeURL = "http://www.belgianrail.be/jp/sncb-nmbs-routeplanner/trainsearch.exe/nn?ld=std&AjaxMap=CPTVMap&seqnr=1&";

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

