<?php

$dist = "dist/routes.tmp.txt";

// ICE and ICT trains are included in search-results IC
$shortNames = array("IC", "L", "P", "TGV", "THA");

// Scrapes the Belgian Rail website
function getServerData($date, $shortName){
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
function getData($serverData, $shortName){
	include_once("includes/simple_html_dom.php");

	$routes = array();
    $html = str_get_html($serverData);

    if(isset($html->getElementsByTagName('table')->children)) {
	    $nodes = $html->getElementsByTagName('table')->children;

	    // First node is header, so skip
	    for($i=1; $i<count($nodes); $i++){
	        $node = $nodes[$i];

	        $route_short_name = preg_replace('/\s+/', '',$node->children[0]->children[0]->children[0]->attr{"alt"});

	        // Filter out busses and others
	        if(substr($route_short_name,0,strlen($shortName)) == $shortName)
	        	array_push($routes, $route_short_name);
	    }
    }          

    return $routes;
}

function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = "route_short_name,date";
appendCSV($dist, $header);

// Start date
$start_date = '2015-01-01';
// End date → See https://github.com/iRail/brail2gtfs/issues/8
$end_date = '2015-12-14';

// content CSV
// loop all days between start_date and end_date
for ($date = strtotime($start_date); $date < strtotime($end_date); $date = strtotime("+1 day", $date)) {
	foreach ($shortNames as $shortName) {
		$serverData = getServerData(date('d%2fm%2fY', $date), $shortName);

		$routes = getData($serverData, $shortName);

		$csv = "";
		for($i=0; $i<count($routes); $i++){
			$route_short_name = $routes[$i];

			$csv .= $route_short_name . ",";
			$csv .= date("Y-m-d", $date);

			appendCSV($dist,$csv);
			$csv = "";
		}
	}
}



