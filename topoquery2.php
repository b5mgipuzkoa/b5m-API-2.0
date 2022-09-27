<?php
// b5m topoquery API
//
// Data comes from the b5m OGC WFS Service
//
//

// Requests
if (isset($_REQUEST['bbox'])) $bbox = $_REQUEST['bbox']; else $bbox = "";
if (isset($_REQUEST['srs'])) $srs = $_REQUEST['srs']; else $srs = "";
if (isset($_REQUEST['format'])) $format = $_REQUEST['format']; else $format = "";

// Includes
include_once("./includes/json2xml.php");

// Variables
$server_name = $_SERVER['SERVER_NAME'];
//$wfs_server = "https://b5m.gipuzkoa.eus/ogc/wfs2/gipuzkoa_wfs";
//$wfs_server = "http://b5mlive1.gipuzkoa.eus/ogc/wfs2/gipuzkoa_wfs";
$wfs_server = "http://b5mdev/ogc/wfs2/gipuzkoa_wfs";
//$wfs_server = "https://" . $server_name . "/ogc/wfs2/gipuzkoa_wfs";
//$wfs_server = $server_name . "/ogc/wfs2/gipuzkoa_wfs";
$wfs_request = "?service=wfs&version=2.0.0&request=getFeature&typeNames=ms:";
$wfs_output = "&outputFormat=application/json;%20subtype=geojson";

// Query types array
$types_a = array("m_municipalities", "d_postaladdresses");

// Messages
$msg001 = "Missing required parameter: bbox";
$msg002 = "SRS not supported. It should be: EPSG:25830, EPSG:4326 or EPSG:3857 (https://epsg.io)";
$msg003 = "No data found";

// License and metadata variables
$year = date("Y");
$provider = "b5m - Gipuzkoa Spatial Data Infrastructure - Gipuzkoa Provincial Council - " .$year;
$url_license = "https://" . $server_name . "/web5000/en/legal-information";
$url_base = "https://" . $server_name . "/web5000";
$image_url = "https://" . $server_name . "/web5000/assets/img/logo-b5m.svg";
$response_time_units = "seconds";
$messages = "";

// Init statuscode and time
$statuscode = 0;
$init_time = microtime(true);

// Checking Requests
if ($bbox == "" && $srs == "" && $format == "")
	$statuscode = -1;

// Bbox
if ($bbox == "" && $statuscode == 0) {
	$statuscode = 1;
	$messages = $msg001;
}

// SRS
if ($srs != "" && strtolower($srs) != "epsg:25830" && strtolower($srs) != "epsg:4326" && strtolower($srs) != "epsg:3857" && $statuscode == 0) {
	$statuscode = 3;
	$messages = $msg003;
}

// BBOX array
if ($statuscode == 0) {
	$bbox_array = explode(",", $bbox);
}

// Trying to guess the SRS
if ($srs == "" && $statuscode == 0) {
	if ($bbox_array[0] >= -90 && $bbox_array[0] <= 90)
		$srs = "epsg:4326";
	else if ($bbox_array[0] > 90)
		$srs = "epsg:25830";
	else
		$srs = "epsg:3857";
}

if ($statuscode == 0) {
	// Data request
	$statuscode = 3;
	$i = 0;
	foreach ($types_a as $val) {
		$wfs_typename = $val;
		$wfs_bbox = "&bbox=" . $bbox;
		$url_request = $wfs_server . $wfs_request . $wfs_typename . $wfs_bbox . $wfs_output;

		// Request
		//$wfs_response = json_decode(file_get_contents($url_request), true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url_request);
		$wfs_response = json_decode(curl_exec($ch), true);
		curl_close ($ch);
		$wfs_response_feat = $wfs_response["features"];
		if(count($wfs_response_feat) == 0) {
			continue;
		} else {
			$statuscode = 0;
			$doc2["items"][$i]["item_name"] = $val;
			$doc2["items"][$i]["item"] = $wfs_response;
			$i++;
		}
	}
}

if ($statuscode == 0 || $statuscode == 3) {
	// Data license
	$final_time = microtime(true);
	$response_time = sprintf("%.2f", $final_time - $init_time);
	$doc1["info"]["license"]["provider"] = $provider;
	$doc1["info"]["license"]["urlLicense"] = $url_license;
	$doc1["info"]["license"]["urlBase"] = $url_base;
	$doc1["info"]["license"]["imageUrl"] = $image_url;
	$doc1["info"]["responseTime"]["time"] = $response_time;
	$doc1["info"]["responseTime"]["units"] = $response_time_units;
	$doc1["info"]["statuscode"] = $statuscode;
	if ($statuscode == 3)
		$doc1["info"]["messages"]["warning"] = $msg003;
	$doc1["bbox"] = $bbox;
	$doc1["srs"] = $srs;

	// Merge Arrays
	if ($statuscode == 3 ) {
		$doc = $doc1;
	} else {
		$doc = array_merge($doc1, $doc2);
	}
} else {
	if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if ($lang == "es")
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/es/api-rest";
	else if ($lang == "en")
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/en/rest-api";
	else
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/eu/rest-apia";
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
