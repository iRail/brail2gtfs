#!/bin/bash
php scripts/0-agency.txt.php
php scripts/1-stops.txt.php
php scripts/2-calendar_dates.txt.php
php scripts/3-schedule-files.php
php scripts/4-feed_info.txt.php
php scripts/5-calendar.txt.php
cd dist
zip nmbs-`date +%Y`-`date +%m`.zip *.txt
