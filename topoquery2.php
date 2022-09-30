<?php
// b5m topoquery API
//
// Data comes from the b5m OGC WFS Service
//
//

// Includes
include_once("includes/gipuzkoa_wfs_featuretypes_except.php");
include_once("includes/json2xml.php");

// Requests
if (isset($_REQUEST['lang'])) $lang = $_REQUEST['lang']; else $lang = "";
if (isset($_REQUEST['bbox'])) $bbox = $_REQUEST['bbox']; else $bbox = "";
if (isset($_REQUEST['srs'])) $srs = $_REQUEST['srs']; else $srs = "";
if (isset($_REQUEST['format'])) $format = $_REQUEST['format']; else $format = "";

// Language Coding
if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if ($lang != "eu" && $lang != "es" && $lang != "en") $lang = "en";
if ($lang == "eu") $lang2 = 0; else if ($lang == "es") $lang2 = 1; else $lang2 = 2;

// Variables
$server_name = $_SERVER['SERVER_NAME'];
//$wfs_server = "https://b5m.gipuzkoa.eus/ogc/wfs2/gipuzkoa_wfs";
//$wfs_server = "http://b5mlive1.gipuzkoa.eus/ogc/wfs2/gipuzkoa_wfs";
$wfs_server = "http://b5mdev/ogc/wfs2/gipuzkoa_wfs";
//$wfs_server = "https://" . $server_name . "/ogc/wfs2/gipuzkoa_wfs";
//$wfs_server = $server_name . "/ogc/wfs2/gipuzkoa_wfs";
$wfs_service = "?service=wfs";
$wfs_capab = $wfs_service . "&request=getcapabilities";
$wfs_request = $wfs_service . "&version=2.0.0&request=getFeature&typeNames=";
$wfs_output = "&outputFormat=application/json;%20subtype=geojson";

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

// Collecting featuretype data from getcapabilites request
$url_capab = $wfs_server . $wfs_capab;
$ch1 = curl_init();
curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_URL, $url_capab);
//$wfs_capab_response = xml2json(curl_exec($ch1));
$wfs_capab_response = curl_exec($ch1);
curl_close ($ch1);
$wfs_capab_xml = new SimpleXMLElement($wfs_capab_response);
$i = 0;
foreach ($wfs_capab_xml->FeatureTypeList->FeatureType as $featuretype) {
	 $featuretypes_a[$i]["name"] = $featuretype->Name;
	 $featuretypes_a[$i]["title"] = explode(" / ", $featuretype->Title);
	 $featuretypes_a[$i]["abstract"] = $featuretype->Abstract->__toString();
	 $i++;
}

if ($statuscode == 0) {
	// Data request
	$statuscode = 3;
	$i = 0;
	$j = 0;
	foreach ($featuretypes_a as $val) {
		/*
		echo $featuretypes_a[$i]["name"] . "<br>";
		echo $featuretypes_a[$i]["title"]["0"] . "<br>";
		echo $featuretypes_a[$i]["title"]["1"] . "<br>";
		echo $featuretypes_a[$i]["title"]["2"] . "<br>";
		echo $featuretypes_a[$i]["abstract"] . "<br>";
		*/
		$wfs_typename = $featuretypes_a[$i]["name"];

		// Exceptions
		$except_flag = 0;
		foreach ($featuretypes_except as $val_except) {
			if ($wfs_typename == $val_except) {
				$except_flag = 1;
				break;
			}
		}

		$wfs_bbox = "&bbox=" . $bbox;
		$url_request = $wfs_server . $wfs_request . $wfs_typename . $wfs_bbox . $wfs_output;

		if ($except_flag == 0) {
			// Request
			//$wfs_response = json_decode(file_get_contents($url_request), true);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, $url_request);
			$wfs_response = json_decode(curl_exec($ch), true);
			curl_close ($ch);
			$wfs_response_feat = $wfs_response["features"];
			if(count($wfs_response_feat) > 0) {
				$statuscode = 0;
				$doc2["items"][$j]["item_title"] = $featuretypes_a[$i]["title"][$lang2];
				$doc2["items"][$j]["item_abstract"] = $featuretypes_a[$i]["abstract"];
				$doc2["items"][$j]["item"] = $wfs_response;
				$j++;
			}
		}
		$i++;
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
