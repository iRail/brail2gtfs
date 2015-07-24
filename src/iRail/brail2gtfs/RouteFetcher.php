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
    static function fetchRouteAndStopTimes ($shortName, $date, $trip_id, $service_id, $language) {
        date_default_timezone_set('UTC');

        $dateNMBS = date_create_from_format('Ymd', $date)->format('d/m/Y');

        $serverData = self::getServerData($dateNMBS, $shortName, $language);

        list($route_entry, $stop_times, $serviceId_date_pair) = self::fetchInfo($serverData, $shortName, $trip_id, $service_id, $dateNMBS, $date, $language);
        
        return [$route_entry, $stop_times, $serviceId_date_pair];
    }

    static function fetchInfo($serverData, $shortName, $trip_id, $service_id, $date, $dateGTFS, $language) {
        var_dump($shortName);
        var_dump($date);

        // create a log channel
        $log = new Logger('route_not_driving');
        $log->pushHandler(new StreamHandler('route_not_driving.log', Logger::ERROR));

        $route_entry = null;
        $stopTimes = null;
        $serviceId_date_pair = null;
        $stations = null; // Used when there are stationnames that don't have a URL, so no stop_ids can be parsed

        $html = str_get_html($serverData);

        $test = $html->getElementById('tq_trainroute_content_table_alteAnsicht');
        if (!is_object($test)) {
            // Trainroute splits. Route_id is of the main train, so take the link that drives
            if (is_object($html->getElementByTagName('table'))) {
                $url = array_shift($html->getElementByTagName('table')->children[1]->children[0]->children[0]->{"attr"});
                if (self::drives($url)) {
                    $serverData = self::getServerDataByUrl($url);
                    list($route_entry, $stopTimes, $serviceId_date_pair) = self::fetchInfo($serverData, $shortName, $trip_id, $service_id, $date, $dateGTFS, $language);
                } else {
                    // Second url
                    $url = array_shift($html->getElementByTagName('table')->children[2]->children[0]->children[0]->{"attr"});
                    $serverData = self::getServerDataByUrl($url);
                    list($route_entry, $stopTimes, $serviceId_date_pair) = self::fetchInfo($serverData, $shortName, $trip_id, $service_id, $date, $dateGTFS, $language);
                }
            } else {
                $log->addError('Train not driving: ' . $shortName . ' on ' . $date . "\n");
                $serviceId_date_pair = array();
                $pair = array($service_id, $dateGTFS);
                array_push($serviceId_date_pair, $pair);
            }
        } else {

            $nodes = $html->getElementById('tq_trainroute_content_table_alteAnsicht')->getElementByTagName('table')->children;

            $stop_sequence = 1; // counter
            $stopTimes = array();
            $route_entry = array();
            $spansMultipleDates = false;

            // First node is just the header
            $i = 1; // Pointer to node
            while (count($nodes) > $i) {
                $node = $nodes[$i];
                if(!count($node->attr)) {
                    $i++;
                    continue; // row with no class-attribute contain no data
                }

                // Arrival- and departuretimes
                // First stop
                if ($stop_sequence == 1) {
                    // only departure
                    $departureTime = array_shift($node->children[1]->children[1]->nodes[0]->_);
                    $arrivalTime = $departureTime;
                    // Stop_name
                    $stop_name = trim(array_shift($node->children[3]->nodes[0]->_));
                    if ($stop_name == '') {
                      // Stop_name has URL
                      $stop_name = trim(array_shift($node->children[3]->children[0]->nodes[0]->_));
                    }

                    $departureStation = $stop_name;
                } 
                // Last stop
                else if ($i == count($nodes)-1) {
                    // only arrival
                    $arrivalTime = array_shift($node->children[1]->children[0]->nodes[0]->_);
                    $departureTime = $arrivalTime;
                    // Stop_name
                    $stop_name = trim(array_shift($node->children[3]->nodes[0]->_));
                    if ($stop_name == '') {
                      // Stop_name has URL
                      $stop_name = trim(array_shift($node->children[3]->children[0]->nodes[0]->_));
                    }
                    $arrivalStation = $stop_name;
                } else {
                    $arrivalTime = array_shift($node->children[1]->children[0]->nodes[0]->_);
                    $departureTime = array_shift($node->children[1]->children[2]->nodes[0]->_);
                    // Stop_name
                    $stop_name = trim(array_shift($node->children[3]->nodes[0]->_));
                    if ($stop_name == '') {
                      // Stop_name has URL
                      $stop_name = trim(array_shift($node->children[3]->children[0]->nodes[0]->_));
                    }
                }
                
                // Stop_id
                // Can be parsed from the stop-URL
                if (isset($node->children[3]->children[0])) {
                    $link = $node->children[3]->children[0]->{"attr"}["href"];
                    // With capital C
                    if (strpos($link, "StationId=")) {
                        $nr = substr($link, strpos($link, "StationId=") + strlen("StationId="));
                    } else {
                        $nr = substr($link, strpos($link, "stationId=") + strlen("stationId="));
                    }
                    $nr = substr($nr, 0, strlen($nr) - 1); // delete ampersand on the end
                    $stop_id = 'stops:' . '00' . $nr;
                } else {
                    // With foreign stations, there's a sometimes no URL available
                    // Find the stop_id with the stations.json
                    if ($stations == null) {
                        $stations = self::getStations();
                    }
                    ////////////// TEMPORARY TILL NEW STATIONS.CSV ONLINE
                    if ($stop_name == 'Siegburg (d)') {
                        $stop_id = 'stops:008015588';
                    } else if ($stop_name == 'Limburg Sud (d)') {
                        $stop_id = 'stops:008032572';
                    } else {
                    ///////////////////////////////////////////////////////
                        $matches = self::getMatches($stations, $stop_name);
                        $stop_id = self::getBestMatchId($matches, $stop_name, $language);

                        if (preg_match("/NMBS\/(\d+)/i", $stop_id,$matches)) {
                            $stop_id = 'stops:' . $matches[1];
                        }
                    }
                }                

                // Has platform
                if (count($node->children) > 5 && trim(array_shift($node->children[5]->nodes[0]->_)) != '&nbsp;') {
                    $platform = trim(array_shift($node->children[5]->nodes[0]->_));
                } else if (is_object($node->children[4])) {
                    $platform = "";
                } else {
                    $platform = "";
                }

                // Times must be eight digits in HH:MM:SS format
                $arrivalTime .= ':00';
                $departureTime .= ':00';

                // Check if arrival- and departuretime spans multiple dates
                // First convert to datetime-object
                $arrivalDateTime = date_create_from_format('H:i:s', $arrivalTime);
                $departureDateTime = date_create_from_format('H:i:s', $departureTime);

                if (isset($previousDateTime) && ($arrivalDateTime < $previousDateTime || $departureTime < $arrivalTime)) {
                    $spansMultipleDates = true;
                }

                if ($spansMultipleDates) {
                    $borderTime = date_create_from_format('H:i:s','00:00:00');
                    if ($arrivalDateTime <= $departureDateTime && $arrivalDateTime >= $borderTime) {
                        $temp = $arrivalDateTime;
                        $arrivalTime = ($temp->format('H') + 24) . ':' . $temp->format('i:s');
                    }
                    $temp = $departureDateTime;
                    $departureTime = ($temp->format('H') + 24) . ':' . $temp->format('i:s');
                }

                array_push($stopTimes, self::generateStopTimesEntry($trip_id, $arrivalTime, $departureTime, $stop_id, $stop_sequence));

                $previousDateTime = $departureDateTime;
                $stop_sequence++;
                $i++;
            }

            $route_entry = self::generateRouteEntry($shortName, $departureStation, $arrivalStation);
        }

        return [$route_entry, $stopTimes, $serviceId_date_pair];
    }

    // Scrapes one route
    static function drives($url) {
        $request_options = array(
                "timeout" => "30",
                "useragent" => "iRail.be by Project iRail",
                );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));   
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $request_options["timeout"]);
        curl_setopt($ch, CURLOPT_USERAGENT, $request_options["useragent"]);
        $result = curl_exec($ch);
        curl_close ($ch);

        $html = str_get_html($result);
        $test = $html->getElementById('tq_trainroute_content_table_alteAnsicht');

        return is_object($test);
    }

    // Gets called when route is split
    static function hasDifferentDestination($serverData) {
        $html = str_get_html($serverData);

        if (isset($html->getElementsByTagName('table')->children)) {
            $nodes = $html->getElementsByTagName('table')->children;

            $node_one = array_shift($nodes);
            $node_two = array_shift($nodes);

            // 
            $route_short_name_one = preg_replace('/\s+/', '',$node->children[0]->children[0]->children[0]->attr{"alt"});
            $destination_one = array_shift($node->children[2]->nodes[0]->_);
            $url_one = array_shift($node->children[0]->children[0]->{'attr'});

            $route_short_name_two = preg_replace('/\s+/', '',$node->children[0]->children[0]->children[0]->attr{"alt"});
            $destination_two = array_shift($node->children[2]->nodes[0]->_);
            $url_two = array_shift($node->children[0]->children[0]->{'attr'});
            } else {
                // Last one
                // make next from the previous to keep our code
                $next_route_short_name = $previous_route_short_name;
                $next_destination = $previous_destination;
            }

            $drives = true;
    }

    static function generateRouteEntry($shortName, $departureStation, $arrivalStation) {
        $route_entry = [
            "@id" => "routes:" . $shortName,
            "@type" => "gtfs:Route",
            "gtfs:longName" => $departureStation . " - " . $arrivalStation,
            "gtfs:shortName" => $shortName,
            "gtfs:agency" => "0",
            "gtfs:routeType" => "2" //→ 2 according to GTFS/CSV spec
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

    static function getServerDataByUrl($scrapeURL) {
        $request_options = array(
            "timeout" => "30",
            "useragent" => "GTFS by Project iRail",
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $scrapeURL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));   
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $request_options["timeout"]);
        curl_setopt($ch, CURLOPT_USERAGENT, $request_options["useragent"]);
        $result = curl_exec($ch);

        curl_close ($ch);

        return $result;
    }

    static function getStations() {
        $client = new Client();
        $url = "https://irail.be/stations/NMBS";
        $response = $client->get($url, [
            'headers' => [
                'Accept'     => 'application/json',
            ]
        ]);

        $json = $response->getBody();

        return json_decode($json, true);
    }

    // Stations as parameter, so we have to load it once
    static function getMatches($stations, $query) {
        // Hardcoded some stations that NMBS gives different names to
        if ($query == 'Frankfurt Main (d)') {
            $query = 'Frankfurt am Main Flughafen';
        } else if ($query == 'Frankfurt Flugh (d)') {
            $query = 'Frankfurt am Main Hbf';
        } else if ($query == 'Ettelbruck (l)') {
            $query = 'Ettelbréck';
        } else if ($query == 'Kautenbach (l)') {
            $query = 'Kautebaach';
        } else if ($query == 'Koln Hbf (d)') {
            $query = 'Köln Hbf';
        }

        // Delete ('country-abbreviation') if present
        if (strpos($query, '(') < strlen($query)) {
            $query = substr($query, 0, strpos($query, '(') - 2);
        }

        // var_dump($stations);
        $newstations = new \stdClass;
        $newstations->{"@id"} = $stations["@id"];
        $newstations->{"@context"} = $stations["@context"];
        $newstations->{"@graph"} = array();
        
        //make sure something between brackets is ignored
        $query = preg_replace("/\s?\(.*?\)/i", "", $query);
        
        // st. is the same as Saint
        $query = preg_replace("/st(\s|$)/i", "(saint|st|sint) ", $query);
        //make sure that we're only taking the first part before a /
        $query = explode("/", $query);
        $query = trim($query[0]);
        
        // Dashes are the same as spaces
        $query = self::normalizeAccents($query);
        $query = str_replace("\-", "[\- ]", $query);
        $query = str_replace(" ", "[\- ]", $query);
        
        $count = 0;
        foreach ($stations["@graph"] as $station) {
            if (preg_match('/.*' . $query . '.*/i', self::normalizeAccents($station["name"]), $match)) {
                $newstations->{"@graph"}[] = $station;
                $count++;
            } elseif (isset($station->alternative)) {
                if (is_array($station->alternative)) {
                    foreach ($station->alternative as $alternative) {
                        if (preg_match('/.*(' . $query . ').*/i', self::normalizeAccents($alternative->{"@value"}), $match)) {
                            $newstations->{"@graph"}[] = $station;
                            $count++;
                            break;
                        }
                    }
                } else {
                    if (preg_match('/.*' . $query . '.*/i', self::normalizeAccents($station->alternative->{"@value"}))) {
                        $newstations->{"@graph"}[] = $station;
                        $count++;
                    }
                }
            }
            if ($count > 5) {
                return $newstations;
            }
        }
        return $newstations;
    }

    /**
     * @param $str
     * @return string
     * Languages supported are: German, French and Dutch
     * We have to take into account that some words may have accents
     * Taken from https://stackoverflow.com/questions/3371697/replacing-accented-characters-php
     */
    static function normalizeAccents($str)
    {
        $unwanted_array = array(
            'Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O',
            'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y',
            'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a',
            'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
            'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ý' => 'y', 'ý' => 'y', 'þ' => 'b',
            'ÿ' => 'y'
        );
        
        return strtr($str, $unwanted_array);
    }

    static function getBestMatchId($matches, $stop_name, $language) {
        $max_percent = 0; // Percentage of similarity of the best match
        $stop_id = "";

        foreach ($matches->{"@graph"} as $match) {
            // First check if the stationname is available in the same language
            if (isset($match["alternative"])) {
                $stationName = self::getAlternativeName($match["alternative"], $language);
            } else {
                $stationName = NULL;
            }

            if ($stationName == NULL) {
                // Use the standardName
                $stationName = $match["name"];
            }

            similar_text($stationName, $stop_name, $percent);
            if ($percent > $max_percent) {
                $stop_id =  $match["@id"];
                $max_percent = $percent;
            }
        }

        return $stop_id;
    }

    static function getAlternativeName($stationsByLang, $language) {
        // Dutch
        if ($language == "nn") {
            $language = "nl";
        }

        foreach ($stationsByLang as $s) {
            if (isset($s["@language"])) {
                return $s["@value"];
            }
        }

        return NULL;
    }
}

