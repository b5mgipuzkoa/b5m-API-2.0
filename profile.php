<?php
// b5m profile API

// The data comes from the b5m OGC WCS Service

// Requests
$coors = $_REQUEST["coors"];
$srs = $_REQUEST["srs"];
$fmt = $_REQUEST["format"];

// Function to get altitude value
function altitude($x1, $y1, $srs, $session_id) {
	$wcs = "https://b5mprod/ogc/wcs/gipuzkoa_wcs?SERVICE=WCS&VERSION=1.0.0&REQUEST=GetCoverage&FORMAT=GEOTIFFFLOAT32&COVERAGE=LIDAR%202008:%20Lur%20eredua-Modelo%20de%20suelo&CRS=" . $srs . "&RESPONSE_CRS=" . $srs . "&WIDTH=10&HEIGHT=10&BBOX=";
	if (strtolower($srs) == "epsg:4326") {
		$offset = 0.0001;
	} else {
		$offset = 1;
	}
	$x2 = $x1 + $offset;
	$y2 = $y1 - $offset;
	$wcs_query = $wcs . $x1 . "," . $y1 . "," . $x2 . "," . $y2;
	$f_name = "/tmp/" . $session_id . ".tif";

	$ch = curl_init($wcs_query);
	$fp = fopen($f_name, "w");

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	curl_exec($ch);
	if(curl_error($ch)) {
	    fwrite($fp, curl_error($ch));
	}
	curl_close($ch);
	fclose($fp);

	$output = null;
	$retval = null;
	exec("gdallocationinfo " . $f_name . " 0 0", $output, $retval);

	unlink($f_name);

	$altitude_wcs = explode(" ", $output[3]);
	return round($altitude_wcs[5], 2);
}

// Session start
$session_id = session_create_id();
session_id($session_id);
session_start();

// Coordinates loop
$coors_array = explode(",", $coors);

$i = 1;
foreach ($coors_array as $coor)  {
	if ($i == 1) {
		$coor1 = $coor;
	} else {
		$coor2 = $coor;
		$altitude = altitude($coor1, $coor2, $srs, $session_id);
		$i = 0;
		echo $coor1 . " - " . $coor2 . " - " . $altitude ."<br />";
	}
	$i++;
}

// Session end
session_destroy();
session_write_close();
