<?php

ini_set('memory_limit', '-1');

$filename = 'dist/calendar_dates.txt';
$file = fopen($filename, 'r');
$read = fread($file, filesize($filename));

$split = array_unique(explode("\n", $read));

fclose($file);

$filename2 = 'dist/other.txt';
$file2 = fopen($filename2, 'a');

foreach ($split as $key => $value) {
    if ($value != '') {
        fwrite($file2, $value."\n");
    }
}

fclose($file2);
