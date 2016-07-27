<?php

$TEST = true; // set this to false to scrape full 4 month period. True means only scraping 1 day.

//We're going to start scraping
$startDate = mktime(0, 0, 0, date('n'), 1, date('Y'));
$endDate = strtotime('+4 months', $startDate); //end date 4 months in the future
$startDate = strtotime('-1 day', $startDate); //Take the last day of the previous month to start scraping

if ($TEST) {
    $endDate = strtotime('+1 day', $startDate);
}

/*
 * Possible traintypes: IC, ICE, L, P, TGV, THA, TRN, EXT, S*
 * Possible languages: Dutch, English, French and German (currently only Dutch has been tested)
 * Always leave one language uncommented
 */
return [
    'start_date'   => date('d-m-Y', $startDate),
    'end_date'     => date('d-m-Y', $endDate),
    'feed_version' => '2.0',
    'shortNames'   => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10', 'S20', 'S81', 'IC', 'ICE', 'L', 'P', 'TGV', 'THA', 'TRN', 'EXT'],
    'language'     => 'nl', // Dutch
    // 'language' => 'en' // English
    // 'language' => 'fr' // French
    // 'language' => 'de' // German
];
