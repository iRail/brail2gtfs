<?php

//We're going to start scraping the first three months starting the 15th of the month this file was created on
$startDate = mktime(0, 0, 0, date("n"), 15, date("Y")); //15th of the current month
$endDate = strtotime("+3 months", strtotime($startDate));

/*
 * Possible traintypes: IC, ICE, L, P, TGV, THA, TRN, EXT, S*
 * Possible languages: Dutch, English, French and German (currently only Dutch has been tested)
 * Always leave one language uncommented
 */
return [
    'start_date'   => date("d-m-Y", $startDate),
    'end_date'     => date("d-m-Y", $endDate),
    'feed_version' => '1.0',
    'shortNames'   => ['S1','S2','S3','S4','S5','S6','S7','S8','S9','S10','S20','S81','IC', 'ICE', 'L', 'P', 'TGV', 'THA', 'TRN', 'EXT'],
    'language'     => 'nl', // Dutch
    // 'language' => 'en' // English
    // 'language' => 'fr' // French
    // 'language' => 'de' // German
];
