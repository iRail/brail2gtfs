# NMBS/SNCB to GTFS scraper

Scrapes the Belgian railways and generates a GTFS file for the current year.

If you're unsure what GTFS is, check the explanation at http://gtfs.org.

## Install

We use the PHP package manager [composer](http://getcomposer.org). Make sure it's installed and then run from this directory:

```bash
composer install
```

## Generating the GTFS file

There are a couple of scripts in the scripts folder. Run them in order. The scraped results will be in the dist folder.

```bash
php scripts/*.php
```

Afterwards, go to the dist folder, and create a zip archive:
```bash
cd dist/
zip brail-0.1.zip *
# remove them for the next run
rm *.txt
```

Your NMBS/SNCB GTFS file is now ready for publishing!

