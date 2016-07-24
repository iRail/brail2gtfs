<?php

/**
 * This script generates an empty calendar.txt file.
 *
 * @author Brecht Van de Vyvere <brecht@iRail.be>
 * @author Pieter Colpaert <pieter@iRail.be>
 * @license MIT
 */
$file_calendar = 'dist/calendar.txt';

function appendCSV($dist, $csv)
{
    file_put_contents($dist, trim($csv).PHP_EOL, FILE_APPEND);
}

// header CSV
$header = 'service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date';
appendCSV($file_calendar, $header);
