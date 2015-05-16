<?php
// Generates the feed_info metadata
// TODO: add feed_start_date, feed_end_date (calculate this) and feed_version (get from composer.json

$csv = "feed_publisher_name,feed_publisher_url,feed_lang
iRail,http://hello.irail.be/,en";

if (file_put_contents("dist/feed_info.txt", $csv)) {
    echo "successfully wrote to dist/feed_info.txt\n";
} else {
    echo "could not write dist/feed_info.txt\n";
}


