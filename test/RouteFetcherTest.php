<?php

use iRail\brail2gtfs\RouteFetcher;

class RouteFetcherTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		$this->routeFetcher = new RouteFetcher;
	}

	public function inputShortNameAndDateAndTripIdAndServiceIdAndLanguageAndRouteResultAndStopTimesResult()
	{
		$routeResult = [
			"@id" => "routes:IC106",
			"@type" => "gtfs:Route",
			"gtfs:longName" => "Liers - Luxembourg (l)",
			"gtfs:shortName" => "IC106",
			"gtfs:agency" => "0",
			"gtfs:routeType" => "2"
		];

		$stopTimesResult = [
		[
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "05:36:00",
		    "gtfs:departureTime" => "05:36:00",
		    "gtfs:stop" => "stops:008841673:0",
		    "gtfs:stopSequence" => 1
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "05:39:00",
		    "gtfs:departureTime" => "05:39:00",
		    "gtfs:stop" => "stops:008841665:0",
		    "gtfs:stopSequence" => 2
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "05:45:00",
		    "gtfs:departureTime" => "05:46:00",
		    "gtfs:stop" => "stops:008841608:0",
		    "gtfs:stopSequence" => 3
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "05:51:00",
		    "gtfs:departureTime" => "05:52:00",
		    "gtfs:stop" => "stops:008841525:0",
		    "gtfs:stopSequence" => 4
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "05:54:00",
		    "gtfs:departureTime" => "05:55:00",
		    "gtfs:stop" => "stops:008841558:0",
		    "gtfs:stopSequence" => 5
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "05:59:00",
		    "gtfs:departureTime" => "06:08:00",
		    "gtfs:stop" => "stops:008841004:0",
		    "gtfs:stopSequence" => 6
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "06:12:00",
		    "gtfs:departureTime" => "06:13:00",
		    "gtfs:stop" => "stops:008842002:0",
		    "gtfs:stopSequence" => 7
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "06:26:00",
		    "gtfs:departureTime" => "06:26:00",
		    "gtfs:stop" => "stops:008842689:0",
		    "gtfs:stopSequence" => 8
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "06:29:00",
		    "gtfs:departureTime" => "06:30:00",
		    "gtfs:stop" => "stops:008842705:0",
		    "gtfs:stopSequence" => 9
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "06:38:00",
		    "gtfs:departureTime" => "06:39:00",
		    "gtfs:stop" => "stops:008842754:0",
		    "gtfs:stopSequence" => 10
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "06:57:00",
		    "gtfs:departureTime" => "06:57:00",
		    "gtfs:stop" => "stops:008845229:0",
		    "gtfs:stopSequence" => 11
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:00:00",
		    "gtfs:departureTime" => "07:01:00",
		    "gtfs:stop" => "stops:008845203:0",
		    "gtfs:stopSequence" => 12
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:13:00",
		    "gtfs:departureTime" => "07:13:00",
		    "gtfs:stop" => "stops:008845146:0",
		    "gtfs:stopSequence" => 13
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:23:00",
		    "gtfs:departureTime" => "07:26:00",
		    "gtfs:stop" => "stops:008845005:0",
		    "gtfs:stopSequence" => 14
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:34:00",
		    "gtfs:departureTime" => "07:36:00",
		    "gtfs:stop" => "stops:008200136:0",
		    "gtfs:stopSequence" => 15
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:44:00",
		    "gtfs:departureTime" => "07:45:00",
		    "gtfs:stop" => "stops:008200134:0",
		    "gtfs:stopSequence" => 16
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:49:00",
		    "gtfs:departureTime" => "07:50:00",
		    "gtfs:stop" => "stops:008200133:0",
		    "gtfs:stopSequence" => 17
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:53:00",
		    "gtfs:departureTime" => "07:54:00",
		    "gtfs:stop" => "stops:008200132:0",
		    "gtfs:stopSequence" => 18
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "07:59:00",
		    "gtfs:departureTime" => "08:02:00",
		    "gtfs:stop" => "stops:008200130:0",
		    "gtfs:stopSequence" => 19
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "08:15:00",
		    "gtfs:departureTime" => "08:17:00",
		    "gtfs:stop" => "stops:008200120:0",
		    "gtfs:stopSequence" => 20
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "08:28:00",
		    "gtfs:departureTime" => "08:28:00",
		    "gtfs:stop" => "stops:008200110:0",
		    "gtfs:stopSequence" => 21
	    ],
	    [
		    "gtfs:trip" => "IC10611",
		    "gtfs:arrivalTime" => "08:42:00",
		    "gtfs:departureTime" => "08:42:00",
		    "gtfs:stop" => "stops:008200100:0",
		    "gtfs:stopSequence" => 22
	    ]
		];

		return [
			["IC106", "20150101", "IC10611", "1", "nl", $routeResult, $stopTimesResult]
		];
	}

	/**
	* @dataProvider inputShortNameAndDateAndTripIdAndServiceIdAndLanguageAndRouteResultAndStopTimesResult
	*/
	public function testCorrectRouteAndStopTimes($shortName, $date, $trip_id, $service_id, $language, $routeResult, $stopTimesResult)
	{
		$this->assertEquals($routeResult, $this->routeFetcher->fetchRouteAndStopTimes($shortName, $date, $trip_id, $service_id, $language)[0]);
		$this->assertEquals($stopTimesResult, $this->routeFetcher->fetchRouteAndStopTimes($shortName, $date, $trip_id, $service_id, $language)[1]);
	}
}