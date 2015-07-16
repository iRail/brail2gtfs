<?php
/**
 * This script fetches all the routes from the NMBS and generates calendar_dates for the specific dates they roll
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */

$file_calendar_dates = "dist/calendar_dates.txt";
$file_splitted_routes = "dist/routes_splitted.txt";

// ICE and ICT trains are included in search-results IC
$shortNames = array("IC", "L", "P", "TGV", "THA");

// Hashmap: route_id => array(service_id, VTString)-pairs
// This way we can generate a new service_id if a route has a different VTString, so another service
$routes_info = array();

// We generate our own service_ids, based on the VTString we get from the NMBS
$service_id = 0; // Counter

date_default_timezone_set('UTC');

// Scrapes the Belgian Rail website
function getServerData($date, $shortName) {
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

// Parses short_names of routes out of the HTML (e.g., "P8008")
function getData($serverData, $date, $shortName) {
	include_once ("includes/simple_html_dom.php");

	// Trains that split have twice route_short_name in the list
	// The second route_short_name needs to be investigated later on
	$previous_route_short_name = "";

    $html = str_get_html($serverData);

    if (isset($html->getElementsByTagName('table')->children)) {
	    $nodes = $html->getElementsByTagName('table')->children;

	    // First node is header, so skip
	    for ($i=1; $i<count($nodes); $i++){
	        $node = $nodes[$i];
	        $route_info = array();

	        $route_short_name = preg_replace('/\s+/', '',$node->children[0]->children[0]->children[0]->attr{"alt"});
			$VTString = array_shift($node->children[4]->nodes[0]->{"_"});

	        // Filter out busses and others
	        if (substr($route_short_name,0,strlen($shortName)) == $shortName) {
	        	// Route splits
	        	if ($route_short_name == $previous_route_short_name) {
	        		// route is split in two routes
	        		// The route_short_name of the splitted part can't be found in the list
	        		// Needs to be parsed from the link
	        		// $url = preg_replace(pattern, replacement, subject)
					// $csv = "";
	        		// $csv .= $route_short_name . ",";
	        		// $csv .= $url;
	        		// appendCSV($file_routes_tmp,$csv);
					// $csv = "";
	        	} else {
	        		checkServiceId($route_short_name, $date, $VTString);
	        		
	        		$previous_route_short_name = $route_short_name;
	        	}
	        }
	    }
    }          
}

function checkServiceId($route_short_name, $date, $VTString) {
	global $routes_info; // our hashmap
	global $service_id; // our counter

	if (isset($routes_info[$route_short_name])) {
		$service_vtstring_array = $routes_info[$route_short_name]; // array of (service_id, VTString)-pairs
	} else {
		$service_vtstring_array = array();
	}

	$toAddServiceId = "";

	// If VTString isn't already added, make new service_id and add to hashmap
	if (!isVTStringAlreadyAdded($service_vtstring_array, $VTString)) {
		$service_id++;
		$toAddServiceId = $service_id;
		$new_service_vtstring_pair = array($service_id, $VTString);

		if (!isset($routes_info[$route_short_name])) {
			$routes_info[$route_short_name] = array();
		}
		array_push($routes_info[$route_short_name], $new_service_vtstring_pair);
	} else {
		// Use service_id of existing service_id/VTString-pair
		$toAddServiceId = getServiceId($route_short_name, $VTString);
	}

	// Add to calendar_dates.txt
	addCalendarDate($toAddServiceId, $date, $route_short_name);
}

function isVTStringAlreadyAdded($service_vtstring_array, $VTString) {
	$alreadyAdded = false;

	foreach ($service_vtstring_array as $pair) {
		$string = $pair[1];
		if ($VTString == $string) {
			$alreadyAdded = true;
		}
	}

	return $alreadyAdded;
}

function getServiceId($route_short_name, $VTString) {
	global $routes_info; // our hashmap
	$service_vtstring_array = $routes_info[$route_short_name];

	foreach ($service_vtstring_array as $pair) {
		$service_id = $pair[0];
		$string = $pair[1];

		if ($VTString == $string) {
			return $service_id;
		}
	}

	return NULL; // Something went wrong
}

function addCalendarDate($service_id, $date, $route_short_name) {
	global $file_calendar_dates;

	$csv = "";
	$csv .= $service_id . ",";
	$csv .= $date . ",";
	$csv .= $route_short_name . ","; // just for testing purposes
	$csv .= 1; // We only add days when trains drive

	appendCSV($file_calendar_dates,$csv);
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = "service_id,date,exception_type";
appendCSV($file_calendar_dates, $header);

// Start date
$start_date = '01-01-2015';
// End date → See https://github.com/iRail/brail2gtfs/issues/8
$end_date = '14-12-2015';

// content CSV
// loop all days between start_date and end_date
for ($date = strtotime($start_date); $date < strtotime($end_date); $date = strtotime("+1 day", $date)) {
	foreach ($shortNames as $shortName) {
		$dateNMBS = date('d-m-Y', $date);
		$serverData = getServerData($dateNMBS, $shortName);

		$dateGTFS = date('Ymd', $date);
		getData($serverData, $dateGTFS, $shortName);
	}
}



