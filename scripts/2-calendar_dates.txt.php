<?php

/**
 * This script fetches all the routes from the NMBS and generates calendar_dates for the specific dates they roll.
 *
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
$configs = include 'config.php';

$file_calendar_dates = 'dist/calendar_dates.txt';
$file_routes_info_tmp = 'dist/routes_info.tmp.txt';

// ICE and ICT trains are included in search-results IC
$shortNames = $configs['shortNames'];

// Hashmap: route_id => array(service_id, VTString)-pairs
// This way we can generate a new service_id if a route has a different VTString, so another service
$routes_info = [];

// We generate our own service_ids, based on the VTString we get from the NMBS
$service_id = 0; // Counter

date_default_timezone_set('UTC');

/**
 * Scrapes list of routes of the Belgain Rail website.
 *
 * @param $date
 * @param $shortName
 * @return mixed
 */
function getServerData($date, $shortName)
{
    include_once 'includes/simple_html_dom.php';

    $html = true;
    while (is_bool($html)) {
        $request_options = [
              'timeout'   => '30',
              'useragent' => 'iRail.be by Project iRail',
              ];

        $scrapeURL = 'http://www.belgianrail.be/jp/sncb-nmbs-routeplanner/trainsearch.exe/nn?ld=std&AjaxMap=CPTVMap&seqnr=1&';

        $scrapeURL .= 'trainname='.$shortName.'&start=Zoeken&selectDate=oneday&date='.$date.'&realtimeMode=Show';

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

/**
 * Parses short_name of routes out of the HTML (e.g., "P8008").
 *
 * @param $serverData
 * @param $date
 * @param $shortName
 */
function getData($serverData, $date, $shortName)
{
    include_once 'includes/simple_html_dom.php';

    $html = str_get_html($serverData);
    $previous_route_short_name = '';
    $previous_destination = '';

    if (isset($html->getElementsByTagName('table')->children)) {
        $nodes = $html->getElementsByTagName('table')->children;

        $first = true;
        // First node is just the header
        array_shift($nodes);

        while (count($nodes) > 0) {
            $route_info = [];

            if ($first) {
                $node = array_shift($nodes);
                $first = false;
                $route_short_name = preg_replace('/\s+/', '', $node->children[0]->children[0]->children[0]->attr['alt']);
                $destination = array_shift($node->children[2]->nodes[0]->_);
                $VTString = array_shift($node->children[4]->nodes[0]->_);
                $url = array_shift($node->children[0]->children[0]->{'attr'});
            } else {
                // Because the value is already array_shifted
                $route_short_name = $next_route_short_name;
                $destination = $next_destination;
                $VTString = $next_VTString;
                $url = $next_url;
            }

            // Next node. Needed for splitted trains
            if (count($nodes) > 0) {
                $next_node = array_shift($nodes);
                $next_route_short_name = preg_replace('/\s+/', '', $next_node->children[0]->children[0]->children[0]->attr['alt']);
                $next_destination = array_shift($next_node->children[2]->nodes[0]->{'_'});
                $next_VTString = array_shift($next_node->children[4]->nodes[0]->{'_'});
                $next_url = array_shift($next_node->children[0]->children[0]->{'attr'});
            } else {
                // Last one
                // make next from the previous to keep our code
                $next_route_short_name = $previous_route_short_name;
                $next_destination = $previous_destination;
            }

            $drives = true;
               // ICE trains are parsed seperately from IC
               if ($shortName == 'IC' && substr($route_short_name, 0, 3) == 'ICE') {
                   // Don't add
               } else {
                   // Filter out busses and others
                if (substr($route_short_name, 0, strlen($shortName)) == $shortName && substr($route_short_name, 0, 3) != 'Bus') {
                    // Route splits: two different destinations
                    if ($route_short_name == $next_route_short_name && $destination != $next_destination) {
                        // route is split in two routes: we'll check both
                        // First check if this route is really driving this day (bug in NMBS website)
                        $html = drives($url);
                        if ($html) {
                            // Check if this train splits by searching for other route_id
                            // If not found, this is the main train
                            $split_route_short_name = parseSplittedRoute($html); // New route_short_name of the splitted route
                            if ($split_route_short_name != null) {
                                // E.g. IC 1528 -> IC 1628 to Blankenberge
                                checkServiceId($split_route_short_name, $date, $VTString);
                            } else {
                                // Check second route
                                $html_next = drives($next_url);
                                if ($html_next) {
                                    $split_route_short_name = parseSplittedRoute($html_next); // New route_short_name of the splitted route
                                    if ($split_route_short_name != null) {
                                        // E.g. IC 1528 -> IC 1628 to Blankenberge
                                        checkServiceId($split_route_short_name, $date, $VTString);
                                    } else {
                                        $drives = false;
                                    }
                                }
                            }
                        } else {
                            // Check second url
                            $html_next = drives($next_url);
                            if ($html_next) {
                                $split_route_short_name = parseSplittedRoute($html_next); // New route_short_name of the splitted route
                                if ($split_route_short_name != null) {
                                    // E.g. IC 1528 -> IC 1628 to Blankenberge
                                    checkServiceId($split_route_short_name, $date, $VTString);
                                } else {
                                    checkServiceId($route_short_name, $date, $VTString);
                                }
                            } else {
                                $drives = false;
                            }
                        }
                    }

                    if ($drives && $route_short_name != $previous_route_short_name) {
                        checkServiceId($route_short_name, $date, $VTString);
                    }

                    $previous_route_short_name = $route_short_name;
                    $previous_destination = $destination;
                }
               }
        }
    }
}

/**
 * Scrapes one route.
 *
 * @param $url
 * @return bool
 */
function drives($url)
{
    $html = true;
    while (is_bool($html)) {
        $request_options = [
              'timeout'   => '30',
              'useragent' => 'iRail.be by Project iRail',
              ];
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

    if (is_object($test)) {
        return $html;
    } else {
        return false;
    }
}

/**
 * @param $url
 * @return mixed|void
 */
function parseSplittedRoute($html)
{
    $splitRouteId = getSplitTrainRouteId($html);

    return $splitRouteId;
}

/**
 * @param $html
 * @return mixed|void
 */
function getSplitTrainRouteId($html)
{
    $nodes = $html->getElementById('tq_trainroute_content_table_alteAnsicht')->getElementByTagName('table')->children;

    // First node is header, so skip
    // Second node contains name of the original route_id
    $j = 0;
    for ($i = 2; $i < count($nodes); $i++) {
        $node = $nodes[$i];
        if (! count($node->attr)) {
            continue;
        } // row with no class-attribute contain no data

        // Check Train-column if contains name of new route_id
        $splitRouteId = preg_replace('/\s+/', '', array_shift($node->children[4]->nodes[0]->{'_'}));
        if ($splitRouteId == '&nbsp;') {
            continue; // not found
        } else {
            return $splitRouteId;
        }
    }

     // No name for the splitted train
}

/**
 * @param $route_short_name
 * @param $date
 * @param $VTString
 */
function checkServiceId($route_short_name, $date, $VTString)
{
    global $routes_info; // our hashmap
    global $service_id; // our counter

    if (isset($routes_info[$route_short_name])) {
        $service_vtstring_array = $routes_info[$route_short_name]; // array of (service_id, VTString)-pairs
    } else {
        $service_vtstring_array = [];
    }

    $toAddServiceId = '';

    // If VTString isn't already added, make new service_id and add to hashmap
    if (! isVTStringAlreadyAdded($service_vtstring_array, $VTString)) {
        $service_id++;
        $toAddServiceId = $service_id;
        $new_service_vtstring_pair = [$service_id, $VTString];

        if (! isset($routes_info[$route_short_name])) {
            $routes_info[$route_short_name] = [];
        }
        array_push($routes_info[$route_short_name], $new_service_vtstring_pair);
    } else {
        // Use service_id of existing service_id/VTString-pair
        $toAddServiceId = getServiceId($route_short_name, $VTString);
    }

    // Add to calendar_dates.txt
    addCalendarDate($toAddServiceId, $date, 1); // Exception_type: 1

    // Add to route_info.tmp.txt
    addRouteInfo($toAddServiceId, $date, $route_short_name);
}

/**
 * @param $service_vtstring_array
 * @param $VTString
 * @return bool
 */
function isVTStringAlreadyAdded($service_vtstring_array, $VTString)
{
    $alreadyAdded = false;

    foreach ($service_vtstring_array as $pair) {
        $string = $pair[1];
        if ($VTString == $string) {
            $alreadyAdded = true;
        }
    }

    return $alreadyAdded;
}

/**
 * @param $route_short_name
 * @param $VTString
 */
function getServiceId($route_short_name, $VTString)
{
    global $routes_info; // our hashmap
    $service_vtstring_array = $routes_info[$route_short_name];

    foreach ($service_vtstring_array as $pair) {
        $service_id = $pair[0];
        $string = $pair[1];

        if ($VTString == $string) {
            return $service_id;
        }
    }

     // Something went wrong
}

function addCalendarDate($service_id, $date, $exception_type)
{
    global $file_calendar_dates;

    $csv = '';
    $csv .= $service_id.',';
    $csv .= $date.',';
    $csv .= $exception_type; // We only add days when trains drive

    appendCSV($file_calendar_dates, $csv);
}

/**
 * @param $service_id
 * @param $date
 * @param $route_short_name
 */
function addRouteInfo($service_id, $date, $route_short_name)
{
    global $file_routes_info_tmp;

    $csv = '';
    $csv .= $route_short_name.',';
    $csv .= $service_id.',';
    $csv .= $date;

    appendCSV($file_routes_info_tmp, $csv);
}

/**
 * @param $dist
 * @param $csv
 */
function appendCSV($dist, $csv)
{
    file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = 'service_id,date,exception_type';
appendCSV($file_calendar_dates, $header);

$header = 'route_short_name,service_id,date';
appendCSV($file_routes_info_tmp, $header);

// Start date
$start_date = $configs['start_date'];
// End date → See https://github.com/iRail/brail2gtfs/issues/8
$end_date = $configs['end_date'];

// content CSV
// loop all days between start_date and end_date
for ($date = strtotime($start_date); $date < strtotime($end_date); $date = strtotime('+1 day', $date)) {
    foreach ($shortNames as $shortName) {
        $dateNMBS = date('d-m-Y', $date);
        $serverData = getServerData($dateNMBS, $shortName);

        $dateGTFS = date('Ymd', $date);
        getData($serverData, $dateGTFS, $shortName);
    }
}

// Delete duplicates
echo "Calendar_dates.txt and routes_info.tmp.txt are ready!\n";
echo "Removing duplicates from calendar_dates.txt...\n";
//create header in the file
echo exec('head -n1 dist/calendar_dates.txt > dist/calendar_dates_unique.txt');
//append the rest of the file, but only the unique lines
echo exec('tail -n+2 dist/calendar_dates.txt | sort -u >> dist/calendar_dates_unique.txt');
echo exec('mv dist/calendar_dates_unique.txt dist/calendar_dates.txt');
echo "Done.\n";
