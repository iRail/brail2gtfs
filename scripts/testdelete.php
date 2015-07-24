<?php

$file_temp = 'dist/calendar_dates_temp.txt';

function makeCorrectCalendarDates($serviceId_date_pairs) {
	global $file_temp;

	if(($handleRead = fopen('dist/test.txt', 'r')) !== false && ($handleWrite = fopen($file_temp, 'w')) !== false)
	{
		// Header new calendar_dates.txt file
		$header = "service_id,date,exception_type";
		appendCSV($file_temp, $header);

	    // get the first row, which contains the column-titles (if necessary)
	    $header = fgetcsv($handleRead);

	    // loop through the file line-by-line
	    while(($line = fgetcsv($handleRead)) !== false)
	    {
    		// Not all pairs have been found
	    	if (count($serviceId_date_pairs) > 0) {
				$service_id_ = $line[0]; // $line is an array of the csv elements
				$date_ = $line[1];
				
				foreach ($serviceId_date_pairs as $service_id => $date) {
					if ($service_id == $service_id_ && $date == $date_) {
						// Delete from pairs
						unset($serviceId_date_pairs[$service_id]);
					} else {
						// Write to new temporary CSV-file
						fputcsv($handleWrite, $line);
					}
				}
			// Just write the rest
			} else {
				fputcsv($handleWrite, $line);
			}

	        // I don't know if this is really necessary, but it couldn't harm;
	        // see also: http://php.net/manual/en/features.gc.php
	        unset($line);
	    }
	    fclose($handleRead);
	    fclose($handleWrite);
	}
}
function appendCSV($dist, $csv) {
	file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}
$serviceId_date_pairs = array(
	'6' => '20150101'
	);

makeCorrectCalendarDates($serviceId_date_pairs);