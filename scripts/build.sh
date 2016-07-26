#!/bin/bash
php 0-agency.txt.php
php 1-stops.txt.php
php 2-calendar_dates.txt.php
php 3-schedule-files.php
php 4-feed_info.txt.php
php 5-calendar.txt.php
cd dist
zip nmbs-`date +%Y`-`date +%m`.zip *.txt
