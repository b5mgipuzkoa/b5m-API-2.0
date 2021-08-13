<?php
// b5m profile API
//
// Data comes from the b5m OGC WCS Service
//
// Requires profile.sh Bash script to calculate the profile
//
// Coordinate transformation with PROJ (proj.org)
// Raster query to get heights with GDAL (gdal.org)
//

// Requests
if (isset($_REQUEST['coors'])) $coors = $_REQUEST['coors']; else $coors = "";
if (isset($_REQUEST['srs'])) $srs = $_REQUEST['srs']; else $srs = "";
if (isset($_REQUEST['precision'])) $precision = $_REQUEST['precision']; else $precision = "";
if (isset($_REQUEST['format'])) $format = $_REQUEST['format']; else $format = "";

// Includes
include_once("includes/json2xml.php");

// Variables
$server_name = $_SERVER['SERVER_NAME'];
$max_number_coors = 20;

// Messages
$msg001="Warning: there are cases out of our range (Gipuzkoa: https://b5m.gipuzkoa.eus) where no height data exists, and the resulting height value is -9999";
$msg002="Missing required parameter: coors";
$msg003="SRS not supported. It should be: EPSG:25830, EPSG:4326 or EPSG:3857 (https://epsg.io)";
$msg004="The maximum number of coordinate pairs has been exceeded (" . $max_number_coors . ")";

// License and metadata variables
$provider = "b5m - Gipuzkoa Spatial Data Infrastructure - Gipuzkoa Provincial Council - 2021";
$url_license = "https://" . $server_name . "/web5000/en/legal-information";
$url_base = "https://" . $server_name . "/web5000";
$image_url = "https://" . $server_name . "/web5000/assets/img/logo-b5m.svg";
$altimetry_data = "Digital Terrain Model (DTM) of 1m from the Autonomous Community of the Basque Country, based on LIDAR data. Year 2012";
$altimetry_data_url = "http://www.geo.euskadi.eus/lidar-lur-zoruaren-eredu-digitalean-oinarritutako-euskal-autonomi-erkidegoko-norantza-mapa-2012-urtea/s69-geodir/eu";
$altimetry_units = "meters";
$response_time_units = "seconds";
$messages = "";

// Init statuscode and time
$statuscode = 0;
$init_time = microtime(true);

// Precision
if (($precision > 1) && ($precision < 6)){
	$precision = $precision;
} else {
	$precision = 1;
}

// Coordinates
if ($coors == "") {
	$statuscode = 2;
	$messages = $msg002;
}

// SRS
if (($srs != "") && (strtolower($srs) != "epsg:25830") && (strtolower($srs) != "epsg:4326") && (strtolower($srs) != "epsg:3857")) {
	$statuscode = 3;
	$messages = $msg003;
}

// Maximum number of coordinates
$coors_array = explode(",", $coors);
if (count($coors_array) / 2 > $max_number_coors) {
	$statuscode = 4;
	$messages = $msg004;
}

// Trying to guess the SRS
if ($srs == "") {
	if (($coors_array[0] >= -90) && ($coors_array[0] <= 90)) {
		$srs = "epsg:4326";
	} else if ($coors_array[0] > 90) {
		$srs = "epsg:25830";
	} else {
		$srs = "epsg:3857";
	}
}

if ($statuscode == 0) {
	// Data request
	$output = shell_exec("./profile.sh $coors $srs $precision");
	$data = explode("\n", $output);
	$data_last = array_pop($data);

	$i = 0;
	foreach ($data as $value) {
		$value_array = explode(" ", $value);
		$doc2["elevationProfile"][$i]["distance"] = $value_array[0];
		$doc2["elevationProfile"][$i]["height"] = $value_array[1];

		// Detecting out of range values
		if ($value_array[1] == "-9999") {
			$statuscode = 1;
			$messages = $msg001;
		}
		$i++;
	}
} else {
	$doc2 = [];
}

// Data license and metadata
$final_time = microtime(true);
$response_time = sprintf("%.2f", $final_time - $init_time);
$doc1["info"]["license"]["provider"] = $provider;
$doc1["info"]["license"]["urlLicense"] = $url_license;
$doc1["info"]["license"]["urlBase"] = $url_base;
$doc1["info"]["license"]["imageUrl"] = $image_url;
$doc1["info"]["metadata"]["altimetryData"] = $altimetry_data;
$doc1["info"]["metadata"]["altimetryDataUrl"] = $altimetry_data_url;
$doc1["info"]["metadata"]["altimetryUnits"] = $altimetry_units;
$doc1["info"]["responseTime"]["time"] = $response_time;
$doc1["info"]["responseTime"]["units"] = $response_time_units;
$doc1["info"]["statuscode"] = $statuscode;
$doc1["info"]["messages"] = $messages;
$doc1["coordinates"] = $coors;
$doc1["srs"] = $srs;
$doc1["precision"] = $precision;

// Merge Arrays
$doc = array_merge($doc1, $doc2);

// Output Format (JSON by default)
if ((strtolower($format) == "php") || (strtolower($format) == "phps")) {
	header("Content-type: text/plain;charset=utf-8");
	print_r($doc);
} else {
	$jsonres = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	if (strtolower($format) == "xml") {
		header("Content-type: application/xml;charset=utf-8");
		print_r(json2xml($jsonres));
	} else {
		header("Content-type: application/json;charset=utf-8");
		print_r($jsonres);
	}
}
