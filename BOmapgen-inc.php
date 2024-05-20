<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
#--------------------------------------------------------------------------------
# BOmapgen - freshen the stroke/stations files, generate the map
#
# NOTE: this file is the 'guts' to be included in the parameter file (at the end
#   and serves to separate the working code from the individual map parameter files
#  This file is based on http://www.blitzortung.org/Webpages/Protected/main.php.txt from
#  9-Apr-2015 and uses the JSON versions of the strikes and stations files.
#
#  Mods by:  K. True - webmaster@saratoga-weather.org
#  Additions to the base code include:
#    some streamlining of code (repetitive drawing in loops for easier mods)
#    logging capability (to file and optionally to print)
#    optional placement of legend [top|bottom],[left|right]
#    saving of PNG file (for cron usage) and generation of animated GIF
#    generation of <map> <area/> </map> for mouse-over display and links to station data
#    optional generation of GRLevel3 placefile for strike data
#
#  Version 1.00 - 18-Apr-2015 - initial release
#
#  Version 1.01 - 21-Apr-2015
#    changed image count from 10 to 12 default (add $numimages to gen-BO-maps.php)
#    changed graphic to plot offline stations first (and <area> last) for better display
#    changed progress bar on animated gif to use 192,192,192 for better contrast
#    changed <map> ID/Name to be basename of image (e.g. id="BONorthAmerica") to allow multiple maps/page
#  Version 1.02 - 20-May-2015
#    added tracking for strikes file size to logging
#    added optional reset for strikes file if too large
#  Version 1.03 - 22-May-2015
#    changed strikes.txt file from JSON to '|' delimited file for improved speed
#    added autoconvert for old JSON strikes.txt to new format
#  Version 1.04 - 29-Sep-2017
#    added overlay message to maps when data is not available 
#  Version 1.05 - 30-Oct-2018
#    added fetch for MOTD message on blitzortung.org. Save to $BOcacheDir.BOmotd.txt
#    changed for new stations JSON format
#  Version 1.07 - 23-Mar-2022
#    added code to optionally use last_strikes.php query instead of 10-minute JSON files
#  Version 1.08 - 26-Apr-2023
#    added code to produce placefile with UTC timestamps as {filename}UTC.{ext}
#  Version 1.09 - 19-May-2024
#    changed imagefilledpolygon() calls for PHP 8.0+ for Deprecated errata
#--------------------------------------------------------------------------------
$Version = 'BOmapgen - V1.09 - 19-May-2024';
$Credits = 'script by saratoga-weather.org';
$mainURL = 'data.blitzortung.org';
#$mainURL = 'data2.blitzortung.org';
#$mainURL = 'data.lightningmaps.org';
#$mainURL = '217.145.98.148';  // data.blitzortung.org IP
#$mainURL = '213.32.62.243';   // data.lightningmaps.org

$useQuery = false; // =true; to use last_strikes.php query; =false; to use old 10-minute files queries
$BOMainURL = 'https://en.blitzortung.org/live_lightning_maps.php?map=30';
$BOmsgFile = $BOcacheDir.'BOmotd.txt';


if(!isset($MapList) or count($MapList) < 1) {
   exit ("$Version\nError: script cannot run without a \$MapList specification.\n");
}
if(!isset($username) or $username == 'username') {
   exit  ("$Version\nError: missing a valid Blitzortung \$username and \$password.\n");
}
log_msg("$Version - $Credits\n");
include_once("GIFEncoder.class.php");// This needs to be in the same folder as this script
//
// strikes and stations path
//
# old $strikes_dir= 'http://' . $username . ':' . $password . '@'.$mainURL.'/Data_' . $region . '/Protected/Strikes/';
# new https://loginname:password@data.blitzortung.org/Data/Protected/Strikes_1/2013/08/08/10/30.json.gz
$strikes_dir= 'https://' . $username . ':' . $password . '@'.$mainURL.'/Data/Protected/Strikes_' . $region . '/';

# old $stations_file= 'http://' . $username . ':' . $password . '@'.$mainURL.'/Data_/' . $region . '/Protected/stations.json.gz';
# new https://loginname:password@data.blitzortung.org/Data/Protected/stations.txt.gz
$stations_file= 'https://' . $username . ':' . $password . '@'.$mainURL.'/Data/Protected/stations.json.gz';

# V1.07 - add query with last_strikes.php
$strikes_query = 'https://' . $username . ':' . $password . '@'.$mainURL.'/Data/Protected/last_strikes.php?number=999999';

//
// times
//
if (!function_exists('date_default_timezone_set')) {
	
	if (! ini_get('safe_mode') ) {
	   putenv("TZ=$ourTZ");  // set our timezone for 'as of' date on file
	}
  } else {
   date_default_timezone_set($ourTZ);
 }

$end_time= time();
$ourStart = time();

$numimages = isset($numimages)?$numimages:12; // default of 12 images
$maxFilesize = isset($maxFilesize)?$maxFilesize*1048576:200*1048576; //default = 200MB size
log_msg("Run date: ".date('r')."\n");
log_msg("Running on PHP " . phpversion()."\n");
log_msg("Script path:   ".__FILE__."\n");
log_msg("Parms: URLPath='$URLpath' for map generation\n");
log_msg("Parms: using cache directory of '$BOcacheDir'\n");
log_msg("Parms: maxFilesize='" . show_size($maxFilesize) . "' for $local_strikes_file\n");
log_msg("Parms: ourTZ='$ourTZ'\n");
log_msg("Parms: $numimages images to be used.\n");
#
# fetch and save the MOTD if any from the Blitzortung.org website.

$BOhtml = @file_get_contents($BOMainURL);

if(preg_match('!<div id="motd".*>(.*)</div>!Uis',$BOhtml,$matches) and 
   ! preg_match('|^Network for Lightning|i',$matches[1])) {
	$BOmsg = 'Blitzortung.org message: <strong>'.trim(strip_tags($matches[1])).'</strong>';
	log_msg('BO MOTD: \''.$BOmsg."'\n");
	if(preg_match('/Real time lightning map/is',$BOmsg) or 
	   preg_match('/Make a donation for Blitzortung.org/is',$BOmsg)) {
		$BOmsg = '';
		log_msg("BO MOTD: omitted about Forum message.\n");
	}
} else {
	$BOmsg = '';
}

$twrite = file_put_contents($BOmsgFile,$BOmsg);
if($twrite !== false) {
	log_msg("BO MOTD written to $BOmsgFile with $twrite bytes.\n");
} else {
	log_msg("--Unable to write BO MOTD file to $BOmsgFile.\n");
}
#
# 

if($useQuery) {
	log_msg("Parms: using last_strikes.php query method for strikes\n");
	$queryStart = (string)($ourStart - $time_interval - 1); #.'000000000'; // earliest data needed in nanoseconds
	// new: find needed NW, SE coords needed for query
	$qNorth = -90;
	$qWest  = 180;
	$qSouth = 90;
	$qEast  = -180;
	log_msg("\$Maplist used:\n");
	foreach ($MapList as $i => $rec) {
		# base-map|generated-map-name|north,west,south,east|legend-loc|GR3placefile|thumbnail-width|
		list($v1,$v2,$coords,$v5) = explode('|',$rec.'||||||');
		list($tN,$tW,$tS,$tE) = explode(',',$coords.',,,,');
		log_msg("  '$rec'\n");
		#log_msg(" N=$tN, W=$tW,  S=$tS,E=$tE\n");
		if(is_numeric($tN) and $tN >= $qNorth) {$qNorth = $tN;}
		if(is_numeric($tW) and $tW <= $qWest)  {$qWest  = $tW;}
		if(is_numeric($tS) and $tS <= $qSouth) {$qSouth = $tS;}
		if(is_numeric($tE) and $tE >= $qEast)  {$qEast  = $tE;}
	}
	
	log_msg("\$MapList scan has N=$qNorth,W=$qWest and S=$qSouth,E=$qEast for overall map coordinates.\n");
	
	$strikes_query .= "&north=$qNorth&west=$qWest&south=$qSouth&east=$qEast&sig=0&time=$queryStart";
	$sFilename = preg_replace('|//([^@]+)@|','//user:pass-omitted@',$strikes_query);
	
	log_msg("Query='$sFilename'\n");
} # end $useQuery

$fileOversize = (file_exists($local_strikes_file) and filesize($local_strikes_file) > $maxFilesize)?true:false;

if($fileOversize) {
  log_msg("\nResetting strikes file '$local_strikes_file' as too big: ".show_size(filesize($local_strikes_file))." > limit of " .
    show_size($maxFilesize). "\n");
}

//
// renew local data file
//
if(!file_exists($local_strikes_file) or $fileOversize) {
  // initialize with null file
  $fp=fopen($local_strikes_file,'w');
  fclose($fp);
  log_msg("Initalized local strikes file $local_strikes_file\n");
  $run_time = $end_time - $time_interval;
  $l_time = $$end_time - $time_interval -1;
} 

if(!$useQuery) { #----------- process strikes from 10-minute JSON files
	log_msg("Parms: using 10-minute JSON files for strikes.\n");

	if(file_exists($local_strikes_file) and !$fileOversize) {
		$lFileSize = filesize($local_strikes_file);
		log_msg("start processing for region $region data ".date("D, d M Y H:i:s", $ourStart)."\n");
		log_msg("current $local_strikes_file strikes file size is ".show_size($lFileSize)."\n");
		if (filemtime($local_strikes_file) < $end_time-60) {
		touch ($local_strikes_file);
		$first_time = 0;
		$l_time= $end_time-$time_interval;
		$l_file= @fopen($local_strikes_file, 'r');
		$t_file= @fopen($tmp_strikes_file, 'w');
		while ($line= fgets ($l_file)) {
			if(substr($line,0,1) == "{") { # JSON format
			$strike= json_decode($line);
			$strike->time/= 1000000000;
			$l_time = $strike->time;
			$strike_time = $l_time;
			$strike_lat = $strike->lat;
			$strike_lon = $strike->lon;
			} else { # in text format
				list($strike_time,$strike_lat,$strike_lon) = explode('|',trim($line).'|||');
			}
		 
			if(!$first_time) {$first_time = $l_time; }
			if ($strike_time >= $end_time-$time_interval) {
			$nline = "$strike_time|$strike_lat|$strike_lon\n";
			fwrite ($t_file, $nline);
			$l_time= $strike_time;
			}
		}
		fclose ($l_file);
		fclose ($t_file);
		rename ($tmp_strikes_file,$local_strikes_file);
		log_msg("$local_strikes_file filtered for old data\n");
		log_msg("First data: ".date("D, d M Y H:i:s T", $first_time)."\n");
		log_msg("Last data : ".date("D, d M Y H:i:s T", (integer)$l_time)."\n");
		
	}
		if($l_time > $end_time - $time_interval) {
			$run_time= $l_time;
			log_msg("data freshen starting from ".date("D, d M Y H:i:s T", (integer)$run_time)."\n");
		} else {
		$run_time = $end_time - $time_interval;
		$l_time = $end_time - $time_interval;
			log_msg("old data - restart collection from ".date("D, d M Y H:i:s T", $run_time)."\n");
		}
		$l_file= fopen($local_strikes_file, 'a');
		$opts = array(
		'http'=>array(
			'method'=>"GET",
			'protocol_version' => 1.1,
			'timeout' => 8.0,
			'header'=> 
				//  "Hostname: data.blitzortung.org\r\n" . 
					"Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" .
					"Connection: close\r\n" . 
					"User-agent: Mozilla 5.0 (BOmapgen)\r\n" . 
					 "Accept: text/html,text/plain\r\n"
		)
		);
	
		$context = stream_context_create($opts);
	//error_reporting(E_ALL);
		while ($run_time < $end_time+600) {
		$filename= $strikes_dir . gmdate("Y/m/d/H/",intval($run_time)) . intval(fmod($run_time/600,6)) . '0.json';
		$sFilename = preg_replace('|//([^@]+)@|','//user:pass-omitted@',$filename);
		log_msg("fetching new strikes file at $sFilename\n");
		$nLines = 0;
		$nWritten = 0;
		$total_time = 0;
		$T_begin = microtime(true);
			$lines = @file($filename,0,$context);
			$headerarray = @get_headers($filename,1,$context);
		if(is_array($headerarray) and !preg_match('| 200 |',$headerarray[0])) {
			log_msg("Headers returned from $sFilename\n".print_r($headerarray,true)."\n");
		}
		$T_end = microtime(true);
		if(is_array($lines) and count($lines) > 0) {
	//    if($file= fopen($filename, 'r')) {
			$number_calls = 0;
				foreach ($lines as $line) {
	//      while ($line= fgets ($file)) {
			$nLines++;
			$time_start = microtime(true);
			$number_calls++;
					$json= json_decode($line);
					$json->time /= 1000000000;
			$time_stop = microtime(true);
			$total_time += ($time_stop - $time_start);
					if ($json->time >= $l_time) {
				$strike_time = $json->time;
				$strike_lat = $json->lat;
				$strike_lon = $json->lon;
				$nline = "$strike_time|$strike_lat|$strike_lon\n";
						fwrite ($l_file, $nline);
				$nWritten++;
					}
				}
			$time_elapsed = sprintf("%01.3f",round($total_time,3));
			$time_overall = sprintf("%01.3f",round($T_end-$T_begin,3));
			
			log_msg("timed calls took $time_elapsed for $number_calls executions in $time_overall seconds.\n");
			log_msg("$nLines lines read. $nWritten lines written to $local_strikes_file.\n\n");
	//      fclose ($file);
			} else {
			$nLines = 0;
			$nWritten = 0;
			$gzfilename= $strikes_dir . gmdate("Y/m/d/H/",(integer)$run_time) . intval(fmod($run_time/600,6)) . '0.json.gz';
			$sFilename = preg_replace('|//([^@]+)@|','//user:pass-omitted@',$gzfilename);
			log_msg("fetching new GZ strikes file at $sFilename\n");
				$file= @gzopen($gzfilename, 'r');
				$headerarray = @get_headers($gzfilename,1,$context);
			if(is_array($headerarray) and !preg_match('| 200 |',$headerarray[0])) {
				log_msg("Headers returned from $sFilename\n".print_r($headerarray,true)."\n");
			}
			if($file) {
					while ($line= gzgets ($file)) {
				$nLines++;
						$json= json_decode($line);
						$json->time /= 1000000000;
						if ($json->time >= $l_time) {
				$strike_time = $json->time;
				$strike_lat = $json->lat;
				$strike_lon = $json->lon;
				$nline = "$strike_time|$strike_lat|$strike_lon\n";
							fwrite ($l_file, $nline);
				$nWritten++;
						}
					}
			log_msg("$nLines GZ lines read. $nWritten lines written to $local_strikes_file.\n\n");
					gzclose ($file);
				}
			}
			$run_time+= 600;
		}
		fclose ($l_file);
	}
		log_msg("after refresh, $local_strikes_file strikes file size is ".show_size(filesize($local_strikes_file))."\n\n");
	
	} else { # -------------------- use new query method
	$T_begin = microtime(true);
  $rawStrikes = file($strikes_query);
	if($rawStrikes == false) {
		log_msg("--Query failed -- no data returned.  Exiting\n");
		die("Strike data not available.");
	}
  $T_end = microtime(true);
	file_put_contents('./returned.txt',$rawStrikes);
	$l_file= @fopen($local_strikes_file, 'w');
	krsort($rawStrikes); # put in cronological order
	$T_elapsed = sprintf("%01.3f",round($T_end-$T_begin,3));
	log_msg("Query took $T_elapsed seconds\n");
	$first_strike_time = 0;
	$last_strike_time = 0;
	 
	if($rawStrikes !== false and is_array($rawStrikes)) {
	  $nWritten = 0;
		$T_QPStart = microtime(true);
		foreach ($rawStrikes as $i => $line) {
      $json= json_decode($line);
      $json->time /= 1000000000;
			$strike_time = $json->time;
			if($first_strike_time == 0) {$first_strike_time = $strike_time; }
			$last_strike_time = $strike_time;
			$strike_lat = $json->lat;
			$strike_lon = $json->lon;
			$nline = "$strike_time|$strike_lat|$strike_lon\n";
      fwrite ($l_file, $nline);
			$nWritten++;
		}
		$T_QPend = microtime(true);
		$T_QPtotal = sprintf("%01.3f",round($T_QPend-$T_QPStart,3));
		log_msg("Query returned $nWritten strikes .. saved to $local_strikes_file\n");
		$T_firstStrike = date("D, d M Y H:i:s T",$first_strike_time);
		$T_lastStrike =  date("D, d M Y H:i:s T",$last_strike_time);
		$T_strikeCoverage = round($last_strike_time - $first_strike_time,0);
		log_msg("Query processing took $T_QPtotal seconds.\n");
		log_msg("Strikes from $T_firstStrike to $T_lastStrike ($T_strikeCoverage seconds)\n");
	} else {
		log_msg("Query returned no strikes.\n");
	}
  fclose($l_file);
	
} # ----------------------------end new query method

if(filesize($local_strikes_file) < 100) {
	$doNoDataMsg = true;
} else {
	$doNoDataMsg = false;
}

//
// Get stations file
//
$sFilename = preg_replace('|//([^@]+)@|','//user:pass-omitted@',$stations_file);
log_msg("fetching new stations file at $sFilename\n");
$nLines = 0;
$T_begin = microtime(true);
$StationList = @gzfile($stations_file);
$T_end = microtime(true);
$headerarray = @get_headers($stations_file,1,$context);
if(is_array($headerarray) and !preg_match('| 200 |',$headerarray[0])) {
  log_msg("Headers returned from $sFilename\n".print_r($headerarray,true)."\n");
}
if(!is_array($StationList)) {
	$StationList = array();
	log_msg("Unable to fetch station list. Null array used instead\n");
}
if(isset($local_stations_file)) { // save off the local_stations_file for later processing
	$fp = fopen($local_stations_file,'w');
	if($fp) {
		$nRecs = 0;
		foreach ($StationList as $rec) {
			fwrite($fp,$rec);
			$nRecs++;
		}
		fclose ($fp);
		log_msg("Wrote $nRecs lines to $local_stations_file.\n");
	} else {
		log_msg("Error: unable to write station data to $local_stations_file.\n");
	}
}
$time_overall = sprintf("%01.3f",round($T_end-$T_begin,3));
log_msg("Stations file fetch took $time_overall seconds for $nRecs records.\n");

	$StationsJSON = array();
	$StationsJSON = json_decode(implode($StationList),true);
  if(function_exists('json_last_error')) { // report status, php >= 5.3.0 only
	switch (json_last_error()) {
	  case JSON_ERROR_NONE:           $error = '- No errors';                                                break;
	  case JSON_ERROR_DEPTH:          $error = '- Maximum stack depth exceeded';                             break;
	  case JSON_ERROR_STATE_MISMATCH: $error = '- Underflow or the modes mismatch';                          break;
	  case JSON_ERROR_CTRL_CHAR:      $error = '- Unexpected control character found';                       break;
	  case JSON_ERROR_SYNTAX:         $error = '- Syntax error, malformed JSON';                             break;
	  case JSON_ERROR_UTF8:           $error = '- Malformed UTF-8 characters, possibly incorrectly encoded'; break;
	  default:                        $error = '- Unknown error';                                            break;
	}
  log_msg("Station list JSON decode return $error\n");
 }
 if(is_array($StationsJSON)) {
	log_msg("StationsJSON lists ".@count($StationsJSON)." entries.\n\n");
 }
	
	//log_msg("Dump of StationsJSON\n\n".print_r($StationsJSON,true)."\n\n");
/*
    [808] => Array
        (
            [user] => 696
            [city] => Minden - 808
            [comments] => 300mm x 7.5mm ferrite, E-field 370mm x 2mm
            [country] => United States / Nevada
            [website] => carsonvalleyweather.com
            [latitude] => 39.038548
            [longitude] => -119.7211
            [altitude] => 1471
            [controller_board] => 10.3
            [firmware] => 8.4
            [controller_status] => 30
            [input] => Array
                (
                    [0] => Array
                        (
                            [board] => 12.3
                            [firmware] => 1.6
                            [gain] => 8.2
                            [antenna] => F;300,7;S
                        )

                    [1] => Array
                        (
                            [board] => 12.3
                        )

                    [2] => Array
                        (
                            [board] => 12.3
                        )

                    [3] => Array
                        (
                            [board] => 12.3
                        )

                    [4] => Array
                        (
                            [board] => 12.3
                        )

                    [5] => Array
                        (
                            [board] => 12.3
                        )

                )

            [signals] => 1518
            [last_signal] => 2018-10-30 08:18:18
            [last_signal_nsec] => 1540887499367336558
            [last_strike] => 2018-08-28 13:43:30
            [last_strike_nsec] => 0
            [50_km_60_m] => 0
            [50_km_60_m_a] => 0
            [50_km_60_m_e] => 0
            [500_km_60_m] => 0
            [500_km_60_m_a] => 0
            [500_km_60_m_e] => 0
            [5000_km_60_m] => 304
            [5000_km_60_m_a] => 5793
            [5000_km_60_m_e] => 1.37511
        )

*/

//
// mercator projection
//
function mercator_proj ($lat)
{
  $lat= (float)$lat;
  return(log(tan(pi()/4.0+$lat/2.0)));
}

//
// parse/generate city overlay specs
//
$CityList = array();

if(isset($Overlays) and count($Overlays)>0) {
  $numCity = 0;
  foreach($Overlays as $n => $line) {
	list($map,$text,$latlong,$offset) = explode('|',$line.'||||');
	if(!$latlong) {
		log_msg("--\$Overlay spec '$line' missing lat,long .. ignored\n");
		continue;
	}
	if(!$map) {
		log_msg("--\$Overlay spec '$line' missing map name .. ignored\n");
		continue;
	}
	$CityList[$map][] = "$text\t$latlong\t$offset";
	$numCity++;
  }
  log_msg("Added $numCity city overlay specs.\n");
} else {
	$Overlays = array();
}


# -- map generation loop -- run for each map specified
foreach ($MapList as $mapspec) {
  list($map,$PNGfile,$NWSE,$legendLoc,$GRplacefilename,$thumbW) = explode('|',$mapspec.'|||||');
  list($north,$west,$south,$east) = explode(',',$NWSE.',,,');
  $snap_start = time();

  //
  // copy image
  //
  if(!file_exists($map)) {
	  log_msg("\n---------------------------------------------------------------------------------\n");
	  log_msg("--Error: $map template map file not found.  Skipping generation of $PNGfile.\n");
	  log_msg("---------------------------------------------------------------------------------\n\n");
	  continue; // skipt to the next generation spec.
  }
  if($north > 90.0 or $north < -90.0 or $south < -90.0 or $south > 90.0 or
     $west < -180.0 or $west > 180.0 or $east < -180.0 or $east > 180.0 or
	 $west > $east or $south > $north) {
	  log_msg("\n---------------------------------------------------------------------------------\n");
	  log_msg("--Error: invalid N,W,S,E ($north,$west,$south,$east) spec. Skipping generation of $PNGfile.\n");
	  log_msg("---------------------------------------------------------------------------------\n\n");
	  continue; // skipt to the next generation spec.
  }

  $img= imagecreatefrompng($map);
  $img_width= imagesx($img);
  $img_height= imagesy($img);
  log_msg("Generating image from $map w=$img_width h=$img_height.\n");
  $doThumb = false;
  if(is_numeric($thumbW) and $thumbW > 0 and $thumbW <= $img_width) {
	  $imgThumb_width = $thumbW;
	  $imgThumb_height = round(($thumbW/$img_width)*$img_height,0);
      $imgThumb = imagecreatetruecolor($imgThumb_width,$imgThumb_height);
	  imagecopyresampled($imgThumb,$img,0,0,0,0,$imgThumb_width,$imgThumb_height,$img_width,$img_height);
	  $doThumb = true;
	  log_msg("Generating thumbnail w=$imgThumb_width h=$imgThumb_height.\n");
  }
  //
  // convert d.dddddd coords. to radians for computation
  //
  $north = rad($north);
  $south = rad($south);
  $west = rad($west);
  $east = rad($east);

  //
  // colors defined
  //
  $black       = imagecolorallocate($img,   0,   0,   0);
  $white       = imagecolorallocate($img, 255, 255, 255);
  $yellow      = imagecolorallocate($img, 255, 255,   0);
  $orange      = imagecolorallocate($img, 255, 170,   0);
  $red_light   = imagecolorallocate($img, 255,  85,   0);
  $red         = imagecolorallocate($img, 255,   0,   0);
  $red_dark    = imagecolorallocate($img, 191,   0,   0);
  $city_col    = imagecolorallocate($img, 192, 192, 192);
  $city_bor    = imagecolorallocate($img, 192, 192, 192);
  $active_col  = imagecolorallocate($img,  63, 191,  63);
  $idle_col    = imagecolorallocate($img, 191, 191,  63);
  $fault_col   = imagecolorallocate($img, 255,  0,  0);
  $inactive_col= imagecolorallocate($img, 159, 159, 159);
  $interf_col  = imagecolorallocate($img, 255, 255, 255);
  $bground_col = imagecolorallocate($img,  31, 109, 153);
  $light_grey  = imagecolorallocate($img, 128, 128, 128);

  //
  // draw some cities
  //
  $cities_array = array();
  if(isset($CityList[$map]) and count($CityList[$map])>0) {
	  $cities_array = $CityList[$map];
	  log_msg("Drawing ". count($cities_array) . " cities on $PNGfile.\n");
  }
  foreach ($cities_array as $city_line ) {
	list($city,$latlong,$offset) = explode("\t",$city_line);
	list($latR,$lonR) = explode(',',$latlong);
	list($offX,$offY) = explode(',',$offset.',');
	if(!isset($offY) or !isset($offX) or 
	    ($offY == '' and $offX == '')) {
		$offX = 5;
		$offY = -10;
	}
	$lat=rad($latR);
	$lon=rad($lonR);
	
#	$lat= strtok($city_line,';')*pi()/180.0;
#	$lon= strtok(';')*pi()/180.0;
	if (($lon >= $west)&&($lon <= $east)&&($lat <= $north)&&($lat >= $south)) {
  
	  $x= (int)(($img_width)*($lon-$west)/($east-$west));
	  $y= (int)($img_width*(mercator_proj($north)-mercator_proj($lat))/($east-$west));
  
	  imagefilledellipse ($img, $x, $y, 7, 7, $city_col);
	  imageellipse ($img, $x, $y, 7, 7, $city_bor);
	  imagestring ($img, 1, $x+$offX, $y+$offY, $city, $city_col);
	  log_msg("Drew '$city' on $PNGfile at $latR,$lonR with legend offset $offX,$offY.\n");

	}
  }
  
  //
  // draw strikes
  //
  $strikes= 0;
  log_msg("Drawing strikes for $PNGfile.\n");
  $nLines = 0;
  if($GRplacefilename <> '') {
	$GRfile = fopen($GRplacefilename,'w');
	$GRfileUTCname = str_replace('.txt','UTC.txt',$GRplacefilename);
	$GRfileUTC = fopen($GRfileUTCname,'w');
	
	$GRInitRecs = 
"; Bliztortung USA Placefile for GRLevel3
; Placefile by Ken True, saratoga-weather.org
; Updated: ".date("D, d M Y H:i:s T", $ourStart)."
Title: Blitzortung USA ".date('H:i:s T D M d',$ourStart)."
RefreshSeconds: 300
IconFile: 1,30,30,15,15,$GR3icons\n";
	fwrite($GRfile,$GRInitRecs);
	$GRInitRecsUTC = 
"; Bliztortung USA Placefile for GRLevel3
; Placefile by Ken True, saratoga-weather.org
; Updated: UTC:$ourStart)
Title: Blitzortung USA UTC:$ourStart
RefreshSeconds: 300
IconFile: 1,30,30,15,15,$GR3icons\n";
	fwrite($GRfileUTC,$GRInitRecsUTC);
  }
  $file= @fopen($local_strikes_file, 'r');
  $total_time = 0;
  while ($line= fgets ($file)) {
	$nLines++;
	list($strike_time,$strike_lat,$strike_lon) = explode('|',trim($line));
#	$strike= json_decode($line);
#	$strike->time/=1000000000;
 	$time_start = microtime(true);
	if (($end_time-$time_interval < $strike_time)&&($strike_time <= $end_time)&&
		($west <= rad($strike_lon))&&(rad($strike_lon) <= $east)&&
		($north >= rad($strike_lat))&&(rad($strike_lat) >= $south)) {
	  $latR= $strike_lat;
	  $lonR= $strike_lon;
	  if($GRplacefilename <> '') {
		$GRrec = "Icon: $latR,$lonR,0,1,"; // initial placefile entry
	  }
      $time_stop = microtime(true);
	  $x= (int)(($img_width)*(rad($strike_lon)-$west)/($east-$west));
	  $y= (int)($img_width*(mercator_proj($north)-mercator_proj(rad($strike_lat)))/($east-$west));
  
	  $col= $red_dark;
	  $GRiconNum = 9;
	  if ($end_time-$strike_time < $time_interval/6) {
		$col= $white;
		$GRiconNum = 1;
	  }
	  else if ($end_time-$strike_time < 2*$time_interval/6) {
		$col= $yellow;
		$GRiconNum = 2;
	  }
	  else if ($end_time-$strike_time < 3*$time_interval/6) {
		$col= $orange;
		$GRiconNum = 3;
	  }
	  else if ($end_time-$strike_time < 4*$time_interval/6) {
		$col= $red_light;
		$GRiconNum = 4;
	  }
	  else if ($end_time-$strike_time < 5*$time_interval/6) {
		 $col= $red;
		 $GRiconNum = 5;
	  }
	  if($GRplacefilename <> '') {
		$GRtime = date("g:i:sa T",(integer)$strike_time);
		#$GRtimeUTC = gmdate("Y-m-d H:i:s",$strike_time)." UTC";
		$GRrecUTC = $GRrec;
		$GRrec .= "$GRiconNum,Blitzortung @ $GRtime\n";
		fwrite($GRfile,$GRrec);
		#$GRrecUTC .= "$GRiconNum,Blitzortung @ $GRtimeUTC\n";
		$GRrecUTC .= "$GRiconNum,$strike_time\n";
		fwrite($GRfileUTC,$GRrecUTC);
	  }
  
  //
  // star
  //
	  imageline ($img, $x-3, $y+0, $x+3, $y+0, $col);
	  imageline ($img, $x+0, $y-3, $x+0, $y+3, $col);
	  if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
	    imagefilledpolygon ($img, array ($x-1, $y-1, $x+1, $y-1, $x+1, $y+1, $x-1, $y+1), $col);
		} else {
	    imagefilledpolygon ($img, array ($x-1, $y-1, $x+1, $y-1, $x+1, $y+1, $x-1, $y+1), 4, $col);
		}
	  
  //
  // square
  //
  //    imagefilledpolygon ($img, array ($x-2, $y-2, $x+2, $y-2, $x+2, $y+2, $x-2, $y+2), 4, $col);
  
  //
  // ball
  //
  //    imagefilledpolygon ($img, array ($x-1, $y-2, $x+1, $y-2, $x+2, $y-1, $x+2, $y+1, $x+1, $y+2, $x-1, $y+2, $x-2, $y+1, $x-2, $y-1), 8, $col);
  
  //
  // flash
  //
  //    imagefilledpolygon ($img, array ($x, $y-10, $x+7, $y-10, $x+2, $y-3, $x+7, $y-3, $x-6, $y+10, $x-1, $y, $x-4, $y), 7, $col);
  //    imagepolygon       ($img, array ($x, $y-10, $x+7, $y-10, $x+2, $y-3, $x+7, $y-3, $x-6, $y+10, $x-1, $y, $x-4, $y), 7, $black);
  
	  $strikes++;
	  $total_time += ($time_stop - $time_start);

	}
  }
  fclose ($file);
  $time_elapsed = sprintf("%01.3f",round($total_time,3));

  log_msg("$nLines strike lines processed.  $strikes drawn on map using $time_elapsed secs.\n");
  if($GRplacefilename <> '') {
		log_msg("placefile saved to $GRplacefilename\n");
		log_msg("placefile UTC saved to $GRfileUTCname\n");
	  fclose ($GRfile);
	  fclose ($GRfileUTC);
  }
  
  //
  // draw stations
  //
  $stations = 0;
  $StationStatus = array('10'=>0,'20'=>0,'30'=>0,'40'=>0,'f'=>0);
  $nLines =0;
  $mapArea = '<script type="text/javascript">
function showStation(station) {
    window.open("' . $URLpath . 'BO-station.php?station="+station, "_blank",
	"toolbar=no, menubar=no, titlebar=no, status=no, scrollbars=yes, resizable=yes, top=200, left=500, width=650, height=630");
}
</script>
';
  $basename = preg_replace('|.png$|','',$PNGfile);
  $mapArea .= '<map name="'.$basename.'" id="'.$basename.'">'."\n";
  $offlineMap = '';
	
  if (is_array($StationsJSON) and count($StationsJSON) > 0) {
	foreach (array('Offline','Online') as $pass) {
	  foreach ($StationsJSON as $line => $S) {
		$station = $S;
		$station['station'] = $line;
		
		if($pass == 'Offline' and $station['controller_status'] !== '10') { continue; }
		if($pass == 'Online'  and $station['controller_status'] == '10')  { continue; }
		$nLines++;
		if ($station['station'] > 0) {
	
		  if (($west <= rad($station['longitude']))&&(rad($station['longitude']) <= $east)&&
			  ($north >= rad($station['latitude']))&&(rad($station['latitude']) >= $south)) {
	
			$x= (int)(($img_width)*(rad($station['longitude'])-$west)/($east-$west));
			$y= (int)($img_width*(mercator_proj($north)-mercator_proj(rad($station['latitude'])))/($east-$west));
	//controller status (0 = unknown, 10 = offline, 20 = idle, 30 = running, 40 = interference,
	// 50 = invalid data, 60 = invalid signal, 70 = bad gps, 80 = no amplifiers ,
	// 90 = controller fault, 100 communication error)
			$col= $fault_col;
			if ($station['controller_status'] == "10") {
			  $col= $inactive_col;
			}
			if ($station['controller_status'] == "20") {
			  $col= $idle_col;
			}
			if ($station['controller_status'] == "30") {
			  $col= $active_col;
			}
			if ($station['controller_status'] == "40") {
			  $col= $interf_col;
			}
			
			if($station['controller_status'] < "10" or $station['controller_status'] > "40") {
				$StationStatus['f']++;
			} else {
				$StationStatus[$station['controller_status']]++;
			}
	
	    if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
			  imagefilledpolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), $col);
			  imagepolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), $black);
			} else {
			  imagefilledpolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), 4, $col);
			  imagepolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), 4, $black);
			}
			imageline    ($img, $x-1, $y, $x+1, $y, $black);
			imageline    ($img, $x, $y-1, $x, $y+1, $black);
			if(isset($showStationNames) and $showStationNames) {
			  imagestring ($img, 1, $x+5, $y-4, $station['city'], $white);
			}
			if ($pass == 'Offline') {
				$offlineMap .= gen_map_area($station,$x,$y,$URLpath);
			} else {
			    $mapArea .= gen_map_area($station,$x,$y,$URLpath);
			}
			if($doDebug) {
			  $d = sprintf('%s %d %s (%d,%d)',
				 $station['controller_status'],$station['station'],$station['city'],$x,$y);
			  log_msg(".. $d\n");
			}
			
			$stations++;
		  }
		}
	  }
	} 
	log_msg("$nLines stations in Blitzortung region $region. Drew $stations stations within our map area.\n");
	$mapArea .= $offlineMap . "</map>\n"; // append offline stations at end of map def.
  }
  
  
  //
  // header line
  //
  $xl= 0; $xr= $img_width; $yt= 0; $yb= 16;
	if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    imagefilledpolygon ($img, array ($xl, $yt, $xl, $yb, $xr, $yb, $xr, $yt), $bground_col);
	} else {
    imagefilledpolygon ($img, array ($xl, $yt, $xl, $yb, $xr, $yb, $xr, $yt), 4, $bground_col);
	}
  imageline ($img, $xl, $yb+1, $xr, $yb+1, $black);
  $text= sprintf ("www.Blitzortung.org %s - %s / %d Strikes", date('Y-m-d T g:ia',$end_time-$time_interval), date('g:ia',$end_time), $strikes);
  imagestring ($img, 5, 5, 0, $text, $white);
  
  
  //
  // draw legend
  //
  if(isset($legendLoc)) {
	  $y = preg_match('|top|i',$legendLoc)?$yb+2:$img_height-153;
	  $x = preg_match('|left|i',$legendLoc)?1:$img_width-104;
  } else {
	$x= 1;
	$y= $img_height - 90;
  }
  
   $w= 102;
   $h= 152;
  
	 if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
     imagefilledpolygon ($img, array ($x, $y, $x, $y+$h, $x+$w, $y+$h, $x+$w, $y), $bground_col);
	 } else {
     imagefilledpolygon ($img, array ($x, $y, $x, $y+$h, $x+$w, $y+$h, $x+$w, $y), 4, $bground_col);
	 }
  imageline ($img, $x, $y+$h+1, $x+$w, $y+$h+1, $black);
  imageline ($img, $x+$w+1, $y, $x+$w+1, $y+$h+1, $black);
  
  $x+= 8;
  $y+= 12;
  $dx= 10;
  $dy= -10;
  imagestring ($img, 3, $x+$dx, $y+$dy, 'Strike age', $white);
  $strikeColors = array($white,$yellow,$orange,$red_light,$red,$red_dark);
  
  for ($p=0;$p<6;$p++) {
		$y+= 11;
		$col = $strikeColors[$p];
		imagestring ($img, 2, $x+$dx, $y+$dy, intval($p*$time_interval/6/60) . " - " . 
		 intval(($p+1)*$time_interval/6/60) . ' min', $white);
		if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
	    imagefilledpolygon ($img, array ($x-3, $y-6, $x+3, $y-6, $x+3, $y, $x-3, $y), $col);
	  } else {
	    imagefilledpolygon ($img, array ($x-3, $y-6, $x+3, $y-6, $x+3, $y, $x-3, $y), 4, $col);
	  }
  }
  
  // draw Station Legend
  //10 = offline, 20 = idle, 30 = running, 40 = interference, f=fault
  $Legends = array(
	 'Active' => array($active_col,'30'),
	 'Interference' => array($interf_col,'40'),
	 'Idle' => array($idle_col,'20'),
	 'Offline' => array($inactive_col,'10'),
	 'Fault' => array($fault_col,'f')
	);
  
  $y+= 11;
  imagestring ($img, 3, $x-7, $y+$dy, 'Station Status', $white);
  $y+= 11;
   
  $x-=2;
	if(count($StationList) > 5) {
		foreach ($Legends as $text => $stat) {
		$col = $stat[0];
		$idx = isset($stat[1])?$stat[1]:0;
		if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
			imagefilledpolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), $col);
		  imagepolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), $black);
		} else {
			imagefilledpolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), 4, $col);
		  imagepolygon ($img, array ($x-3, $y-3, $x+3, $y-3, $x+3, $y+3, $x-3, $y+3), 4, $black);
		}
		imageline    ($img, $x-1, $y, $x+1, $y, $black);
		imageline    ($img, $x, $y-1, $x, $y+1, $black);
		imagestring ($img, 2, $x+8, $y-8, $StationStatus[$idx].'-'.$text, $white);
		$y+=11;
		}
	} else {
		imagestring($img,2,$x+1,$y-8,'Station data is',$white);
		$y+=11;
		imagestring($img,2,$x+1,$y-8,'not available.',$white);
	}
		
	log_msg("Legend drawn for ".count($Legends)." types of station status.\n");

  // credit where credit is due :) 
  $y = $img_height - imagefontheight(1)-2;
  $x = (integer)(($img_width / 2) - (imagefontwidth(1)*strlen($Credits)/2));
  imagestring($img, 1, $x, $y, $Credits, $white);  
  
  //
  // output image
  //
  //header("Content-type: image/png");
  //imagepng($img);
  if($doNoDataMsg) {
		if(!isset($noDataMessage)) {
			$noDataMessage = 'Map is not current. Data from Blitzortung is not available.';
		}
		$fSize = 5;
		$y = $img_height/2;
		$x = ($img_width / 2) - (imagefontwidth($fSize)*strlen($noDataMessage)/2); // center
		$tX = $x+imagefontwidth($fSize)*strlen($noDataMessage);  // rightmost string width
		$tY = $y+imagefontheight($fSize); // bottommost string height
		
		imagefilledrectangle($img,$x-5,$y-5,$tX+5,$tY+5,$red_dark);
		imagerectangle($img,$x-6,$y-6,$tX+6,$tY+6,$white);
		imagestring($img,$fSize,$x,$y,$noDataMessage,$white);
	  log_msg("NOTE: '$noDataMessage' added.\n");
	}
  imagepng($img,$BOcacheDir.$PNGfile); // save the image
  log_msg("Image saved to $BOcacheDir$PNGfile.\n");
  if($doThumb) {
	$tPNGfile = preg_replace('|.png$|','-sm.png',$PNGfile);
	imagecopyresampled($imgThumb,$img,0,0,0,0,$imgThumb_width,$imgThumb_height,$img_width,$img_height);
	imagepng($imgThumb,$BOcacheDir.$tPNGfile); // save the image
	log_msg("Image -sm saved to $BOcacheDir$tPNGfile.\n");
  }
  
  $mapAreaFile = preg_replace('|.png$|i','-map.html',$PNGfile);
  if(isset($doMapArea) and $doMapArea) {
		$fp = fopen($BOcacheDir.$mapAreaFile, "w"); 
		if ($fp) { 
		  $write = fputs($fp, $mapArea); 
		  fclose($fp);
		  log_msg("HTML map area written to $BOcacheDir$mapAreaFile.\n");  
		}
  }
   $snap_finish = time();
   $snap_elapsed = $snap_finish - $snap_start;
   log_msg("Completed $PNGfile generation in $snap_elapsed seconds.\n\n");
   gen_animated_gif($img,$BOcacheDir,$PNGfile,$numimages); 
   if($doThumb) {
	$tPNGfile = preg_replace('|.png$|','-sm.png',$PNGfile);
	gen_animated_gif($imgThumb,$BOcacheDir,$tPNGfile,$numimages); 
   }

  //
  // free memory
  //
  imagedestroy($img);
  if($doThumb) {
	imagedestroy($imgThumb);
  }
} 
#---------end foreach map loop --------------------------
rotate_stations_files ( $local_stations_file, $numimages );

$ourEnd = time();
$ourElapsed = $ourEnd-$ourStart;
log_msg("Elapsed time $ourElapsed seconds.\n");

if($doDebug or $doLog) {
      $fp = fopen($log_filename, "w"); 
	  if ($fp) { 
		log_msg("Log written to $log_filename.\n");  
        $write = fputs($fp, $Status); 
        fclose($fp);  
	  } 	
}


#-----------------------------------------------------------------------
# end of main program
#-----------------------------------------------------------------------
//
// functions
//
#-----------------------------------------------------------------------
function rad($a)
{
  return($a*pi()/180.0);
}

#-----------------------------------------------------------------------
function log_msg ( $msg ) {
	global $Status,$doLog,$doPrint;
	
	if($doLog and $msg <> '') {
		$Status .= $msg;
		if($doPrint) { print $msg; }
	}
	
}

#-----------------------------------------------------------------------
function gen_map_area( $station,$x, $y, $URLpath ) {
  global $Status,$doLog,$doPrint,$doMapArea;
  
 
// generate <area> entries for optional imagemap
// $x-3, $y-3, top-left
// $x+3, $y-3, top-right
// $x+3, $y+3, bottom-right
// $x-3, $y+3, bottom-left

  $area = '  <area shape="rect" coords="'.sprintf('%d,%d,%d,%d',$x-3,$y-3,$x+3,$y+3).'" ';
/*  $area .= 'href="http://www.myblitzortung.de/blitzortung/station.php?bo_page=statistics&amp;bo_show=station&amp;bo_station_id='.$station['station'].'&amp;bo_lang=en" target="_blank" ';

*/
#   $area .= 'href="'.$URLpath.'BO-station.php?station='.$station['station'].'" ';
   
   $area .= 'href="#" onclick="return showStation('.$station['station'].');" ';

 
/*
{"station":"802","user":"713","city":"Saratoga","comments":"200mm ferrites","country":"United States \/ California","website":"saratoga-weather.org","latitude":"37.274563","longitude":"-122.022888","altitude":"104","controller_board":"10.3","firmware":"7.4","controller_status":"30","input_0_board":"12.3","input_1_board":"12.3","input_2_board":"12.3","input_3_board":"?","input_4_board":"?","input_5_board":"?","input_0_firmware":"1.6","input_1_firmware":"1.6","input_2_firmware":"1.6","input_3_firmware":"0.0","input_4_firmware":"0.0","input_5_firmware":"0.0","input_0_gain":"5.1","input_1_gain":"5.2","input_2_gain":"-1.-1","input_3_gain":"-1.-1","input_4_gain":"-1.-1","input_5_gain":"-1.-1","input_0_antenna":"F;200,7.5;","input_1_antenna":"F;200,7.5;","input_2_antenna":";;","input_3_antenna":";;","input_4_antenna":";;","input_5_antenna":";;","signals":"2022","last_signal":"2015-04-11 16:50:46","last_signal_nsec":"1428771046088820132","last_strike":"2015-04-11 16:46:30","last_strike_nsec":"0","50_km_60_m":"0","50_km_60_m_a":"0","50_km_60_m_e":"0","500_km_60_m":"0","500_km_60_m_a":"0","500_km_60_m_e":"0","5000_km_60_m":"8","5000_km_60_m_a":"6154","5000_km_60_m_e":"0.129997"}

*/ 

$statLookup = array( // decode controller_status entries to text
 '0' => 'Unknown',
 '10' => 'Offline',
 '20' => 'Idle',
 '30' => 'Running',
 '40' => 'Interference',
 '50' => 'Invalid data',
 '60' => 'Invalid signal',
 '70' => 'Bad GPS',
 '80' => 'No amplifiers',
 '90' => 'Controller fault',
 '100' => 'Communication error',
);

  $cstat = isset($statLookup[$station['controller_status']])?
            $statLookup[$station['controller_status']]:'Unknown';
 
  $atitle = 'Station #'.$station['station'] . ' ' . $cstat . 
            ' (' . $station['city'] . ', ' . $station['country'] . ') - click for details.';
	
  $area .= ' title="'.$atitle.'" alt="'.$atitle.'"'. "/>\n";
  
  return($area);
  
}

# -------------------------------------------------------------------

function rotate_stations_files ( $local_stations_file , $numimages=12) {
  global $Status;

  if(isset($local_stations_file)) { // save off the stations file in a rotation
    log_msg("Rotating stations file(s)\n");
	$bname = preg_replace('|.txt$|','',$local_stations_file);
	for ($i=$numimages-1;$i>0;$i--) {
	  $iplus = $i+1;
	  if(file_exists($bname."_$i.txt")) { 
		rename  ($bname."_$i.txt", $bname."_$iplus.txt");
		log_msg(" rename  ${bname}_$i.txt to ${bname}_$iplus.txt\n");
	  } else {
		copy($local_stations_file,$bname."_$i.txt");
		log_msg(" created missing  ${bname}_$i.txt \n");
	  }
	}
	if(!file_exists($bname."_$numimages.txt")) {
	  copy($local_stations_file,$bname."_$numimages.txt");
	  log_msg(" created missing  ${bname}_$numimages.txt \n");
	}
	  copy($local_stations_file,$bname."_1.txt");
	  log_msg(" saved new ${bname}_1.txt \n");
	  log_msg("Rotation complete for stations file(s).\n");
  }
	
}

# -------------------------------------------------------------------

// Adapted from Wasp2Animator script -- Ken True - 27-Jun-2014
// Original script by SLOweather.com (2008) which
// created an animated GIF from WASP2 PNGs. 

function gen_animated_gif( 
   $imghandle,
   $imgdir, 
   $outputname, 
   $numimages=12, 
   $delay=50
   )  {
	   
  global $Status,$ourTZ,$timeFormat;
  $start_time = time();
  $p = pathinfo($outputname);
  $basename = $p['filename']; // the output file basename.
  $dirname  = $imgdir;
  if($dirname == '.') {$dirname = '';}
  $doProgressBarSmall = preg_match('|-sm$|',$basename)?true:false;
  
  // Set the delay between frames, in hundredths of a second
  $delay = 50; // 25 = a quarter of a second...
  
  // Name the output file.
  $output = $dirname.$basename.'-ani.gif';
  
  $imgX = imagesx($imghandle);
  $imgY = imagesy($imghandle);
  log_msg("Starting animated GIF on $output height=$imgY width=$imgX\n");
  # Set timezone in PHP5/PHP4 manner
	if (!function_exists('date_default_timezone_set')) {
		if (! ini_get('safe_mode') ) {
		   putenv("TZ=$ourTZ");  // set our timezone for 'as of' date on file
		}  
  #	$Status .= "<!-- using putenv(\"TZ=$ourTZ\") -->\n";
	  } else {
	  date_default_timezone_set("$ourTZ");
  #	$Status .= "<!-- using date_default_timezone_set(\"$ourTZ\") -->\n";
	 }
  $timeStamp = time();
  
  if (! $imghandle) {return; }
  
  // Start out by renaming all of the old images here
  // rename  ( string $oldname  , string $newname  [, resource $context  ] )
  
	$EXT = 'png';
	for ($i=$numimages-1;$i>0;$i--) {
	  $iplus = $i+1;
	  if(file_exists($dirname.$basename."_$i.$EXT")) { 
		rename  ($dirname.$basename."_$i.$EXT", $dirname.$basename."_$iplus.$EXT");
		log_msg(" rename  $dirname${basename}_$i.$EXT to $dirname${basename}_$iplus.$EXT\n");
	  } else {
		imagepng($imghandle,$dirname.$basename."_$i.$EXT");
		log_msg(" created missing  $dirname${basename}_$i.$EXT \n");
	  }
	}
	if(!file_exists($dirname.$basename."_$numimages.$EXT")) {
	  imagepng($imghandle,$dirname.$basename."_$numimages.$EXT");
	  log_msg(" created missing  $dirname${basename}_$numimages.$EXT \n");
	}

  
  // save as a png for flash animation
  imagepng($imghandle,$dirname.$basename.'_1.png');
  
  
  $imgNum = 1;
  for ($i=$numimages;$i>0;$i--) { // create the intermediate GIF images marked with sequence
	$img= imagecreatefrompng($dirname.$basename."_$i.png");
	$img_width= imagesx($img);
	$img_height= imagesy($img);
	$white = imagecolorallocate($img, 255, 255, 255);
	$light_gray = imagecolorallocate($img, 192, 192, 192);
	$blue = imagecolorallocate($img, 0, 0, 127);
	if($doProgressBarSmall) {
	  $barLen = 20; // pixels for progress bar
	  $barHeight = 1; // height of the progress bar
	  $yC = imagefontheight(1);
	  $xC = ($img_width / 2);
	  $xBar = $xC-($barLen/2);
	  $yBar = $yC + (imagefontheight(1)/2)-2;
	  if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
	    imagepolygon ($img, 
		  array ($xBar-1, $yBar-1, 
			   $xBar+$barLen+1, $yBar-1,
			   $xBar+$barLen+1, $yBar+$barHeight+1,
			   $xBar-1, $yBar+$barHeight+1),
			   $white);
		} else {
	    imagepolygon ($img, 
		  array ($xBar-1, $yBar-1, 
			   $xBar+$barLen+1, $yBar-1,
			   $xBar+$barLen+1, $yBar+$barHeight+1,
			   $xBar-1, $yBar+$barHeight+1),
			   4, $white);
		}
	  $xLen = round($barLen*$imgNum/$numimages,0);
	  if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
			imagefilledpolygon ($img, 
			array ($xBar, $yBar,
			$xBar+$xLen, $yBar,
			$xBar+$xLen, $yBar+$barHeight,
			$xBar, $yBar+$barHeight),
			$white);
		} else {
			imagefilledpolygon ($img, 
			array ($xBar, $yBar,
			$xBar+$xLen, $yBar,
			$xBar+$xLen, $yBar+$barHeight,
			$xBar, $yBar+$barHeight),
			4, $white);
		}
  } else { // regular progress bar
	  $barLen = 100; // pixels for progress bar
	  $barHeight = 6; // height of the progress bar
	  $tcnt = ($imgNum<10)?" $imgNum":"$imgNum";
	  $seqNum = "$tcnt / $numimages";
	  $yC = imagefontheight(3)+ 5;
	  $xC = ($img_width / 2);
	  $xBar = $xC-($barLen/2);
	  $yBar = $yC + (imagefontheight(2)/2)-2;
	  if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
  	  imagepolygon ($img, 
  		array ($xBar-1, $yBar-1, 
			   $xBar+$barLen+1, $yBar-1,
			   $xBar+$barLen+1, $yBar+$barHeight+1,
			   $xBar-1, $yBar+$barHeight+1),
			   $white);
		} else {
  	  imagepolygon ($img, 
  		array ($xBar-1, $yBar-1, 
			   $xBar+$barLen+1, $yBar-1,
			   $xBar+$barLen+1, $yBar+$barHeight+1,
			   $xBar-1, $yBar+$barHeight+1),
			   4, $white);
		}
	  $xLen = round($barLen*$imgNum/$numimages,0);
	  
	  if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
			imagefilledpolygon ($img, 
			array ($xBar, $yBar,
			$xBar+$xLen, $yBar,
			$xBar+$xLen, $yBar+$barHeight,
			$xBar, $yBar+$barHeight),
			$light_gray);
		} else {
			imagefilledpolygon ($img, 
			array ($xBar, $yBar,
			$xBar+$xLen, $yBar,
			$xBar+$xLen, $yBar+$barHeight,
			$xBar, $yBar+$barHeight),
			4, $light_gray);
		}
  
  
	  $y = imagefontheight(3)+ 5;
	  $x = (integer)(($img_width / 2) + $barLen/2 + 4);
	  imagestring($img, 2, $x, $y, $seqNum, $white);
	} // end progress bar
    imagegif($img,$dirname.$basename."_$i.gif");
	imagedestroy($img);
    $imgNum++;
  }

  
  // Setup the animation control
    
  $frames = array();
  $framed = array();
  
  for ($i=$numimages;$i>0;$i--) {
	$frames[] = $dirname.$basename."_$i.gif";
	$framed[] = $delay;
  }
  $framed[$numimages-1] = $delay*3; # pause 3x at last frame
  
  /*
		  GIFEncoder constructor:
		  =======================
  
		  image_stream = new GIFEncoder    (
							  URL or Binary data    'Sources'
							  int                    'Delay times'
							  int                    'Animation loops'
							  int                    'Disposal'
							  int                    'Transparent red, green, blue colors'
							  int                    'Source type'
						  );
  */
  $gif = new GIFEncoder (
							  $frames,
							  $framed,
							  0,
							  2,
							  0, 0, 0,
							  "url"
		  );
  /*
		  Possibles outputs:
		  ==================
  
		  Output as GIF for browsers :
			  - Header ( 'Content-type:image/gif' );
		  Output as GIF for browsers with filename:
			  - Header ( 'Content-disposition:Attachment;filename=myanimation.gif');
		  Output as file to store into a specified file:
			  - FWrite ( FOpen ( "myanimation.gif", "wb" ), $gif->GetAnimation ( ) );
  */
  //Header ( 'Content-type:image/gif' );
  //echo    $gif->GetAnimation ( );
  
  $fh = fopen($output, "wb");
  fwrite($fh, $gif->GetAnimation ( ));
  fclose($fh);
  log_msg("Animated GIF saved to $output.\n");

  $end_time = time();
  $elapsed = $end_time - $start_time;
  log_msg("Animated GIF processing completed for $output in $elapsed seconds.\n\n");  
  return(0);
}
# -------------------------------------------------------------------

function show_size($size)
{
 if (!is_numeric($size)) {return false;}
 else
 {
  if ($size >= 1073741824) {$size = round($size/1073741824*100)/100 ." GB";}
  elseif ($size >= 1048576) {$size = round($size/1048576*100)/100 ." MB";}
  elseif ($size >= 1024) {$size = round($size/1024*100)/100 ." KB";}
  else {$size = $size . " B";}
  return $size;
 }
}

// end of functions
#-----------------------------------------------------------------------
?>
