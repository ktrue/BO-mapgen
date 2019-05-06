<?php
# gen-BO-maps.php
#
# This is the main control/settings for the Blitzortung map generator
# It should be run via a cron job at 5 minute intervals.
#
#--------------------------------------------------------------------------------
# settings to adjust
#
# error_reporting(E_ALL);
$region= "3";             # Blitzortung REGION number
$username= "username";  # YOUR Blitzortung USERID for the blitzortung.org website
$password= "password";  # YOUR Plitzortung PASSWORD for the blitzortung.org website
$ourTZ = "America/New_York";

$BOcacheDir = "cache/";   # relative FILE path to CACHE directory with trailing '/'
$URLpath = '/BOmap/';     # absolute URL path on website to main directory with leading/trailing '/'.

# for the GRLevel3 placefile, specify the full URL of the icons file for GRLevel3 use
$GR3icons = 'http://saratoga-weather.org/USA-blitzortung/lightningicons.png';

$doLog = true;    # =true, generate the log (recommended), =false suppress log
$doPrint = true;  # =true, also print log to output (not required, but helpful for debug)
$doMapArea = true; #=true, gen <map><area> files for clickable detailed stats.

$numimages = 12; // number of images in animated GIFs

$MapList = array(
  # generate multiple maps from one 'pull' of strikes from blitzortung.org
  # NOTE: make sure base-map and generated-map-name ARE NOT THE SAME
  #   Only generate a placefile once for NorthAmerica -- it's all that GRLevel3 handles
  # base-map|generated-map-name|north,west,south,east|legend-loc|GR3placefile|thumbnail-width|
   'NorthAmerica.png|BONorthAmerica.png|62.0,-145.0,10.0,-50.0|bottom,left|placefile.txt|320|',
  
  #'USA.png|BOUSA.png|52.0,-127.0,16.0,-65.0|bottom,right|placefile.txt|120|',
  #'SWN.png|BOSWN.png|43.0,-125.0,30.0,-108.0|bottom,left||120|',
  #'Ontario.png|BOOntario.png|58.0,-98.0,40.0,-72.0|top,right||',
  #'SouthernOntario.png|BOSouthernOntario.png|50.0,-98.0,40.0,-72.0|top,right||',
  #'EasternCanada.png|BOEasternCanada.png|62.0,-100.0,35.0,-50.0|top,right||',
  #'WesternCanada.png|BOWesternCanada.png|62.0,-150.0,35.0,-90.0|bottom,left||',
);

$Overlays = array( # for overlays-by-base-map
  #
  # OPTIONAL: plot names/text of city/Airport/text over a map by lat,long with a 7px marker
  #
  # base-map|text-to-plot|lat,long|offset-X,offset-Y|
  #  note: offset-X,offset-Y are optional. defaults to |5,-10| pixels
  #  offset-X positive=Right, negative=Left; offset-Y positive=Down, negative=Up
  #
  #'NorthAmerica.png|Atlanta|33.75,-84.38||',
  #'NorthAmerica.png|New Orleans|29.85,-90.08|-5,3|',
  'SWN.png|Sacramento|38.555556,-121.468889|5,3|',
  'SWN.png|Carson City|39.160833,-119.753889|7,-5|',
  'SWN.png|Phoenix|33.45,-112.066667||',
  'SWN.png|Salt Lake City|40.75,-111.883333||',
);

# end of required settings
#--------------------------------------------------------------------------------
# these settings you likely won't have to adjust
#
$time_interval= 7200;  # 2 hours in seconds=7200 -- leave this one as-is

$local_strikes_file= $BOcacheDir.'strikes.txt';     # lightning data (last 2 hrs)
$tmp_strikes_file= $BOcacheDir.'strikes_tmp.txt';   # temp file for lightning data
$local_stations_file = $BOcacheDir.'stations.txt';  # stations.json from Blitzortung
$log_filename = $BOcacheDir.'gen-BO-maps-log.txt';  # detailed log stored here
#
# Start of code .. changes not needed below ... (unless you want to tinker...)
$doDebug = (isset($_REQUEST['debug']))?true:false;
global $Status, $doLog, $doPrint, $doMapArea, $MapList;
$Status = '';
require("BOmapgen-inc.php");
?>