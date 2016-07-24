<?php

/**
 * This script converts takes the list of routes (routes_info.tmp.txt)
 * which has been collected in script 2-calendar_dates.txt.php into route and stop_time entries.
 *
 *
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
namespace iRail\brail2gtfs;

include_once 'includes/simple_html_dom.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use irail\stations\Stations;

class RouteFetcher
{
    /**
     * Fetch Route fetches for a specific date an array of: a route object and stop_times.
     *
     * @param $shortName
     * @param $date
     * @param $trip_id
     * @param $service_id
     * @param $language
     *
     * @return array
     */
    public static function fetchRouteAndStopTimes($shortName, $date, $trip_id, $service_id, $language)
    {
        date_default_timezone_set('UTC');

        $dateNMBS = date_create_from_format('Ymd', $date)->format('d/m/Y');

        $serverData = self::getServerData($dateNMBS, $shortName, $language);

        list($route_entry, $stop_times, $serviceId_date_pair) = self::fetchInfo($serverData, $shortName, $trip_id, $service_id, $dateNMBS, $date, $language);

        return [$route_entry, $stop_times, $serviceId_date_pair];
    }

    /**
     * @param $serverData
     * @param $shortName
     * @param $trip_id
     * @param $service_id
     * @param $date
     * @param $dateGTFS
     * @param $language
     *
     * @return array
     */
    public static function fetchInfo($serverData, $shortName, $trip_id, $service_id, $date, $dateGTFS, $language)
    {
        // create a log channel
        $log = new Logger('route_not_driving');
        $log->pushHandler(new StreamHandler('route_not_driving.log', Logger::ERROR));

        $route_entry = null;
        $stopTimes = null;
        $serviceId_date_pair = null;
        $stations = null; // Used when there are stationnames that don't have a URL, so no stop_ids can be parsed

        $html = str_get_html($serverData);

        $test = $html->getElementById('tq_trainroute_content_table_alteAnsicht');
        if (! is_object($test)) {
            // Trainroute splits. Route_id is of the main train, so take the link that drives
            if (is_object($html->getElementByTagName('table'))) {
                $url = array_shift($html->getElementByTagName('table')->children[1]->children[0]->children[0]->{'attr'});
                if (self::drives($url)) {
                    $serverData = self::getServerDataByUrl($url);
                    list($route_entry, $stopTimes, $serviceId_date_pair) = self::fetchInfo($serverData, $shortName, $trip_id, $service_id, $date, $dateGTFS, $language);
                } else {
                    // Second url
                    $url = array_shift($html->getElementByTagName('table')->children[2]->children[0]->children[0]->{'attr'});
                    $serverData = self::getServerDataByUrl($url);
                    list($route_entry, $stopTimes, $serviceId_date_pair) = self::fetchInfo($serverData, $shortName, $trip_id, $service_id, $date, $dateGTFS, $language);
                }
            } else {
                $log->addError('Train not driving: '.$shortName.' on '.$date."\n");
                $serviceId_date_pair = [];
                $pair = [$service_id, $dateGTFS];
                array_push($serviceId_date_pair, $pair);
            }
        } else {
            $nodes = $html->getElementById('tq_trainroute_content_table_alteAnsicht')->getElementByTagName('table')->children;

            $stop_sequence = 1; // counter
            $stopTimes = [];
            $route_entry = [];
            $spansMultipleDates = false;
            $departureStation = null;
            $arrivalStation = null;

            for ($i = 1; $i < count($nodes); $i++) {
                $node = $nodes[$i];
                if (! count($node->attr)) {
                    continue;
                } // row with no class-attribute contain no data

                $spans = $node->children[1]->find('span');
                $arrivalTime = reset($spans[0]->nodes[0]->_);
                if (count($spans) > 1) {
                    $departureTime = reset($spans[1]->nodes[0]->_);
                } else {
                    $departureTime = $arrivalTime;
                }
                if (count($node->children[3]->find('a'))) {
                    $as = $node->children[3]->find('a');
                    $stop_name = reset($as[0]->nodes[0]->_);
                } else {
                    $stop_name = reset($node->children[3]->nodes[0]->_);
                }

                // Set departure station for routes.txt
                if ($departureStation == null) {
                    $departureStation = $stop_name;
                }
                // Stop_id
                // Can be parsed from the stop-URL
                if (isset($node->children[3]->children[0])) {
                    $link = $node->children[3]->children[0]->{'attr'}['href'];
                    // With capital C
                    if (strpos($link, 'StationId=')) {
                        $nr = substr($link, strpos($link, 'StationId=') + strlen('StationId='));
                    } else {
                        $nr = substr($link, strpos($link, 'stationId=') + strlen('stationId='));
                    }
                    $nr = substr($nr, 0, strlen($nr) - 1); // delete ampersand on the end
                    $stop_id = $nr;
                } else {
                    // With foreign stations, there's a sometimes no URL available
                    if ($stop_name == 'Moutiers Sb Les B (f)') {
                        $stop_id = '8774172'; // Don't know where I found this
                    } elseif ($stop_name == 'Dommeldange (l)') {
                        $stop_id = '8000001'; // To be found: https://github.com/iRail/stations/issues/82
                    } elseif ($stop_name == 'Limburg sud (d)') {
                        $stop_id = '8032572';
                    } elseif ($stop_name == 'Aeroport Cdg Tgv (f)') {
                        $stop_id = '8727149';
                    } elseif ($stop_name == 'Tgv Haute Picardie (f)') {
                        $stop_id = '8731388';
                    } elseif ($stop_name == 'Dortmund Hbf (d)') {
                        $stop_id = '8010053';
                    } elseif ($stop_name == 'Essen Hbf `') {
                        $stop_id = '8821402';
                    } elseif ($stop_name == 'Duesseldorf Flughafen (d)') {
                        $stop_id = '8039904';
                    } elseif ($stop_name == 'Duesseldorf Hbf (d) ') {
                        $stop_id = '8008094';
                    } elseif ($stop_name == 'Koln Hbf (d)') {
                        $stop_id = '8015458';
                    } elseif ($stop_name == '') {
                    } else {
                        $stop_id = substr(str_replace('http://irail.be/stations/NMBS/', '', Stations::getStations($stop_name)->{'@graph'}[0]->{'@id'}), 2);
                    }
                }

                // Has platform
                if (count($node->children) == 6) {
                    $platform = trim(array_shift($node->children[5]->nodes[0]->_));
                    if ($platform == '&nbsp;') {
                        $platform = '0';
                        $stop_id .= ':'.$platform;
                    } else {
                        // Add platform to stop_id
                        $stop_id .= ':'.$platform;
                    }
                } else {
                    $platform = '0';
                    $stop_id .= ':'.$platform;
                }

                // Can happen
                if ($departureTime == '') {
                    $departureTime = $arrivalTime;
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
                    $borderTime = date_create_from_format('H:i:s', '00:00:00');
                    if ($arrivalDateTime <= $departureDateTime && $arrivalDateTime >= $borderTime) {
                        $temp = $arrivalDateTime;
                        $arrivalTime = ($temp->format('H') + 24).':'.$temp->format('i:s');
                    }
                    $temp = $departureDateTime;
                    $departureTime = ($temp->format('H') + 24).':'.$temp->format('i:s');
                }

                array_push($stopTimes, self::generateStopTimesEntry($trip_id, $arrivalTime, $departureTime, $stop_id, $stop_sequence));

                $previousDateTime = $departureDateTime;
                $stop_sequence++;
            }

            // Set arrival station for routes.txt
            if ($arrivalStation == null) {
                $arrivalStation = $stop_name;
            }

            $route_entry = self::generateRouteEntry($shortName, $departureStation, $arrivalStation);
        }

        return [$route_entry, $stopTimes, $serviceId_date_pair];
    }

    /**
     * Scrapes one route.
     *
     * @param $url
     *
     * @return bool
     */
    public static function drives($url)
    {
        $request_options = [
            'timeout'   => '30',
            'useragent' => 'iRail.be by Project iRail',
        ];

        $html = true;
        while (is_bool($html)) {
            echo 'HTTP GET - '.$url."\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $request_options['timeout']);
            curl_setopt($ch, CURLOPT_USERAGENT, $request_options['useragent']);
            $result = curl_exec($ch);
            curl_close($ch);

            $html = str_get_html($result);
        }

        $test = $html->getElementById('tq_trainroute_content_table_alteAnsicht');

        return is_object($test);
    }

    /**
     * Gets called when route is split.
     *
     * @param $serverData
     */
    public static function hasDifferentDestination($serverData)
    {
        $html = str_get_html($serverData);

        if (isset($html->getElementsByTagName('table')->children)) {
            $nodes = $html->getElementsByTagName('table')->children;

            $node_one = array_shift($nodes);
            $node_two = array_shift($nodes);

            //
            $route_short_name_one = preg_replace('/\s+/', '', $node->children[0]->children[0]->children[0]->attr['alt']);
            $destination_one = array_shift($node->children[2]->nodes[0]->_);
            $url_one = array_shift($node->children[0]->children[0]->{'attr'});

            $route_short_name_two = preg_replace('/\s+/', '', $node->children[0]->children[0]->children[0]->attr['alt']);
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

    /**
     * @param $shortName
     * @param $departureStation
     * @param $arrivalStation
     *
     * @return array
     */
    public static function generateRouteEntry($shortName, $departureStation, $arrivalStation)
    {
        $route_entry = [
            '@id'            => 'routes:'.$shortName,
            '@type'          => 'gtfs:Route',
            'gtfs:longName'  => $departureStation.' - '.$arrivalStation,
            'gtfs:shortName' => $shortName,
            'gtfs:agency'    => '0',
            'gtfs:routeType' => '2', //→ 2 according to GTFS/CSV spec
        ];

        return $route_entry;
    }

    /**
     * @param $trip_id
     * @param $arrival_time
     * @param $departure_time
     * @param $stop_id
     * @param $stop_sequence
     *
     * @return array
     */
    public static function generateStopTimesEntry($trip_id, $arrival_time, $departure_time, $stop_id, $stop_sequence)
    {
        $stoptimes_entry = [
            'gtfs:trip'          => $trip_id,
            'gtfs:arrivalTime'   => $arrival_time,
            'gtfs:departureTime' => $departure_time,
            'gtfs:stop'          => $stop_id,
            'gtfs:stopSequence'  => $stop_sequence,
        ];

        return $stoptimes_entry;
    }

    /**
     * Scrapes a route of the Belgian Rail Website.
     *
     * @param $date
     * @param $shortName
     * @param $language
     *
     * @return mixed
     */
    public static function getServerData($date, $shortName, $language)
    {
        $request_options = [
            'timeout'   => '30',
            'useragent' => 'GTFS by Project iRail',
        ];

        $scrapeURL = 'http://www.belgianrail.be/jp/sncb-nmbs-routeplanner/trainsearch.exe/'.$language.'ld=std&seqnr=1&ident=at.02043113.1429435556&';

        $post_data = 'trainname='.$shortName.'&start=Zoeken&selectDate=oneday&date='.$date.'&realtimeMode=Show';

        $html = true;
        while (is_bool($html)) {
            echo 'HTTP POST - '.$scrapeURL.' - '.$post_data."\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scrapeURL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $request_options['timeout']);
            curl_setopt($ch, CURLOPT_USERAGENT, $request_options['useragent']);
            $result = curl_exec($ch);
            curl_close($ch);
            $html = str_get_html($result);
        }

        return $result;
    }

    /**
     * @param $scrapeURL
     *
     * @return mixed
     */
    public static function getServerDataByUrl($scrapeURL)
    {
        $request_options = [
            'timeout'   => '30',
            'useragent' => 'GTFS by Project iRail',
        ];

        $html = true;
        while (is_bool($html)) {
            echo 'HTTP GET - '.$scrapeURL."\n";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scrapeURL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $request_options['timeout']);
            curl_setopt($ch, CURLOPT_USERAGENT, $request_options['useragent']);
            $result = curl_exec($ch);
            curl_close($ch);

            $html = str_get_html($result);
        }

        return $result;
    }
}
