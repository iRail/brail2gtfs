<?php

// Generates the feed_info metadata

// set the default timezone to use. Available since PHP 5.1
date_default_timezone_set('UTC');

$configs = include 'config.php';

$feed_lang = $configs['language'];
$feed_start_date = date_create_from_format('d-m-Y', $configs['start_date'])->format('Ymd');
$feed_end_date = date_create_from_format('d-m-Y', $configs['end_date'])->format('Ymd');
$feed_version = $configs['feed_version'];

$csv = "feed_publisher_name,feed_publisher_url,feed_lang,feed_start_date,feed_end_date,feed_version
iRail,http://hello.irail.be/,$feed_lang,$feed_start_date,$feed_end_date,$feed_version";

if (file_put_contents('dist/feed_info.txt', $csv)) {
    echo "successfully wrote to dist/feed_info.txt\n";
} else {
    echo "could not write dist/feed_info.txt\n";
}
