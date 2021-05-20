<?php
// b5m profile API

// The data comes from the b5m OGC WCS Service

// Requests
$coors = $_REQUEST["coors"];
$srs = $_REQUEST["srs"];
$fmt = $_REQUEST["format"];

// Function to get altitude values
function altitude($x1, $y1, $x2, $y2, $srs, $session_id) {
	$wcs = "https://b5mprod/ogc/wcs/gipuzkoa_wcs?SERVICE=WCS&VERSION=1.0.0&REQUEST=GetCoverage&FORMAT=GEOTIFFFLOAT32&COVERAGE=LIDAR%202008:%20Lur%20eredua-Modelo%20de%20suelo&CRS=" . $srs . "&RESPONSE_CRS=" . $srs . "&WIDTH=10&HEIGHT=10&BBOX=";
	if (strtolower($srs) == "epsg:4326") {
		$offset = 0.0001;
	} else {
		$offset = 1;
	}

	// Distance of the line
	if (strtolower($srs) == "epsg:4326") {
		// Distance from latitude and longitude
    // Converting to radians
    $longi1 = deg2rad($x1);
    $longi2 = deg2rad($x2);
    $lati1 = deg2rad($y1);
    $lati2 = deg2rad($y2);

    // Haversine Formula
    $difflong = $longi2 - $longi1;
    $difflat = $lati2 - $lati1;

    $val = pow(sin($difflat / 2), 2) + cos($lati1) * cos($lati2) * pow(sin($difflong / 2), 2);
    $distance =6378800 * (2 * asin(sqrt($val)));
	} else {
		$distance = sqrt(pow($x2 - $x1, 2)+pow($y2 - $y1, 2));
	}
	return "$x1 $y1 $x2 $y2 $distance";

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
		$x1 = $coor;
	} else if ($i == 2) {
		$y1 = $coor;
	} else if ($i == 3) {
		$x2 = $coor;
	} else if ($i == 4) {
		$y2 = $coor;
		echo "KK1: $x1 $y1 $x2 $y2 <br />";
		$profile = altitude($x1, $y1, $x2, $y2, $srs, $session_id);
		echo "KK2: $profile <br />";
		$x1 = $x2;
		$y1 = $y2;
		$i = 2;
//	} else {
//		$coor2 = $coor;
//		$altitude = altitude($coor1, $coor2, $srs, $session_id);
//		$i = 0;
//		echo $coor1 . " - " . $coor2 . " - " . $altitude ."<br />";
	}
	$i++;
}

// Session end
session_destroy();
session_write_close();
