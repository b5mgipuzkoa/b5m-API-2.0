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
$msg002="Warning: all the profile data is out of our range (Gipuzkoa: https://b5m.gipuzkoa.eus)";
$msg003="Missing required parameter: coors";
$msg004="SRS not supported. It should be: EPSG:25830, EPSG:4326 or EPSG:3857 (https://epsg.io)";
$msg005="The maximum number of coordinate pairs has been exceeded (" . $max_number_coors . ")";
$msg006="https://kokoalberti.com/articles/creating-elevation-profiles-with-gdal-and-two-point-equidistant-projection";

// License and metadata variables
$provider = "b5m - Gipuzkoa Spatial Data Infrastructure - Gipuzkoa Provincial Council - " . date('Y');
$url_license = "https://" . $server_name . "/web5000/en/legal-information";
$url_base = "https://" . $server_name . "/web5000";
$image_url = "https://" . $server_name . "/web5000/img/logo-b5m.png";
$elevation_data = "Digital Terrain Model (DTM) of 1m from the Historical Territory of Gipuzkoa, based on LIDAR data. Year 2008";
$elevation_data_url = "https://b5m.gipuzkoa.eus/web5000/en/csw2/GFA.LIDS08";
$elevation_units = "meters";
$distance_units = "meters";
$response_time_units = "seconds";
$messages = "";

// Init statuscode and time
$statuscode = 0;
$init_time = microtime(true);

// Checking Requests
if ($coors == "" && $srs == "" && $precision == "" && $format == "")
	$statuscode = -1;

// Precision
if ($precision > 1 && $precision < 6)
	$precision = $precision;
else
	$precision = 1;

// Coordinates
if ($coors == "" && $statuscode == 0) {
	$statuscode = 3;
	$messages = $msg003;
}

// SRS
if ($srs != "" && strtolower($srs) != "epsg:25830" && strtolower($srs) != "epsg:4326" && strtolower($srs) != "epsg:3857" && $statuscode == 0) {
	$statuscode = 4;
	$messages = $msg004;
}

// Maximum number of coordinates
if ($statuscode == 0) {
	$coors_array = explode(",", $coors);
	if (count($coors_array) / 2 > $max_number_coors) {
		$statuscode = 5;
		$messages = $msg005;
	}
}

// Trying to guess the SRS
if ($srs == "" && $statuscode == 0) {
	if ($coors_array[0] >= -90 && $coors_array[0] <= 90)
		$srs = "epsg:4326";
	else if ($coors_array[0] > 90)
		$srs = "epsg:25830";
	else
		$srs = "epsg:3857";
}

if ($statuscode == 0) {
	// Data request
	$output = shell_exec("./profile.sh $coors $srs $precision");
	$data = explode("\n", $output);
	$data_last = array_pop($data);

	$i = -1;
	$statuscode2 = 2;
	foreach ($data as $value) {
		$value_array = explode(" ", $value);
		if ($i == -1) {
			if (count($value_array) == 6) {
				$doc2["summary"]["profile_distance"] = $value_array[0];
				$doc2["summary"]["elevation_high"] = $value_array[1];
				$doc2["summary"]["elevation_low"] = $value_array[2];
				$doc2["summary"]["average_elevation"] = $value_array[3];
				$doc2["summary"]["total_elevation_gain"] = $value_array[4];
				$doc2["summary"]["total_elevation_loss"] = $value_array[5];
			}
		} else {
			$doc2["elevationProfile"][$i]["distance"] = $value_array[0];
			$doc2["elevationProfile"][$i]["height"] = $value_array[1];
		}

		// Detecting out of range values
		if ($value_array[1] == "-9999") {
			$statuscode = 1;
			$messages = $msg001;
		}
		if ($value_array[1] != "-9999" && $value_array[1] != "0.00")
			$statuscode2 = 0;
		$i++;
	}
	if ($statuscode2 == 2) {
			$statuscode = 2;
			$messages = $msg002;
	}
} else {
	$doc2 = [];
}

if ($statuscode == 0 || $statuscode == 1) {
	// Data license and metadata
	$final_time = microtime(true);
	$response_time = sprintf("%.2f", $final_time - $init_time);
	$doc1["info"]["license"]["provider"] = $provider;
	$doc1["info"]["license"]["urlLicense"] = $url_license;
	$doc1["info"]["license"]["urlBase"] = $url_base;
	$doc1["info"]["license"]["imageUrl"] = $image_url;
	$doc1["info"]["metadata"]["elevationData"] = $elevation_data;
	$doc1["info"]["metadata"]["elevationDataUrl"] = $elevation_data_url;
	$doc1["info"]["metadata"]["elevationUnits"] = $elevation_units;
	$doc1["info"]["metadata"]["distanceUnits"] = $distance_units;
	$doc1["info"]["responseTime"]["time"] = $response_time;
	$doc1["info"]["responseTime"]["units"] = $response_time_units;
	$doc1["info"]["statuscode"] = $statuscode;
	$doc1["info"]["messages"]["method"]["type"] = "Calculation method";
	$doc1["info"]["messages"]["method"]["url"] = $msg006;
	if ($statuscode == 1)
		$doc1["info"]["messages"]["warning"] = $msg001;
	$doc1["coordinates"] = $coors;
	$doc1["srs"] = $srs;
	$doc1["precision"] = $precision;

	// Merge Arrays
	$doc = array_merge($doc1, $doc2);
} else {
	if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if ($lang == "es")
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/es/api-rest/perfiles-topograficos";
	else if ($lang == "en")
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/en/rest-api/topographic-profiles";
	else
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/eu/rest-apia/profil-topografikoak";
	$doc["info"]["help"] = "Documentation";
	$doc["info"]["url"] = $base_url;
	if ($messages != "" ) {
		$doc["info"]["statuscode"] = $statuscode;
		$doc["info"]["messages"]["warning"] = $messages;
	}
}

// Output Format (JSON by default)
header('Access-Control-Allow-Origin: *');
if (strtolower($format) == "php" || strtolower($format) == "phps") {
	header("Content-type: text/plain;charset=utf-8");
	print_r($doc);
} else if (strtolower($format) == "text" || strtolower($format) == "txt") {
	foreach ($doc["elevationProfile"] as $val) {
	  echo $val["distance"] . " " . $val["height"] . "\r\n";
	}
} else {
	$jsonres = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
	if (strtolower($format) == "xml") {
		header("Content-type: application/xml;charset=utf-8");
		print_r(json2xml($jsonres));
	} else {
		header("Content-type: application/json;charset=utf-8");
		print_r($jsonres);
	}
}
?>
