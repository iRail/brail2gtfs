# NMBS/SNCB to GTFS scraper
![MIT](https://img.shields.io/badge/license-MIT-brightgreen.svg) [![StyleCI](https://styleci.io/repos/35721042/shield)](https://styleci.io/repos/35721042) [![Join the chat at https://gitter.im/iRail/iRail](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/iRail/iRail?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Scrapes the Belgian railways and generates GTFS files for the current year.

If you're unsure what GTFS is, check the explanation at http://gtfs.org.

Following traintypes are included:
IC, ICE, L, P, S, TGV, THALYS, TRN and EXT

You can download a copy at: http://gtfs.irail.be/

## Install

We use the PHP package manager [composer](http://getcomposer.org). Make sure it's installed and then run `composer install` from this directory. It's safe to run `composer update` each time you generate a new GTFS dump: the stations list is fetched over composer.

We also have a dependency for nodejs and npm. You will need this to generate the stations file.

## Custom configuration

You can configure the start_date, end_date, feed_version, language and train_types of the GTFS-files by changing config.php in your favorite editor.

__By default, the $TEST variable is set to `true`, which means that you are only going to scrape 1 day. Set this to false to scrape 3 months of data__

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
