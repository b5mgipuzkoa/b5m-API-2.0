<?php
// b5m topoquery API
//
// Data comes from the b5m OGC WFS Service
//
// Dependencies
// cs2cs from PROJ: https://proj.org/apps/cs2cs.html
//

// Memory
ini_set("memory_limit", "200M");

// Includes
include_once("./includes/gipuzkoa_wfs_featuretypes_except.php");
include_once("./includes/json2xml.php");
$file_json = "./json/topoquery2_zoom.json";

// Requests
if (isset($_REQUEST['lang'])) $lang = $_REQUEST['lang']; else $lang = "";
if (isset($_REQUEST['b5mcode'])) $b5m_code = $_REQUEST['b5mcode']; else $b5m_code = "";
if (isset($_REQUEST['coors'])) $coors = $_REQUEST['coors']; else $coors = "";
if (isset($_REQUEST['bbox'])) $coors = $_REQUEST['bbox'];
if (isset($_REQUEST['z'])) $z = $_REQUEST['z']; else $z = 10;
if (isset($_REQUEST['scale'])) $scale = $_REQUEST['scale']; else $scale = "";
if (isset($_REQUEST['offset'])) $offset = $_REQUEST['offset']; else $offset = "";
if (isset($_REQUEST['srs'])) $srs = $_REQUEST['srs']; else $srs = "";
if (isset($_REQUEST['geom'])) $geom = $_REQUEST['geom']; else $geom = "";
if (isset($_REQUEST['featuretypes'])) $featuretypes = $_REQUEST['featuretypes']; else $featuretypes = "";
if (isset($_REQUEST['featuretypenames'])) $featuretypenames = $_REQUEST['featuretypenames']; else $featuretypenames = "";
if (isset($_REQUEST['format'])) $format = $_REQUEST['format']; else $format = "";
if (isset($_REQUEST['debug'])) $debug = $_REQUEST['debug']; else $debug = 0;
if ($featuretypes != "") $z = "";

// Language Coding
if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if ($lang != "eu" && $lang != "es" && $lang != "en") $lang = "en";
if ($lang == "eu") $lang2 = 0; else if ($lang == "es") $lang2 = 1; else $lang2 = 2;

// Variables
$b5m_server = "https://" . $_SERVER['SERVER_NAME'];
$wfs_server = $b5m_server . "/ogc/wfs2/gipuzkoa_wfs";
$wfs_service = "?service=wfs";
//$wfs_valueref_arr = array("name_eu", "name_es");
//$wfs_valueref = "WFSVALUEREFVALUE";
$wfs_capab = $wfs_service . "&request=getcapabilities";
$wfs_feature = $wfs_service . "&version=1.1.0&request=describefeaturetype&typename=";
$wfs_request1 = $wfs_service . "&version=2.0.0&request=getFeature&typeNames=";
//$wfs_request2 = $wfs_service . "&version=2.0.0&request=getPropertyValue&valueReference=" . $wfs_valueref . "&typeNames=";
$wfs_request3 = "?request=GetMetadata&layer=";
$wfs_output = "&outputFormat=application/json;%20subtype=geojson";
$b5m_code_filter="B5MCODEFILTER";
$wfs_filter_base = "&filter=<Filter><PropertyIsEqualTo><PropertyName>b5mcode</PropertyName><Literal>" . $b5m_code_filter . "</Literal></PropertyIsEqualTo></Filter>";
$offset_default = 1;
$offset_units = "metres";
$offset_v = "";
$bbox = "";
$bbox_default = "";
$featuretypes_a = array();
$d_addr = "d_postaladdresses";
$doc1 = array();
$doc2 = array();
$doc3 = array();
$time_deb = array();
$time_n = 0;
$time_t = 0;
$more_info_a = array();
$url_request1 = null;
$wfs_typename_dw = "dw_download";
$max_area = 100;
$x1 = "";
$y1 = "";
$x2 = "";
$y2 = "";

// Variable Links
$b5map_link["eu"] = $b5m_server . "/b5map/r1/eu/mapa/lekutu/";
$b5map_link["es"] = $b5m_server . "/b5map/r1/es/mapa/localizar/";
$b5map_link["en"] = $b5m_server . "/b5map/r1/en/map/locate/";

// Functions
function get_time($time_i, $url_r) {
	// Get time of a process
	global $time_n, $time_t, $time_deb;
	$time_deb[$time_n]["url"] = $url_r;
	$time_p = sprintf("%.2f", microtime(true) - $time_i);
	$time_deb[$time_n]["time"] = sprintf("%.2f", $time_p);
	$time_t = sprintf("%.2f", $time_t + $time_p);
	$time_n++;
}
function get_url_info($url) {
	// Get information from an URL
	if ($_SERVER['SERVER_NAME'] == "b5m.gipuzkoa.eus") {
		$ssl_check = true;
		$proxy_tunnel = true;
		$proxy_server = "http://proxy.sare.gipuzkoa.net";
		$proxy_port = "8080";
	} else {
		$ssl_check = false;
		$proxy_tunnel = false;
		$proxy_server = "";
		$proxy_port = "";
	}
	$options = array(
		CURLOPT_RETURNTRANSFER => true,     	// return web page
		CURLOPT_HEADER         => false,    	// don't return headers
		CURLOPT_FOLLOWLOCATION => true,     	// follow redirects
		CURLOPT_ENCODING       => "",       	// handle all encodings
		CURLOPT_USERAGENT      => basename(__FILE__, '.php'), // who am i
		CURLOPT_AUTOREFERER    => true,     	// set referer on redirect
		CURLOPT_CONNECTTIMEOUT => 120,      	// timeout on connect
		CURLOPT_TIMEOUT        => 120,      	// timeout on response
		CURLOPT_MAXREDIRS      => 10,      		// stop after 10 redirects
		CURLOPT_SSL_VERIFYHOST => $ssl_check,	// SSL Cert checks
		CURLOPT_SSL_VERIFYPEER => $ssl_check,	// SSL Cert checks
		CURLOPT_HTTPPROXYTUNNEL => $proxy_tunnel,
		CURLOPT_PROXY => $proxy_server,
		CURLOPT_PROXYPORT => $proxy_port,
	);

	$url = str_replace("b5mdev1", "b5mdev", $url);
	$ch = curl_init($url);
	curl_setopt_array($ch, $options);
	$content = curl_exec($ch);
	$err = curl_errno($ch);
	$errmsg = curl_error($ch);
	$header = curl_getinfo($ch);
	curl_close($ch);

	$header['errno'] = $err;
	$header['errmsg'] = $errmsg;
	$header['content'] = $content;
	return $header;
}

function get_feat_info($featuretype_name) {
	// Collecting field list data from getfeature request
	$url_field = $GLOBALS['wfs_server'] . $GLOBALS['wfs_feature'] . $featuretype_name;
	$wfs_field_response = (get_url_info($url_field)['content']);
	$wfs_field_xml = new SimpleXMLElement($wfs_field_response);
	$j = 0;
	foreach ($wfs_field_xml->complexType->complexContent->extension->sequence->element as $featuretypefield) {
		$fieldname = strval($featuretypefield["name"]);
		if ($fieldname != "msGeometry") {
			$featuretypes_info_a[$j] = $fieldname;
			$j++;
		}
	}
	return $featuretypes_info_a;
}

function get_dw_list() {
	// Get download list
	global $wfs_server, $wfs_request1, $wfs_bbox, $wfs_srsname, $wfs_filter, $wfs_output, $wfs_typename_dw, $time1, $time3;
	$url_request_dw = $wfs_server . $wfs_request1 . $wfs_typename_dw . $wfs_bbox . $wfs_srsname . $wfs_filter . $wfs_output;
	$time_i = microtime(true);
	$wfs_response_dw = json_decode((get_url_info($url_request_dw)['content']), true);
	get_time($time_i, $url_request_dw);
	return $wfs_response_dw;
}

function rep_key_a($rep_a, $key1, $key2) {
	// Replace a key in an array
	$rep_a = str_replace($key1, $key2, json_encode($rep_a));
	$rep_a = json_decode($rep_a, true);
	return $rep_a;
}

function tidy_dw($tidy_a, $tidy_l, $tidy_i) {
	// Tidy download keys
	$tidy_a = rep_key_a($tidy_a, "name_grid_eu", "name_grid");
	$tidy_a = rep_key_a($tidy_a, "type_grid_" . $tidy_l, "type_grid");
	$tidy_a = rep_key_a($tidy_a, "name_" . $tidy_l, "name");
	unset($tidy_a["properties"]["b5mcode"]);
	unset($tidy_a["properties"]["name_grid_es"]);
	unset($tidy_a["properties"]["type_grid_eu"]);
	unset($tidy_a["properties"]["type_grid_es"]);
	unset($tidy_a["properties"]["type_grid_en"]);
	$i_dw = 0;
	foreach ($tidy_a["properties"]["types_dw"] as $types_dw) {
		unset($tidy_a["properties"]["types_dw"][$i_dw]["name_eu"]);
		unset($tidy_a["properties"]["types_dw"][$i_dw]["name_es"]);
		unset($tidy_a["properties"]["types_dw"][$i_dw]["name_en"]);
		$i_dw++;
	}
	return $tidy_a;
}

function cs2cs($c1, $c2, $fl, $srs1, $srs2) {
	$coors_c_01 = shell_exec('echo "' . $c1 . ' ' . $c2 . '" | /usr/local/bin/cs2cs -f "%.' . $fl . 'f" +init="' . $srs1 . '" +to +init="' .$srs2 . '" 2> /dev/null');
	$coors_c_02 = explode("	", $coors_c_01);
	$coors_c_03 = explode(" ", $coors_c_02[1]);
	return array($coors_c_02[0], $coors_c_03[0]);
}

function get_bbox($x1, $y1, $x2, $y2, $srs, $offset, $statuscode, $srs_extra) {
	// BBOX
	$wfs_srsname = "&srsname=" . $srs;
	if ($x2 == "") {
		if (strtolower($srs) == "epsg:4326") {
			$coors_25830 = cs2cs($x1, $y1, 2, $srs, "epsg:25830");
			$x11 = $coors_25830[0];
			$y11 = $coors_25830[1];
			if (is_numeric($x1) && is_numeric($y1))
				$statuscode = $statuscode;
			else
				$statuscode = 8;
			if ($x11 == "*" || $statuscode == 8) {
				// Out of range
				$statuscode = 8;
			} else {
				$coors_4326_1 = cs2cs($x11 - $offset, $y11 - $offset, 6, "epsg:25830", $srs);
				$coors_4326_2 = cs2cs($x11 + $offset, $y11 + $offset, 6, "epsg:25830", $srs);
				$bbox = $coors_4326_1[1] . "," . $coors_4326_1[0] . "," . $coors_4326_2[1] . "," . $coors_4326_2[0];
			}
		} else {
			$bbox = $x1 - ($offset / 2) . "," . $y1 - ($offset / 2) . "," . $x1 + ($offset / 2) . "," . $y1 + ($offset / 2);
		}
		if ($srs_extra != "") {
			$bbox2 = $bbox. "," . $srs_extra;
		} else {
			$bbox2 = $bbox;
			$wfs_srsname = "";
		}
	} else {
		if (strtolower($srs) == "epsg:4326")
			$bbox = $y1 . "," . $x1 . "," . $y2 . "," . $x2;
		else
			$bbox = $x1 . "," . $y1 . "," . $x2 . "," . $y2;
		$bbox2 = $bbox. "," . $srs_extra;
	}
	return $bbox2 . "|" . $wfs_srsname . "|" . $statuscode;
}

function get_25830($coors, $srs) {
	// Get epsg:25830 coordinates
	if (strtolower($srs) != "epsg:25830") {
		$coors_2 = explode(",", $coors);
		$coors_25830_1 = cs2cs($coors_2[0], $coors_2[1], 2, $srs, "epsg:25830");
		if (count($coors_2) == 4) {
			$coors_25830_2 = cs2cs($coors_2[2], $coors_2[3], 2, $srs, "epsg:25830");
			return $coors_25830_1[0] . "," . $coors_25830_1[1] . "," . $coors_25830_2[0] . "," . $coors_25830_2[1];
		} else {
			return $coors_25830_1[0] . "," . $coors_25830_1[1];
		}
	} else {
		return $coors;
	}
}

function area_calc($x1, $y1, $x2, $y2, $srs) {
	// Area calculation
	if (strtolower($srs) == "epsg:4326" || strtolower($srs) == "epsg:3857") {
		$coors_1 = cs2cs($x1, $y1, 0, $srs, "epsg:25830");
		$coors_2 = cs2cs($x2, $y2, 0, $srs, "epsg:25830");
		$x1 = $coors_1[0];
		$y1 = $coors_1[1];
		$x2 = $coors_2[0];
		$y2 = $coors_2[1];
	}
	$area_c = (($x2 - $x1) * ($y2 - $y1)) / 1000000;
	if ($area_c <= 0 || $area_c >= 9999)
	 	$area_c = 9999;
	return $area_c;
}

// Messages
$msg000 = "None";
$msg001 = "Missing required parameter: coors";
$msg002 = "SRS not supported. It should be EPSG:25830, EPSG:4326 or EPSG:3857 (https://epsg.io)";
$msg003 = "SRS epsg:4326, but invalid latitude or longitude (https://epsg.io)";
$msg004 = "Request type: Featuretypes list";
$msg005 = "No data found";
$msg006 = "Invalid featuretypenames";
$msg008 = "Out of range";
$msg009 = "Out of area range";

// License and metadata variables
$year = date("Y");
$provider = "b5m - Gipuzkoa Spatial Data Infrastructure - Gipuzkoa Provincial Council - " .$year;
$url_license = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/en/legal-information";
$url_base = "https://" . $_SERVER['SERVER_NAME'] . "/web5000";
$image_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/assets/img/logo-b5m.svg";
$response_time_units = "seconds";
$messages = "";

// Init statuscode and time
$statuscode = 0;
$init_time = microtime(true);

// Id Request
if ($b5m_code != "") {
	$statuscode = 7;
	$z = "";
	$b5m_code_type = strtolower(explode("_", $b5m_code)[0]);
}

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
	$x1 = $coors_a[0];
	$y1 = $coors_a[1];
	if (count($coors_a) > 2) {
		$x2 = $coors_a[2];
		$y2 = $coors_a[3];
	} else {
		$x2 = "";
		$y2 = "";
	}
}

// Zoom and scale
if (is_numeric($z)) {
	if ($z != "") {
		if ($z < 9) $z = 9; else if ($z > 19) $z = 19;
	}
} else {
	$z = "";
}
if ($z == "" && $scale != "") {
	if (is_numeric($scale)) {
		if ($scale > 600000) $z = 9;
		else if ($scale <= 600000 && $scale > 300000) $z = 10;
		else if ($scale <= 300000 && $scale > 150000) $z = 11;
		else if ($scale <= 150000 && $scale > 75000) $z = 12;
		else if ($scale <= 75000 && $scale > 37500) $z = 13;
		else if ($scale <= 37500 && $scale > 18750) $z = 14;
		else if ($scale <= 18750 && $scale > 9375) $z = 15;
		else if ($scale <= 9375 && $scale > 4687) $z = 16;
		else if ($scale <= 4687 && $scale > 2343) $z = 17;
		else if ($scale <= 2343 && $scale > 1172) $z = 18;
		else $z = 19;
	} else {
		$scale = "";
	}
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
$offset_ori = $offset;
if ($offset == "") $offset = $offset_default;
if ($statuscode == 7) $offset = "";

// Trying to guess the SRS
if ($srs == "" && $statuscode == 0) {
	if ($x1 >= -180 && $x1 <= 180)
		$srs = "epsg:4326";
	else if ($x1 > 180)
		$srs = "epsg:25830";
	else
		$srs = "epsg:3857";
} else if ($srs == "" && $statuscode == 7) {
		$srs = "epsg:25830";
}

// Area if it is a bounding box
if ($x2 != "" && $featuretypenames != "dw_download") {
	$bbox_area = area_calc($x1, $y1, $x2, $y2, $srs);
	if ($bbox_area > $max_area) {
		$statuscode = 9;
		$msg009 = $msg009 . ". Current area range is " . number_format($bbox_area, 2) . " km2. Maximum area range is " . number_format($max_area, 2) . " km2.";
	}
}

if ($statuscode == 0 || $statuscode == 4 || $statuscode == 7) {
	// Collecting featuretype data from getcapabilites request
	$url_capab = $wfs_server . $wfs_capab;
	$time_i = microtime(true);
	$wfs_capab_response = (get_url_info($url_capab)['content']);
	get_time($time_i, $url_capab);
	$wfs_capab_xml = new SimpleXMLElement($wfs_capab_response);
	$i = 0;

	// Typenames
	foreach ($wfs_capab_xml->FeatureTypeList->FeatureType as $featuretype) {
		$except_flag = 0;
		$featuretype_name = str_replace("ms:", "", $featuretype->Name);
		$featuretype_desc =	explode(" / ", $featuretype->Title);
		$featuretype_abstract = $featuretype->Abstract->__toString();
		if ($featuretype_name == $d_addr) {
			$d_addr_des = $featuretype_desc;
			$d_addr_abs = $featuretype_abstract;
		}
		if ($statuscode == 7) {
			// Id Request
			if ($b5m_code_type == strtolower(explode("_", explode(":", $featuretype->Name->__toString())[1])[0])) {
				$featuretypes_a[0]["featuretypename"] = $featuretype_name;
				$featuretypes_a[0]["description"] = $featuretype_desc;
				$featuretypes_a[0]["abstract"] = $featuretype_abstract;
			}
		} else {
			// Exceptions
			foreach ($featuretypes_except as $val_except) {
				if ($featuretype_name == $val_except) {
					$except_flag = 1;
					break;
				}
			}
		}

		if ($except_flag == 0 && $statuscode != 7) {
			$featuretypes_a[$i]["featuretypename"] = $featuretype_name;
			$featuretypes_a[$i]["description"] = $featuretype_desc;
			$featuretypes_a[$i]["abstract"] = $featuretype_abstract;

			if ($featuretypenames == "" && $statuscode == 4)
				$featuretypes_a[$i]["properties"] = get_feat_info($featuretype_name);
		}
		$i++;
	}
}

// Zoom restriction
if (($z != "" || $featuretypenames != "") && ($statuscode != 3) && ($statuscode != 9)) {
	$data_json = file_get_contents($file_json);
	$zoom_array = json_decode($data_json);
	foreach ($zoom_array as $obj) {
		if ($obj->zoom == $z) {
			$featuretypenames_a = $obj->featuretypenames;
			$offset_v = $obj->offset;
		}
	}
	if ($featuretypenames != "")
		$featuretypenames_a = explode(",", $featuretypenames);
	$i=0;
	foreach ($featuretypenames_a as $featuretypenames_n) {
		$j=0;
		foreach ($featuretypes_a as $featuretypes_n) {
			if ($featuretypenames_n == $featuretypes_n["featuretypename"]) {
				$featuretypes_a2[$i] = $featuretypes_a[$j];
				if ($statuscode == 4)
					$featuretypes_a2[$i]["properties"] = get_feat_info($featuretypenames_n);
				$i++;
			}
			$j++;
		}
	}
	$featuretypes_a = $featuretypes_a2;
}

// Featuretypes count
if (count($featuretypes_a) == 0 && $statuscode != 3 && $statuscode != 7 && $statuscode != 9)
	$statuscode = 6;

if ($statuscode == 4) {
	// Show gathered featuretypes
	$i = 0;
	foreach ($featuretypes_a as $val) {
		$doc2["featuretypes"][$i]["featuretypename"] = str_replace("ms:", "", $val["featuretypename"]);
		$doc2["featuretypes"][$i]["description"] = $val["description"][$lang2];
		$doc2["featuretypes"][$i]["abstract"] = $val["abstract"];
		$doc2["featuretypes"][$i]["properties"] = $val["properties"];
		$i++;
	}
}

if ($statuscode == 0 || $statuscode == 7 || $statuscode == 9) {
	// SRS related extra parameters
	if (strtolower($srs) == "epsg:25830")
	 $srs_extra = "urn:ogc:def:crs:EPSG::25830";
	else if (strtolower($srs) == "epsg:4326")
	 $srs_extra = "urn:ogc:def:crs:EPSG::4326";
	else if (strtolower($srs) == "epsg:3857")
	 $srs_extra = "urn:ogc:def:crs:EPSG::3857";
	else
	 $srs_extra = "";

	if ($statuscode != 7) {
		// BBOX
		$bbox_s = get_bbox($x1, $y1, $x2, $y2, $srs, $offset, $statuscode, $srs_extra);
		$bbox_a = explode("|", $bbox_s);
		$bbox_default = $bbox_a[0];
		$bbox2 = $bbox_default;
		$wfs_srsname = $bbox_a[1];
		$statuscode = $bbox_a[2];
	} else {
		if ($srs != "")
			$wfs_srsname = "&srsname=" . $srs;
		else
			$wfs_srsname = "";
	}

	// Data request
	if ($statuscode != 8 && $statuscode != 9) {
		if ($statuscode != 7)
			$statuscode = 5;
		$i = 0;
		$j = 0;
		foreach ($featuretypes_a as $val) {
			$wfs_typename = $val["featuretypename"];
			if ($offset_ori == "" && $statuscode == 5) {
				// New offset if feature's geometry is curve or point
				$url_request_md = $wfs_server . $wfs_request3 . $val["featuretypename"];
				$time_i = microtime(true);
				$wfs_response_md = (get_url_info($url_request_md)['content']);
				get_time($time_i, $url_request_md);
				$wfs_md_xml = new SimpleXMLElement($wfs_response_md);
				$md_ns = $wfs_md_xml->getNamespaces(true);
				$md_child = $wfs_md_xml->children($md_ns["gmd"]);
				if ($md_child->spatialRepresentationInfo->MD_VectorSpatialRepresentation->geometricObjects->MD_GeometricObjects->geometricObjectType->MD_GeometricObjectTypeCode != "surface" && $statuscode == 5) {
					$bbox_s = get_bbox($x1, $y1, $x2, $y2, $srs, $offset_v, $statuscode, $srs_extra);
					$bbox_a = explode("|", $bbox_s);
					$bbox2 = $bbox_a[0];
				} else {
					$bbox2 = $bbox_default;
				}
			}
			if ($statuscode != 7) {
				$wfs_bbox = "&bbox=" . $bbox2;
				$wfs_filter = "";
			} else {
				$wfs_bbox = "";
				$wfs_filter = str_replace($b5m_code_filter, $b5m_code, $wfs_filter_base);
			}
			$url_request = $wfs_server . $wfs_request1 . $wfs_typename . $wfs_bbox . $wfs_srsname . $wfs_filter . $wfs_output;

			// Request
			if (count($featuretypes_a) > 0) {
				if ($i == 0) {
					$url_request1 = $url_request;
					$time_i = microtime(true);
					$wfs_response = json_decode((get_url_info($url_request1)['content']), true);
					get_time($time_i, $url_request1);
					$wfs_response_feat = $wfs_response["features"];
					$wfs_response_count = count($wfs_response_feat);
					if ($wfs_response_count == 0)
						$i = -1;
				} else {
					$wfs_response_count = 0;
					$more_info_a[$i-1]["featuretypename"] = $val["featuretypename"];
					$more_info_a[$i-1]["description"] = $val["description"][$lang2];
					$more_info_a[$i-1]["abstract"] = $val["abstract"];
				}
			} else {
				$wfs_response_count = 0;
			}
			if ($wfs_response_count > 0) {
				if ($statuscode != 7)
					$statuscode = 0;
				if ($i == 0) {
					if ($val["featuretypename"] == "e_buildings") {
						// e_buildings case, postal addresses nested

						// Listing building areas
						foreach ($wfs_response["features"] as $q1 => $r1) {
							$type_e_a[$q1] = $r1["properties"]["b5mcode"];
						}
						$type_e_a = array_unique($type_e_a);
						$t = 0;
						foreach ($type_e_a as $q0 => $r0) {
							$doc2["features"][$t]["type"] = $wfs_response["features"][$q0]["type"];
							$doc2["features"][$t]["featuretypename"] = $val["featuretypename"];
							$doc2["features"][$t]["description"] = $val["description"][$lang2];
							$doc2["features"][$t]["abstract"] = $val["abstract"];
							$b5m_code_e = $wfs_response["features"][$q0]["properties"]["b5mcode"];
							$doc2["features"][$t]["properties"]["b5mcode"] = $b5m_code_e;
							$doc2["features"][$t]["properties"]["b5maplink"] = $b5map_link[$lang] . $b5m_code_e;
							$u = 0;
							$w = 0;
							foreach ($wfs_response["features"] as $q1 => $r1) {
								if ($r0 == $r1["properties"]["b5mcode"]) {
									if ($w == 0)
										$u = 0;
									$w++;
									$ot1 = 0;
									foreach ($r1["properties"] as $q2 => $r2) {
										$doc2["features"][$t]["properties"]["info"][$u]["featuretypename"] = $d_addr;
										$doc2["features"][$t]["properties"]["info"][$u]["description"] = $d_addr_des[$lang2];
										$doc2["features"][$t]["properties"]["info"][$u]["abstract"] = $d_addr_abs;
										if ($q2 != "idname" && $q2 != "b5mcode" && $q2 != "type_eu" && $q2 != "type_es" && $q2 != "type_en" && stripos($q2, "b5mcode_others") === false) {
											if ($q2 == "b5mcode2")
												$q2 = "b5mcode";
											$doc2["features"][$t]["properties"]["info"][$u]["properties"][$q2] = $r2;
										}

										// More info
										if(stripos($q2, "b5mcode_others") !== false && strlen($q2) < 18) {
											$b5mcode_others = $wfs_response["features"][$q1]["properties"][$q2];
											$b5mcode_others_name_eu = $wfs_response["features"][$q1]["properties"][$q2 . "_name_eu"];
											$b5mcode_others_name_es = $wfs_response["features"][$q1]["properties"][$q2 . "_name_es"];
											$b5m_code_type2 = strtolower(substr($b5mcode_others, 0, 1));
											foreach ($wfs_capab_xml->FeatureTypeList->FeatureType as $featuretype) {
												if ($b5m_code_type2 == strtolower(explode("_", explode(":", $featuretype->Name->__toString())[1])[0])) {
													$featuretype_name = str_replace("ms:", "", $featuretype->Name);
													$featuretype_desc =	explode(" / ", $featuretype->Title);
													$featuretype_abstract = $featuretype->Abstract->__toString();
													$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["featuretypename"] = $featuretype_name;
													$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["description"] = $featuretype_desc[$lang2];
													$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["abstract"] = $featuretype_abstract;
													$b5mcode_others_a = explode("|", $b5mcode_others);
													$b5mcode_others_name_eu_a = explode("|", $b5mcode_others_name_eu);
													$b5mcode_others_name_es_a = explode("|", $b5mcode_others_name_es);
													$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["numberMatched"] = count($b5mcode_others_a);
													$ot2 = 0;
													foreach ($b5mcode_others_a as $b5mcode_others_i) {
														$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["features"][$ot2]["b5mcode"] = $b5mcode_others_i;
														$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["features"][$ot2]["name_eu"] = $b5mcode_others_name_eu_a[$ot2];
														$doc2["features"][$q0]["properties"]["info"][$u]["more_info"][$ot1]["features"][$ot2]["name_es"] = $b5mcode_others_name_es_a[$ot2];
														$ot2++;
													}
												}
											}
											$ot1++;
										}
										// End more info
									}
								}
								$u++;
							}
							if ($geom != "false")
								$doc2["features"][$t]["geometry"] = $wfs_response["features"][$q0]["geometry"];
							$t++;
						}

						// Downloads
						if ($statuscode != "7") {
							$wfs_response_dw = get_dw_list();
							foreach ($wfs_response_dw["features"] as $q1_dw => $r1_dw) {
								$r1_dw = tidy_dw($r1_dw, $lang, $q1_dw);
								$doc2["downloads"][$q1_dw] = [$r1_dw][0]["properties"];
							}
						}
					} else {
						foreach ($wfs_response["features"] as $q1 => $r1) {
							$doc2["features"][$q1]["type"] = $wfs_response["features"][$q1]["type"];
							$doc2["features"][$q1]["featuretypename"] = $val["featuretypename"];
							$doc2["features"][$q1]["description"] = $val["description"][$lang2];
							$doc2["features"][$q1]["abstract"] = $val["abstract"];
							$ot1 = 0;
							foreach ($r1["properties"] as $q2 => $r2) {
								if ($q2 == "b5mcode") {
									$doc2["features"][$q1]["properties"][$q2] = $wfs_response["features"][$q1]["properties"][$q2];
									$doc2["features"][$q1]["properties"]["b5maplink"] = $b5map_link[$lang] . $wfs_response["features"][$q1]["properties"][$q2];
									$doc2["features"][$q1]["properties"]["info"][0][$q2 . "2"] = $wfs_response["features"][$q1]["properties"][$q2];
								} else {
									if ($q2 != "idname" && $q2 != "type_eu" && $q2 != "type_es" && $q2 != "type_en" && $q2 != "type_description_eu" && $q2 != "type_description_es" && $q2 != "type_description_en" && $q2 != "category_eu" && $q2 != "category_es" && $q2 != "category_en" && $q2 != "category_description_eu" && $q2 != "category_description_es" && $q2 != "category_description_en" && stripos($q2, "b5mcode_others") === false)
										$doc2["features"][$q1]["properties"]["info"][0][$q2] = $wfs_response["features"][$q1]["properties"][$q2];

										// Type and Category
										if ($q2 == "type_" . $lang)
											$doc2["features"][$q1]["properties"]["info"][0]["type"] = $wfs_response["features"][$q1]["properties"][$q2];
										if ($q2 == "type_description_" . $lang)
											$doc2["features"][$q1]["properties"]["info"][0]["type_description"] = $wfs_response["features"][$q1]["properties"][$q2];
										if ($q2 == "category_" . $lang)
											$doc2["features"][$q1]["properties"]["info"][0]["category"] = $wfs_response["features"][$q1]["properties"][$q2];
										if ($q2 == "category_description_" . $lang)
											$doc2["features"][$q1]["properties"]["info"][0]["category_description"] = $wfs_response["features"][$q1]["properties"][$q2];

										// More info
										if(stripos($q2, "b5mcode_others") !== false && strlen($q2) < 18) {
											$b5mcode_others = $wfs_response["features"][$q1]["properties"][$q2];
											$b5mcode_others_name_eu = $wfs_response["features"][$q1]["properties"][$q2 . "_name_eu"];
											$b5mcode_others_name_es = $wfs_response["features"][$q1]["properties"][$q2 . "_name_es"];
											$b5m_code_type2 = strtolower(substr($b5mcode_others, 0, 1));
											foreach ($wfs_capab_xml->FeatureTypeList->FeatureType as $featuretype) {
												if ($b5m_code_type2 == strtolower(explode("_", explode(":", $featuretype->Name->__toString())[1])[0])) {
													$featuretype_name = str_replace("ms:", "", $featuretype->Name);
													$featuretype_desc =	explode(" / ", $featuretype->Title);
													$featuretype_abstract = $featuretype->Abstract->__toString();
													$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["featuretypename"] = $featuretype_name;
													$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["description"] = $featuretype_desc[$lang2];
													$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["abstract"] = $featuretype_abstract;
													$b5mcode_others_a = explode("|", $b5mcode_others);
													$b5mcode_others_name_eu_a = explode("|", $b5mcode_others_name_eu);
													$b5mcode_others_name_es_a = explode("|", $b5mcode_others_name_es);
													$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["numberMatched"] = count($b5mcode_others_a);
													$ot2 = 0;
													foreach ($b5mcode_others_a as $b5mcode_others_i) {
														$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["features"][$ot2]["b5mcode"] = $b5mcode_others_i;
														$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["features"][$ot2]["name_eu"] = $b5mcode_others_name_eu_a[$ot2];
														$doc2["features"][$q1]["properties"]["info"][0]["more_info"][$ot1]["features"][$ot2]["name_es"] = $b5mcode_others_name_es_a[$ot2];
														$ot2++;
													}
												}
											}
											$ot1++;
										}
										// End more info
								}
							}
							//exit;

							// Downloads
							if ($statuscode != "7" && $featuretypenames != "dw_download") {
								$wfs_response_dw = get_dw_list();
								foreach ($wfs_response_dw["features"] as $q1_dw => $r1_dw) {
									$r1_dw = tidy_dw($r1_dw, $lang, $r1_dw);
									$doc2["downloads"][$q1_dw] = [$r1_dw][0]["properties"];
								}
							}

							if ($geom != "false")
								$doc2["features"][$q1]["geometry"] = $wfs_response["features"][$q1]["geometry"];
						}
					}
				} else {
					if ($wfs_response_count > 0) {
						// more_info
						$doc2["more_info_coors"][$j]["featuretypename"] = $val["featuretypename"];
						$doc2["more_info_coors"][$j]["description"] = $val["description"][$lang2];
						$doc2["more_info_coors"][$j]["abstract"] = $val["abstract"];
						$doc2["more_info_coors"][$j]["numberMatched"] = count($wfs_response_feat2);
						$k = 0;
						foreach ($wfs_response_feat2 as $valfeat2) {
							$doc2["more_info_coors"][$j]["features"][$k]["b5mcode"] = $valfeat2["properties"]["b5mcode"];
							$doc2["more_info_coors"][$j]["features"][$k]["name_eu"] = $valfeat2["properties"]["name_eu"];
							$doc2["more_info_coors"][$j]["features"][$k]["name_es"] = $valfeat2["properties"]["name_es"];
							$k++;
						}
						$j++;
					}
				}
			}
			$i++;
		}
		// Number matched
		if (count($doc2) == 0)
			$doc3["numberMatched"] = 0;
		else
			$doc3["numberMatched"] = count($doc2["features"]);
	}

	// More info
	if ($statuscode == 0) {
		$coors_number_a = explode(",", $coors);
		if (count($coors_number_a) == "2") {
			$wfs_typename_list = "";
			foreach ($more_info_a as $more_info_val) {
				$wfs_typename_list = $wfs_typename_list . $more_info_val["featuretypename"] . ",";
			}
			$wfs_typename_list = substr($wfs_typename_list, 0, strlen($wfs_typename_list) - 1);
			$coors_25830 = get_25830($coors, $srs);
			$coors_wms_a = explode(",", $coors_25830);
			$coors_wms = $coors_wms_a[0] - 0.5 . "," . $coors_wms_a[1] - 0.5 . "," . $coors_wms_a[0] + 0.5 . "," . $coors_wms_a[1] + 0.5;
			$url_request_wms = $wfs_server . "?service=wms&version=1.3.0&request=getfeatureinfo&layers=". $wfs_typename_list . "&query_layers=" . $wfs_typename_list . "&bbox=" . $coors_wms . "&crs=epsg:25830&width=2&height=2&i=1&j=1&info_format=application/vnd.ogc.gml&feature_count=10";
			$time_i = microtime(true);
			$wms_response_more_info = get_url_info($url_request_wms)['content'];
			get_time($time_i, $url_request_wms);
			$wms_response_xml = new SimpleXMLElement($wms_response_more_info);
			$i_wms = 0;
			foreach ($more_info_a as $more_info_val) {
				$wfs_typename_item = $more_info_val["featuretypename"];
				$wms_layer = $wfs_typename_item . "_layer";
				$wms_feature = $wfs_typename_item . "_feature";
				if ($wms_response_xml->$wms_layer->$wms_feature != null) {
					$doc2["more_info_coors"][$i_wms]["featuretypename"] = $wfs_typename_item;
					$doc2["more_info_coors"][$i_wms]["description"] = $more_info_val["description"];
					$doc2["more_info_coors"][$i_wms]["abstract"] = $more_info_val["abstract"];
					$doc2["more_info_coors"][$i_wms]["numberMatched"] = count($wms_response_xml->$wms_layer->$wms_feature);
					$i2_wms = 0;
					foreach ($wms_response_xml->$wms_layer->$wms_feature as $wms_feature_val) {
						$doc2["more_info_coors"][$i_wms]["features"][$i2_wms]["b5mcode"] = "" . $wms_feature_val->b5mcode . "";
						$doc2["more_info_coors"][$i_wms]["features"][$i2_wms]["name_eu"] = "" . $wms_feature_val->name_eu . "";
						$doc2["more_info_coors"][$i_wms]["features"][$i2_wms]["name_es"] = "" . $wms_feature_val->name_es . "";
						$i2_wms++;
					}
					$i_wms++;
				}
			}
		}
	}
	// End more info
	if (count($doc2) == 0 && $statuscode != 8 && $statuscode != 9)
		$statuscode = 5;
}

if ($statuscode == 0 || $statuscode == 4 || $statuscode == 5 || $statuscode == 6 || $statuscode == 7 || $statuscode == 8 || $statuscode == 9) {
	// Data license
	$final_time = microtime(true);
	$response_time = sprintf("%.2f", $final_time - $init_time);
	if ($debug == 1) {
		$doc1["debug"]["total_time"] = $time_t;
		$doc1["debug"]["partial_times"] = $time_deb;
	}
	$doc1["info"]["license"]["provider"] = $provider;
	$doc1["info"]["license"]["urlLicense"] = $url_license;
	$doc1["info"]["license"]["urlBase"] = $url_base;
	$doc1["info"]["license"]["imageUrl"] = $image_url;
	$doc1["info"]["license"]["urlWFS"] = $url_request1;
	$doc1["info"]["responseTime"]["time"] = $response_time;
	$doc1["info"]["responseTime"]["units"] = $response_time_units;
	$doc1["info"]["statuscode"] = $statuscode;
	if ($statuscode == 0)
		$doc1["info"]["messages"]["warning"] = $msg000;
	else if ($statuscode == 4) {
		$doc1["info"]["messages"]["request"] = $msg004;
		$doc1["info"]["messages"]["warning"] = $msg000;
	} else if ($statuscode == 5)
		$doc1["info"]["messages"]["warning"] = $msg005;
	else if ($statuscode == 6)
		$doc1["info"]["messages"]["warning"] = $msg006;
	else if ($statuscode == 8)
		$doc1["info"]["messages"]["warning"] = $msg008;
	else if ($statuscode == 9)
		$doc1["info"]["messages"]["warning"] = $msg009;
	if ($statuscode != 4) {
		if ($statuscode != 6 && $statuscode != 7) {
				if ($coors != "") $doc1["coors"] = $coors;
				if ($statuscode != 9) {
				if ($offset != "") $doc1["offset"]["value"] = $offset;
				if ($offset != "") $doc1["offset"]["units"] = $offset_units;
				if ($bbox != "") $doc1["bbox"] = $bbox;
				if ($z != "") $doc1["z"] = $z;
				if ($scale != "") $doc1["scale"] = $scale;
			}
		}
		if ($statuscode != 6) {
			if ($statuscode != 9)
				$doc1["type"] = "FeatureCollection";
			$doc1["crs"]["type"] = "name";
			$doc1["crs"]["properties"]["name"] = $srs_extra;
		}
		if ($featuretypenames != "" && $statuscode != 9) {
			$doc1["featuretypenames"] = $featuretypenames;
		} else {
			$featuretypenames2 = "";
			foreach ($featuretypes_a as $valfeaturetype) {
				if ($featuretypenames2 != "")
					$featuretypenames2 = $featuretypenames2 . ",";
				$featuretypenames2 = $featuretypenames2 . $valfeaturetype["featuretypename"];
			}
			$doc1["featuretypenames"] = $featuretypenames2;
			$doc1["type"] = "FeatureCollection";
		}
	}

	// Merge Arrays
	if ($statuscode == 5 || $statuscode == 6) {
		$doc = $doc1;
	} else {
		$doc = array_merge($doc1, $doc3, $doc2);
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
	// Minify
	$jsonres = json_encode(json_decode($jsonres));
	if (strtolower($format) == "xml") {
		header("Content-type: application/xml;charset=utf-8");
		print_r(json2xml($jsonres));
	} else {
		header("Content-type: application/json;charset=utf-8");
		print_r($jsonres);
	}
}
?>
