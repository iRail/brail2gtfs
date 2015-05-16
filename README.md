# brail2gtfs
Belgian railways GTFS scraper in PHP.

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

