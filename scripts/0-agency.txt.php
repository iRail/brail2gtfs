<?php

$csv = 'agency_id,agency_name,agency_url,agency_timezone
0,NMBS/SNCB,http://www.belgianrail.be/,Europe/Brussels';

if (file_put_contents('dist/agency.txt', $csv)) {
    echo "successfully wrote to dist/agency.txt\n";
} else {
    echo "could not write dist/agency.txt\n";
}
