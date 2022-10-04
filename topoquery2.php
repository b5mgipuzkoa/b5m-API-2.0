<?php
// b5m topoquery API
//
// Data comes from the b5m OGC WFS Service
//
// Dependencies
// cs2cs from PROJ: https://proj.org/apps/cs2cs.html
//

// Includes
include_once("includes/gipuzkoa_wfs_featuretypes_except.php");
include_once("includes/json2xml.php");

// Requests
if (isset($_REQUEST['lang'])) $lang = $_REQUEST['lang']; else $lang = "";
if (isset($_REQUEST['coors'])) $coors = $_REQUEST['coors']; else $coors = "";
if (isset($_REQUEST['offset'])) $offset = $_REQUEST['offset']; else $offset = "";
if (isset($_REQUEST['srs'])) $srs = $_REQUEST['srs']; else $srs = "";
if (isset($_REQUEST['featuretypes'])) $featuretypes = $_REQUEST['featuretypes']; else $featuretypes = "";
if (isset($_REQUEST['featuretypenames'])) $featuretypenames = $_REQUEST['featuretypenames']; else $featuretypenames = "";
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
$offset_default = 1;
$featuretypes_a = array();

// Messages
$msg001 = "Missing required parameter: coors";
$msg002 = "SRS not supported. It should be EPSG:25830, EPSG:4326 or EPSG:3857 (https://epsg.io)";
$msg003 = "SRS epsg:4326, but invalid latitude or longitude (https://epsg.io)";
$msg004 = "Request type: Featuretypes list";
$msg005 = "No data found";
$msg006 = "Invalid featuretypenames";

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

// Types Request
if (strtolower($featuretypes) == "true")
	$statuscode = 4;

// Coors
if ($coors == "" && $statuscode == 0) {
	$statuscode = 1;
	$messages = $msg001;
}

// Coors array
if ($statuscode == 0) {
	$coors_a = explode(",", $coors);
	$x = $coors_a[0];
	$y = $coors_a[1];
}

// SRS
if ($statuscode == 0) {
	if ($srs == "25830" || $srs == "4326" || $srs =="3857")
		$srs = "epsg:" . $srs;
	if ($srs != "" && strtolower($srs) != "epsg:25830" && strtolower($srs) != "epsg:4326" && strtolower($srs) != "epsg:3857" && $statuscode == 0) {
		$statuscode = 2;
		$messages = $msg002;
	}
	if (strtolower($srs) == "epsg:4326" && (($x <= -180 || $x >= 180) || ($y <= -90 || $y >= 90))) {
		$statuscode = 3;
		$messages = $msg003;
	}
}

// Offset
if ($offset == "") $offset = $offset_default;

// Trying to guess the SRS
if ($srs == "" && $statuscode == 0) {
	if ($x >= -180 && $x <= 180)
		$srs = "epsg:4326";
	else if ($x > 180)
		$srs = "epsg:25830";
	else
		$srs = "epsg:3857";
}

if ($statuscode == 0 || $statuscode == 4) {
	// Collecting featuretype data from getcapabilites request
	$url_capab = $wfs_server . $wfs_capab;
	$ch1 = curl_init();
	curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch1, CURLOPT_URL, $url_capab);
	$wfs_capab_response = curl_exec($ch1);
	curl_close ($ch1);
	$wfs_capab_xml = new SimpleXMLElement($wfs_capab_response);
	$i = 0;

	// Typenames
	if ($featuretypenames != "")
		$featuretypenames_a = explode(",", $featuretypenames);
	foreach ($wfs_capab_xml->FeatureTypeList->FeatureType as $featuretype) {
		// Exceptions
		$featuretype_name = str_replace("ms:", "", $featuretype->Name);
		$except_flag = 0;
		foreach ($featuretypes_except as $val_except) {
			if ($featuretype_name == $val_except) {
				$except_flag = 1;
				break;
			}
		}

		if ($except_flag == 0) {
			$include_flag = 1;
			if ($featuretypenames != "") {
				$include_flag = 0;
				foreach ($featuretypenames_a as $featuretypenames_n) {
					if ($featuretypenames_n == $featuretype_name)
						$include_flag = 1;
				}
			}
			if ($include_flag == 1) {
				$featuretypes_a[$i]["name"] = $featuretype_name;
				$featuretypes_a[$i]["title"] = explode(" / ", $featuretype->Title);
				$featuretypes_a[$i]["abstract"] = $featuretype->Abstract->__toString();
			}
		}
		$i++;
	}
}

// Featuretypes count
if (count($featuretypes_a) == 0)
	$statuscode = 6;

if ($statuscode == 4) {
	// Show gathered featuretypes
	$i = 0;
	foreach ($featuretypes_a as $val) {
		$doc2["featuretypes"][$i]["name"] = str_replace("ms:", "", $val["name"]);
		$doc2["featuretypes"][$i]["title"] = $val["title"][$lang2];
		$doc2["featuretypes"][$i]["abstract"] = $val["abstract"];
		$i++;
	}
}

if ($statuscode == 0 || $statuscode == 6) {
	// SRS related extra parameters
	if (strtolower($srs) == "epsg:4326")
	 $srs_extra="urn:ogc:def:crs:EPSG::4326";
	else if (strtolower($srs) == "epsg:3857")
	 $srs_extra="urn:ogc:def:crs:EPSG::3857";
	else
	 $srs_extra="";

	// BBOX
	if (strtolower($srs) == "epsg:4326") {
		$coors_25830 = shell_exec('echo "' . $x . ' ' . $y . '" | /usr/local/bin/cs2cs -f "%.2f" +init=epsg:4326 +to +init=epsg:25830 2> /dev/null');
		$coors_25830_a1 = explode("	", $coors_25830);
		$coors_25830_a2 = explode(" ", $coors_25830_a1[1]);
		$x1 = $coors_25830_a1[0];
		$y1 = $coors_25830_a2[0];
		$coors_4326_1 = shell_exec('echo "' . $x1 - $offset . ' ' . $y1 - $offset . '" | /usr/local/bin/cs2cs -f "%.6f" +init=epsg:25830 +to +init=epsg:4326 2> /dev/null');
		$coors_4326_2 = shell_exec('echo "' . $x1 + $offset . ' ' . $y1 + $offset . '" | /usr/local/bin/cs2cs -f "%.6f" +init=epsg:25830 +to +init=epsg:4326 2> /dev/null');
		$coors_4326_a11 = explode("	", $coors_4326_1);
		$coors_4326_a12 = explode(" ", $coors_4326_a11[1]);
		$coors_4326_a21 = explode("	", $coors_4326_2);
		$coors_4326_a22 = explode(" ", $coors_4326_a21[1]);
		$bbox = $coors_4326_a12[0] . "," . $coors_4326_a11[0] . "," . $coors_4326_a22[0] . "," . $coors_4326_a21[0];
	} else {
		$bbox = $x - ($offset / 2) . "," . $y - ($offset / 2) . "," . $x + ($offset / 2) . "," . $y + ($offset / 2);
	}
	if ($srs_extra != "") {
		$bbox2 = $bbox. "," . $srs_extra;
		$wfs_srsname = "&srsname=" . $srs;
	} else {
		$bbox2 = $bbox;
		$wfs_srsname = "";
	}

	// Data request
	if ($statuscode != 6)
		$statuscode = 5;
	$i = 0;
	foreach ($featuretypes_a as $val) {
		$wfs_typename = $val["name"];
		$wfs_bbox = "&bbox=" . $bbox2;
		$url_request = $wfs_server . $wfs_request . $wfs_typename . $wfs_bbox . $wfs_output . $wfs_srsname;

		// Request
		//$wfs_response = json_decode(file_get_contents($url_request), true);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url_request);
		$wfs_response = json_decode(curl_exec($ch), true);
		curl_close ($ch);
		$wfs_response_feat = $wfs_response["features"];
		if (count($wfs_response_feat) > 0) {
			$statuscode = 0;
			$doc2["items"][$i]["item_name"] = $val["name"];
			$doc2["items"][$i]["item_title"] = $val["title"][$lang2];
			$doc2["items"][$i]["item_abstract"] = $val["abstract"];
			$doc2["items"][$i]["item"] = $wfs_response;
			$i++;
		}
	}
}

if ($statuscode == 0 || $statuscode == 4 || $statuscode == 5 || $statuscode == 6) {
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
	if ($statuscode == 4)
		$doc1["info"]["messages"]["request"] = $msg004;
	if ($statuscode == 5)
		$doc1["info"]["messages"]["warning"] = $msg005;
	if ($statuscode == 6)
		$doc1["info"]["messages"]["warning"] = $msg006;
	if ($statuscode != 4) {
		$doc1["coors"] = $coors;
		$doc1["offset"] = $offset;
		$doc1["bbox"] = $bbox;
		$doc1["srs"] = $srs;
		if ($featuretypenames != "")
			$doc1["featuretypenames"] = $featuretypenames;
	}

	// Merge Arrays
	if ($statuscode == 5 || $statuscode == 6) {
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
