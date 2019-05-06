<?php
#--------------------------------------------------------------------------------
# BO-station-inc.php - display details from the saved stations_N.txt files
#
#  Author:  K. True - webmaster@saratoga-weather.org
#
#  Version 1.00 - 16-Apr-2015 - initial release
#  Version 1.01 - 21-Apr-2015 - added $numimages variable to correspond to images in GIF 
#  Version 1.02 - 20-May-2015 - corrected $BOcacheDir to 'cache/', added debug info to output
#  Version 1.03 - 30-Oct-2018 - adjusted for new format of stations file JSON
#--------------------------------------------------------------------------------
$Version = 'BO-stations-inc - V1.03 - 30-Oct-2018';
$Credits = 'script by saratoga-weather.org';

#--------------------configure to match gen-BO-maps.php settings ----------------
$BOcacheDir = "cache/";
$local_stations_file = $BOcacheDir.'stations.txt';
$numimages = 12;  # set this the same as in gen_BO_maps.php file
$ourTZ = 'America/New_York';
$timeFormat = "d-M H:i:s T";
#--------------------end configure-----------------------------------------------
print "<!-- $Version -->\n";

if(!isset($_GET['station']) or ! preg_match('|^[0-9]+$|',$_GET['station']) ) {
	print "<h2>Sorry.. station selection not numeric or missing.</h2>\n";
	return ('');
} else {
	$req_station = $_GET['station'];
	$req_station = preg_replace('|[^0-9]+|','',$req_station);
}

if(!file_exists($local_stations_file)) {
	print "<h2>Sorry.. $local_stations_file is not found.</h2>\n";
	return('');
}
$found = false;
$StationsList = file_get_contents($local_stations_file);
$StationsJSON = json_decode($StationsList,true);

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
  print  "<!-- JSON decode $error -->\n";
}

if(isset($StationsJSON[$req_station])) {
	$S = $StationsJSON[$req_station];
	$S['station'] = $req_station; // add station ID to JSON record
	$found = true;
}
/*
foreach ($StationsList as $StationRec) {
	$S = json_decode($StationRec,true); // decode into associative array
	if ($S['station'] == $req_station) {
		$found = true;
		break;
	}
}
*/
	
if(!$found) {
	print "<h2>Sorry.. station=$req_station not found in Blitzortung list.</h2>\n";
	print "<pre>\n";
	print_r($StationsJSON);
	print "</pre>\n";
	return('');
}

if (!function_exists('date_default_timezone_set')) {
	
	if (! ini_get('safe_mode') ) {
	   putenv("TZ=$ourTZ");  // set our timezone for 'as of' date on file
	}
  } else {
   date_default_timezone_set($ourTZ);
 }
/*
OLD: 
$Station is:

{
	"station": "808",
	"user": "696",
	"city": "Minden - 808",
	"comments": "300mm x 7.5mm ferrite, E-field 370mm x 2mm",
	"country": "United States \/ Nevada",
	"website": "carsonvalleyweather.com",
	"latitude": "39.038502",
	"longitude": "-119.721123",
	"altitude": "1467",
	"controller_board": "10.3",
	"firmware": "8.4",
	"controller_status": "30",
	"input_0_board": "12.3",
	"input_1_board": "12.3",
	"input_2_board": "12.3",
	"input_3_board": "13.1",
	"input_4_board": "13.1",
	"input_5_board": "13.1",
	"input_0_firmware": "1.6",
	"input_1_firmware": "1.6",
	"input_2_firmware": "1.6",
	"input_3_firmware": "1.7",
	"input_4_firmware": "1.7",
	"input_5_firmware": "1.7",
	"input_0_gain": "8.2",
	"input_1_gain": "8.2",
	"input_2_gain": "-1",
	"input_3_gain": "8.2",
	"input_4_gain": "8.2",
	"input_5_gain": "8.4",
	"input_0_antenna": "F;300,7;S",
	"input_1_antenna": "F;300,7;S",
	"input_2_antenna": ";;",
	"input_3_antenna": "E;370;",
	"input_4_antenna": "E;370;",
	"input_5_antenna": "E;370;",
	"signals": "862",
	"last_signal": "2018-10-08 14:13:57",
	"last_signal_nsec": "1539008037135917985",
	"last_strike": "2018-10-08 14:14:06",
	"last_strike_nsec": "0",
	"50_km_60_m": "0",
	"50_km_60_m_a": "0",
	"50_km_60_m_e": "0",
	"500_km_60_m": "0",
	"500_km_60_m_a": "0",
	"500_km_60_m_e": "0",
	"5000_km_60_m": "195",
	"5000_km_60_m_a": "1357",
	"5000_km_60_m_e": "9.12814"
}

New is:

	"808": {
		"user": "696",
		"city": "Minden - 808",
		"comments": "300mm x 7.5mm ferrite, E-field 370mm x 2mm",
		"country": "United States \/ Nevada",
		"website": "carsonvalleyweather.com",
		"latitude": 39.038517,
		"longitude": -119.721138,
		"altitude": 1461,
		"controller_board": "10.3",
		"firmware": "8.4",
		"controller_status": "30",
		"input": [{
				"board": "12.3",
				"firmware": "1.6",
				"gain": "8.2",
				"antenna": "F;300,7;S"
			}, {
				"board": "12.3"
			}, {
				"board": "12.3"
			}, {
				"board": "12.3"
			}, {
				"board": "12.3"
			}, {
				"board": "12.3"
			}
		],
		"signals": "1518",
		"last_signal": "2018-08-28 13:42:53",
		"last_signal_nsec": "1535463773655377972",
		"last_strike": "2018-08-28 13:43:30",
		"last_strike_nsec": "0",
		"50_km_60_m": "0",
		"50_km_60_m_a": "0",
		"50_km_60_m_e": "0",
		"500_km_60_m": "0",
		"500_km_60_m_a": "0",
		"500_km_60_m_e": "0",
		"5000_km_60_m": "304",
		"5000_km_60_m_a": "5793",
		"5000_km_60_m_e": "1.37511"
	},

*/

do_print_header($S);

print "<div class=\"BOtable\">\n";
print "<p class=\"BOstation\" style=\"text-align: center\"><strong>Sensors</strong></p>\n";
print "<table width=\"100%\" class=\"BOdetail\">\n";
print "<tr>\n";
for ($i=0;$i<=5;$i++) {

/*	
	if($S["input_${i}_board"] != '?' and $S["input_${i}_gain"] != '-1.-1') {
		$iplus = $i+1;
		print "<td>";
		print "Input $iplus<br/>";
		print "Board: ".$S["input_${i}_board"]."<br/>";
		print "Firmware: ".$S["input_${i}_firmware"]."<br/>";
		print "Gain: ".preg_replace('|\.|',' * ',$S["input_${i}_gain"])."<br/>";
		print "Antenna: (".$S["input_${i}_antenna"].")<br/>";
		print decode_antenna($S["input_${i}_antenna"])."<br/>";
		print "</td>\n";
	}
*/
	if($S['input'][$i]['board'] != '?' and $S['input'][$i]['gain'] != '-1.-1') {
		$iplus = $i+1;
		print "<td style=\"vertical-align: text-top;\">";
		print "Input $iplus<br/>";
		print "Board: ".$S['input'][$i]['board']."<br/>";
		if(isset($S['input'][$i]['firmware'])) {
			print "Firmware: ".$S['input'][$i]['firmware']."<br/>";
		}
		if(isset($S['input'][$i]['gain'])) {
			print "Gain: ".preg_replace('|\.|',' * ',$S['input'][$i]['gain'])."<br/>";
		}
		if(isset($S['input'][$i]['antenna'])) {
			print "Antenna: (".$S['input'][$i]['antenna'].")<br/>";
		  print decode_antenna($S['input'][$i]['antenna'])."<br/>";
		}
		print "</td>\n";
	}
	
}
print "</tr>\n";
print "</table>\n";
print "</div>\n";


print "<div class=\"BOtable\">\n";
print "<table width=\"100%\" class=\"BOdetail\">\n";

print "<tr><th colspan=\"5\">&nbsp</th>";
print "<th colspan=\"9\">Number of strikes detected by the station and network at distance<br/>around the station for prior 60 minutes</th></tr>\n";
print "<tr>\n";
print " <th>Time</th>\n";
print " <th>Status</th>\n";
print " <th>signals</th>\n";
print " <th>last_signal</th>\n";
print " <th>last_strike</th>\n";
print " <th>50 km<br/>Station</th>\n";
print " <th>50 km<br/>Network</th>\n";
print " <th>50 km<br/>Eff.</th>\n";
print " <th>500 km<br/>Station</th>\n";
print " <th>500 km<br/>Network</th>\n";
print " <th>500 km<br/>Eff.</th>\n";
print " <th>5000 km<br/>Station</th>\n";
print " <th>5000 km<br/>Network</th>\n";
print " <th>5000 km<br/>Eff.</th>\n";
print "</tr>\n";

for ($i=1;$i<=$numimages;$i++) { // loop over the files;

  $tFile = preg_replace('|.txt$|',"_$i.txt",$local_stations_file);
  
  if(file_exists($tFile)) {
	$mTime = filemtime($tFile);
	$StationsList = file_get_contents($tFile);
	$found = false;
/*
	foreach ($StationsList as $StationRec) {
		$S = json_decode($StationRec,true); // associative array due to funky numeric names
		if ($S['station'] == $req_station) {
			$found = true;
			break;
		}
	}
*/

$StationsJSON = json_decode($StationsList,true);

if(isset($StationsJSON[$req_station])) {
	$S = $StationsJSON[$req_station];
	$S['station'] = $req_station; // add station ID to JSON record
	$found = true;
}

	if(!$found) { continue; }
	
	# Got a station record.. format the output row.
	print "<tr>\n";
	print " <td>".date($timeFormat,$mTime)."</td>\n";
	print " <td>".decode_status($S['controller_status'])."</td>\n";
	print " <td>".$S['signals']."</td>\n";
	print " <td>".decode_time($timeFormat,$S['last_signal'])."</td>\n";
	print " <td>".decode_time($timeFormat,$S['last_strike'])."</td>\n";
	print " <td>".$S['50_km_60_m']."</td>\n";
	print " <td>".$S['50_km_60_m_a']."</td>\n";
	print " <td>".sprintf("%01.1f",round($S['50_km_60_m_e'],1))."%</td>\n";
	print " <td>".$S['500_km_60_m']."</td>\n";
	print " <td>".$S['500_km_60_m_a']."</td>\n";
	print " <td>".sprintf("%01.1f",round($S['500_km_60_m_e'],1))."%</td>\n";
	print " <td>".$S['5000_km_60_m']."</td>\n";
	print " <td>".$S['5000_km_60_m_a']."</td>\n";
	print " <td>".sprintf("%01.1f",round($S['5000_km_60_m_e'],1))."%</td>\n";
/*
    [signals] => 1853
    [last_signal] => 2015-04-16 20:13:04
    [last_signal_nsec] => 1429215184272375510
    [last_strike] => 2015-04-16 20:13:48
    [last_strike_nsec] => 0
    [50_km_60_m] => 0
    [50_km_60_m_a] => 0
    [50_km_60_m_e] => 0
    [500_km_60_m] => 0
    [500_km_60_m_a] => 0
    [500_km_60_m_e] => 0
    [5000_km_60_m] => 752
    [5000_km_60_m_a] => 12006
    [5000_km_60_m_e] => 6.26354

*/
    print "</tr>\n";
  }
}

print "</table>\n";
print "</div>\n";


#--------------------------------------------------------------------------
function do_print_header($S) {
	
	print '<p class="BOstation">Station <strong>'.$S['station'].' '.$S['city'].'</strong> (<strong>'.$S['country']."</strong>)</br>\n";
	if(isset($S['website'])) {
		print "Website: <a href=\"http://".$S['website']."\" target=\"_blank\"><strong>".$S['website']."</strong></a><br/>\n";
	}
#	print 'Latitude <strong>'.$S['latitude'].'</strong> Longitude <strong>'.$S['longitude'].'</strong> Altitude <strong>'.$S['altitude']."m</strong><br/>\n";
	print 'Controller <strong>'.$S['controller_board'].'</strong> Firmware <strong>'.$S['firmware']."</strong><br/>\n";
	print 'Comments: <strong>'.$S['comments']."</strong><br/>\n";
	print "Details at <a href=\"http://www.myblitzortung.de/blitzortung/station.php?bo_page=statistics&amp;bo_show=station&amp;bo_station_id=".$S['station']."&amp;bo_lang=en\" target=\"_blank\"><strong>Blitzortung.org</strong></a><br/>\n";
	print "</p>\n";

  return('');
}

function decode_status($status) {
  //controller status (0 = unknown, 10 = offline, 20 = idle, 30 = running, 40 = interference,
  // 50 = invalid data, 60 = invalid signal, 70 = bad gps, 80 = no amplifiers ,
  // 90 = controller fault, 100 communication error)
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
	
	if(isset($statLookup[$status])) {
		return($statLookup[$status]);
	} else {
		return("Unk status: '$status'");
	}
	
}

function decode_time($dateFormat, $timetext ) {
	$tstamp = strtotime($timetext);
	if($tstamp > 1000) {
		return(date($dateFormat,$tstamp));
	} else {
		return("Unknown<!-- ".$timetext." -->");
	}
	
}

function decode_antenna($A) {
  $Types = array(
	'F' => 'Ferrite rod|Len. |mm, |mm Diam.',
	'L' => 'Loop antenna|Diam. |mm, | turns  ',
	'C' => 'Coaxial Loop|Diam. |mm, | loops  ',
	'E' => 'Electric Field|Len. |mm  |  |',
	'M' => 'Monopole|Len. |mm  |  |',
	'D' => 'Dipole|Len. |mm  |  |',
	);
	$Aux = array (
	'S' => '<br/>Shielded',
	'T' => '<br/>w/Transformer',
	'S,T' =>'<br/>Shielded,<br/>w/Transformer',
    );
	
	if($A == ';;') {return('N/A'); }
	$input = explode(';',$A); // split 'em up.
	$ret = '';
	$legend = array();
	$t=$input[0];
	if(isset($Types[$t])) {
		$legend = explode('|',$Types[$t].'||||');
		$ret .= $legend[0].'<br/>';
	}
	$t=$input[1];
	if (preg_match('|^([0-9\.]+)\,([0-9\.]+)$|',$t,$tmatch)) {
		$ret .= $legend[1].$tmatch[1].$legend[2].$tmatch[2].$legend[3];
	} elseif (preg_match('|^([0-9\.]+)$|',$t,$tmatch)) {
		$ret .= $legend[1].$tmatch[1].$legend[2];
	}
	$t=$input[2];
	if (isset($Aux[$t])) {
		$ret .= $Aux[$t].', ';
	} elseif ($t != '') {
		$ret .= "$t, ";
	}
	if(strlen($ret) > 2) {$ret = substr($ret,0,-2); }
	return ($ret);
	
}

?>