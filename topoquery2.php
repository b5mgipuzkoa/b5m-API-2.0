<?php
// b5m topoquery API
//
// Data comes from the b5m OGC WFS Service
//
// Dependencies
// cs2cs from PROJ: https://proj.org/apps/cs2cs.html
//

// Memory
ini_set("memory_limit", "1000M");

// Includes
include_once("./includes/gipuzkoa_wfs_featuretypes_except.php");
include_once("./includes/json2xml.php");
$file_json_zoom = "./json/topoquery2_zoom.json";
$file_json_locales = "./json/topoquery2_locales.json";

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
if (isset($_REQUEST['downloadlist'])) $downloadlist = $_REQUEST['downloadlist']; else $downloadlist = "";
if (isset($_REQUEST['dwtypeid'])) $dwtypeid = $_REQUEST['dwtypeid']; else $dwtypeid = "";
if (isset($_REQUEST['downloads'])) $downloads = $_REQUEST['downloads']; else $downloads = "";
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
$wfs_capab = $wfs_service . "&request=getcapabilities";
$wfs_feature = $wfs_service . "&version=1.1.0&request=describefeaturetype&typename=";
$wfs_request1 = $wfs_service . "&version=2.0.0&request=getFeature&typeNames=";
$wfs_request2 = "?request=GetMetadata&layer=";
$wfs_output = "&outputFormat=application/json;%20subtype=geojson";
$b5m_code_filter1="B5MCODEFILTER1";
$b5m_code_filter2="B5MCODEFILTER2";
$b5m_code_filter3="B5MCODEFILTER3";
$wfs_filter_base1 = "&filter=<Filter><PropertyIsEqualTo><PropertyName>b5mcode</PropertyName><Literal>" . $b5m_code_filter1 . "</Literal></PropertyIsEqualTo></Filter>";
$wfs_filter_base2 = "&filter=<Filter><AND><BBOX><Box%20srsName='" . $b5m_code_filter2 . "'><coordinates>" . $b5m_code_filter3 . "</coordinates></Box></BBOX><PropertyIsLike%20wildcard='*'%20singleChar='.'%20escape='!'><PropertyName>dw_type_ids</PropertyName><Literal>*|" . $b5m_code_filter1 . "|*</Literal></PropertyIsLike></AND></Filter>";
$offset_default = 1;
$offset_units = "metres";
$offset_v = "";
$bbox = "";
$bbox_default = "";
$featuretypes_a = array();
$featuretypes_a2 = array();
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
$wfs_typename_e = "e_buildings";
$wfs_typename_k = "k_streets_buildings";
$wfs_typename_v = "v_streets_axis";
$max_area = 100;
$x1 = "";
$y1 = "";
$x2 = "";
$y2 = "";
$coors_a = array();

// Variable Links
$b5map_link["eu"] = $b5m_server . "/map/eu/";
$b5map_link["es"] = $b5m_server . "/map/es/";
$b5map_link["en"] = $b5m_server . "/map/en/";
$url_dw_info["eu"] = "https://b5m.gipuzkoa.eus/web5000/eu/fitxategi-deskargak";
$url_dw_info["es"] = "https://b5m.gipuzkoa.eus/web5000/es/descarga-archivos";
$url_dw_info["en"] = "https://b5m.gipuzkoa.eus/web5000/en/file-downloads";
$cadastre_url = "https://ssl6.gipuzkoa.eus/Catastro/map.htm?Lon=_LONCADAS&Lat=_LATCADAS&CodM=_CODMCADAS&MapScale=_SCALECADAS&idioma=_LANGCADAS";

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
		$ssl_check = 2;
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
	$tidy_a = rep_key_a($tidy_a, "official_text_" . $tidy_l, "official_text");
	$tidy_a = rep_key_a($tidy_a, "name_" . $tidy_l, "name");
	$tidy_a = rep_key_a($tidy_a, "url_" . $tidy_l, "url");
	$tidy_a = rep_key_a($tidy_a, "owner_" . $tidy_l, "owner");
	$tidy_a = rep_key_a($tidy_a, "description_" . $tidy_l, "description");
	$tidy_a = rep_key_a($tidy_a, "url_ref_" . $tidy_l, "url_ref");
	$tidy_a = rep_key_a($tidy_a, "url_ref1_" . $tidy_l, "url_ref1");
	$tidy_a = rep_key_a($tidy_a, "url_ref2_" . $tidy_l, "url_ref2");
	$tidy_a = rep_key_a($tidy_a, "documentation_" . $tidy_l, "documentation");
	unset($tidy_a["properties"]["b5mcode"]);
	unset($tidy_a["properties"]["name_grid_es"]);
	unset($tidy_a["properties"]["type_grid_eu"]);
	unset($tidy_a["properties"]["type_grid_es"]);
	unset($tidy_a["properties"]["type_grid_en"]);
	unset($tidy_a["properties"]["official"]["official_text_eu"]);
	unset($tidy_a["properties"]["official"]["official_text_es"]);
	unset($tidy_a["properties"]["official"]["official_text_en"]);
	$i_dw = 0;
	foreach ($tidy_a["properties"]["types_dw"] as $types_dw) {
		unset($tidy_a["properties"]["types_dw"][$i_dw]["name_eu"]);
		unset($tidy_a["properties"]["types_dw"][$i_dw]["name_es"]);
		unset($tidy_a["properties"]["types_dw"][$i_dw]["name_en"]);
		// lidar_features and metadata
		$j_dw = 0;
		foreach ($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"] as $series_dw) {
			// lidar_features
			$k_dw = 0;
			foreach ($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"] as $lidar_features_dw) {
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["model_type"]["description_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["model_type"]["description_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["model_type"]["description_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["model_type"]["url_ref_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["model_type"]["url_ref_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["model_type"]["url_ref_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["description_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["description_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["description_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref1_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref1_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref1_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref2_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref2_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["height_type"]["url_ref2_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["data_processing"]["description_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["data_processing"]["description_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["lidar_features"]["data_processing"]["description_en"]);
				$k_dw++;
			}
			// metadata
			$k_dw = 0;
			foreach ($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"] as $metadata_dw) {
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"]["url_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"]["url_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"]["url_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"]["owner_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"]["owner_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["metadata"]["owner_en"]);
				$k_dw++;
			}
			// viewer
			$k_dw = 0;
			foreach ($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"] as $viewer_dw) {
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["url_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["url_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["url_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["description_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["description_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["description_en"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["documentation_eu"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["documentation_es"]);
				unset($tidy_a["properties"]["types_dw"][$i_dw]["series_dw"][$j_dw]["viewer"]["documentation_en"]);
				$k_dw++;
			}
			$j_dw++;
		}
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
		if ($srs_extra != "")
			$bbox2 = $bbox . "," . $srs_extra;
		else
			$bbox2 = $bbox;
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
// End of functions

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
	$b5m_code_a = explode("_", $b5m_code);
	$b5m_code_type = strtolower($b5m_code_a[0]);

	// kp case
	if ($b5m_code_type == "t" && count($b5m_code_a) == 3)
		$b5m_code_type = "kp";
}

// Types Request
if (strtolower($featuretypes) == "true")
	$statuscode = 4;

// Download types
if (strtolower($downloadlist) == "true")
	$statuscode = 10;

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
if ($x2 != "" && $featuretypenames != $wfs_typename_dw) {
	$bbox_area = area_calc($x1, $y1, $x2, $y2, $srs);
	if ($bbox_area > $max_area) {
		$statuscode = 9;
		$msg009 = $msg009 . ". Current area range is " . number_format($bbox_area, 2) . " km2. Maximum area range is " . number_format($max_area, 2) . " km2.";
	}
}

// Show only downloads parameter
if ($downloads == 2)
	$featuretypenames = $wfs_typename_dw;

// Process
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
		if ($except_flag == 0) $i++;
	}
}

// Download types
if ($statuscode == 10) {
	$wfs_typename = str_replace("dw_", "dw2_", $wfs_typename_dw . "_types");
	$url_request1 = $wfs_server . $wfs_request1 . $wfs_typename . $wfs_output;
	$time_i = microtime(true);
	$wfs_response = json_decode((get_url_info($url_request1)['content']), true);
	$featuretypes_a = $wfs_response['features'][0]['properties']['dw_types'];
	$i = 0;
	foreach ($featuretypes_a as $val) {
		$doc2['dw_types'][$i]['dw_type_id'] = $val['dw_type_id'];
		$doc2['dw_types'][$i]['dw_name'] = $val['dw_name_' . $lang];
		$doc2['dw_types'][$i]['dw_grid'] = $val['dw_grid'];
		$i++;
	}
	get_time($time_i, $url_request1);
}

// Zoom restriction
if (($z != "" || $featuretypenames != "") && ($statuscode != 3) && ($statuscode != 9) && ($statuscode != 10)) {
	$zoom_array = json_decode("[" . file_get_contents($file_json_zoom) . "]");
	foreach ($zoom_array as $obj_zoom) {
		if ($obj_zoom->zoom == $z) {
			$featuretypenames_a = $obj_zoom->featuretypenames;
			$offset_v = $obj_zoom->offset;
			$scale_v = $obj_zoom->scale;
		}
	}
	if ($featuretypenames != "")
		$featuretypenames_a = explode(",", $featuretypenames);
	$i = 0;
	foreach ($featuretypenames_a as $featuretypenames_n) {
		$j = 0;
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
if (count($featuretypes_a) == 0 && $statuscode != 3 && $statuscode != 7 && $statuscode != 9 && $statuscode != 10)
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
	if ($dwtypeid != "")
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
				$url_request_md = $wfs_server . $wfs_request2 . $val["featuretypename"];
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
				$wfs_filter = str_replace($b5m_code_filter1, $b5m_code, $wfs_filter_base1);
			}
			if ($featuretypenames == $wfs_typename_dw && $dwtypeid != "") {
				$wfs_filter = "" ;
				$bbox_default_a = explode(",", $bbox_default);
				$bbox_default2 = $bbox_default_a[0] . "," . $bbox_default_a[1] . "%20" . $bbox_default_a[2] . "," . $bbox_default_a[3];
				$wfs_filter = str_replace($b5m_code_filter1, $dwtypeid, $wfs_filter_base2);
				$wfs_filter = str_replace($b5m_code_filter2, $srs, $wfs_filter);
				$wfs_filter = str_replace($b5m_code_filter3, $bbox_default2, $wfs_filter);
				$url_request = $wfs_server . $wfs_request1 . $wfs_typename . $wfs_srsname . $wfs_filter . $wfs_output;
			} else {
				$url_request = $wfs_server . $wfs_request1 . $wfs_typename . $wfs_bbox . $wfs_srsname . $wfs_filter . $wfs_output;
			}

			// Request
			if (count($featuretypes_a) > 0) {
				if ($i == 0) {
					$url_request1 = $url_request;
					$time_i = microtime(true);
					$wfs_response = json_decode((get_url_info($url_request1)['content']), true);
					get_time($time_i, $url_request1);
					$wfs_response_feat = $wfs_response["features"];
					$wfs_response_count = count($wfs_response_feat);
					if ($wfs_typename == $wfs_typename_k && $wfs_response_count == 0) {
						$url_request1 = str_replace("K_", "V_", $url_request1);
						$url_request1 = str_replace($wfs_typename_k, $wfs_typename_v, $url_request1);
						$time_i = microtime(true);
						$wfs_response = json_decode((get_url_info($url_request1)['content']), true);
						get_time($time_i, $url_request1);
						$wfs_response_feat = $wfs_response["features"];
						$wfs_response_count = count($wfs_response_feat);
					}
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
					if ($val["featuretypename"] == $wfs_typename_e) {
						// e_buildings case, postal addresses nested

						// Listing building areas
						foreach ($wfs_response["features"] as $q1 => $r1) {
							$type_e_a[$q1] = $r1["properties"]["b5mcode"];
						}
						$type_e_a = array_unique($type_e_a);
						$t = 0;
						foreach ($type_e_a as $q0 => $r0) {
							$b5m_code_e = $wfs_response["features"][$q0]["properties"]["b5mcode"];
							$doc2["features"][$t]["type"] = $wfs_response["features"][$q0]["type"];
							$doc2["features"][$t]["featuretypename"] = $val["featuretypename"];
							$doc2["features"][$t]["description"] = $val["description"][$lang2];
							$doc2["features"][$t]["abstract"] = $val["abstract"];
							$doc2["features"][$t]["properties"]["b5mcode"] = $b5m_code_e;
							$doc2["features"][$t]["properties"]["b5maplink"] = $b5map_link[$lang] . $b5m_code_e;
							$u = 0;
							$w = 0;
							foreach ($wfs_response["features"] as $q1 => $r1) {
								if ($r0 == $r1["properties"]["b5mcode"]) {
									if ($w == 0)
										$u = 0;
									$w++;
									foreach ($r1["properties"] as $q2 => $r2) {
										$doc2["features"][$t]["properties"]["info"][$u]["featuretypename"] = $d_addr;
										$doc2["features"][$t]["properties"]["info"][$u]["description"] = $d_addr_des[$lang2];
										$doc2["features"][$t]["properties"]["info"][$u]["abstract"] = $d_addr_abs;
										if ($q2 != "idname" && $q2 != "idut" && $q2 != "b5mcode" && $q2 != "type_eu" && $q2 != "type_es" && $q2 != "type_en" && stripos($q2, "b5mcode_others") === false) {
											if ($q2 == "b5mcode2")
												$q2 = "b5mcode";

											// More info && POI
											if ($q2 != "more_info_eu" && $q2 != "more_info_es" && $q2 != "more_info_en" && $q2 != "poi_eu" && $q2 != "poi_es" && $q2 != "poi_en") {
												$doc2["features"][$t]["properties"]["info"][$u][$q2] = $r2;
											} else {
												if ($q2 == "more_info_" . $lang)
													$doc2["features"][$t]["properties"]["info"][$u]["more_info"] = $wfs_response["features"][$q1]["properties"][$q2];
												if ($q2 == "poi_" . $lang)
													$doc2["features"][$t]["properties"]["info"][$u]["poi"] = $r2;
											}
										}
									}
									// Official
									$doc2["features"][$t]["properties"]["info"][$u]["official"]["official_text"] = $doc2["features"][$t]["properties"]["info"][$u]["properties"]["official"]["official_text_" . $lang];
									unset($doc2["features"][$t]["properties"]["info"][$u]["official"]["official_text_eu"]);
									unset($doc2["features"][$t]["properties"]["info"][$u]["official"]["official_text_es"]);
									unset($doc2["features"][$t]["properties"]["info"][$u]["official"]["official_text_en"]);
								}
								$u++;
							}
							if ($geom != "false")
								$doc2["features"][$t]["geometry"] = $wfs_response["features"][$q0]["geometry"];
							$t++;
						}

						// Downloads
						if ($statuscode != "7" && $featuretypenames == "" && $downloads == 1) {
							$wfs_response_dw = get_dw_list();
							foreach ($wfs_response_dw["features"] as $q1_dw => $r1_dw) {
								$r1_dw = tidy_dw($r1_dw, $lang, $q1_dw);
								$doc2["downloads"][$q1_dw] = [$r1_dw][0]["properties"];
								unset($doc2["downloads"][$q1_dw]["dw_type_ids"]);
							}
						}
					} else {
						foreach ($wfs_response["features"] as $q1 => $r1) {
							$doc2["features"][$q1]["type"] = $wfs_response["features"][$q1]["type"];
							$doc2["features"][$q1]["featuretypename"] = $val["featuretypename"];
							$doc2["features"][$q1]["description"] = $val["description"][$lang2];
							$doc2["features"][$q1]["abstract"] = $val["abstract"];
							foreach ($r1["properties"] as $q2 => $r2) {
								if ($q2 == "b5mcode") {
									$b5m_code_f = $wfs_response["features"][$q1]["properties"][$q2];
									$doc2["features"][$q1]["properties"][$q2] = $wfs_response["features"][$q1]["properties"][$q2];
									$doc2["features"][$q1]["properties"]["b5maplink"] = $b5map_link[$lang] . $b5m_code_f;
									if (substr($b5m_code_f, 0, 3) == "DW_")
										$doc2["features"][$q1]["properties"]["url_dw_info"] = $url_dw_info[$lang];
									$doc2["features"][$q1]["properties"]["info"][0][$q2 . "2"] = $wfs_response["features"][$q1]["properties"][$q2];
								} else {
									// Remove not desired fields
									if ($q2 != "idname" && $q2 != "type_eu" && $q2 != "type_es" && $q2 != "type_en" && $q2 != "class_eu" && $q2 != "class_es" && $q2 != "class_en" && $q2 != "id_poi" && $q2 != "class_description_eu" && $q2 != "class_description_es" && $q2 != "class_description_en" && $q2 != "category_eu" && $q2 != "category_es" && $q2 != "category_en" && $q2 != "category_description_eu" && $q2 != "category_description_es" && $q2 != "category_description_en" && $q2 != "poi_eu" && $q2 != "poi_es" && $q2 != "poi_en" && $q2 != "way_eu" && $q2 != "way_es" && $q2 != "way_en" && $q2 != "more_info_eu" && $q2 != "more_info_es" && $q2 != "more_info_en" && stripos($q2, "b5mcode_others") === false && $q2 != "dw_type_ids")
										$doc2["features"][$q1]["properties"]["info"][0][$q2] = $wfs_response["features"][$q1]["properties"][$q2];

									// Type
									if ($q2 == "type_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["type"] = $wfs_response["features"][$q1]["properties"][$q2];

									// Official
									if ($q2 == "official") {
										$doc2["features"][$q1]["properties"]["info"][0]["official"]["offical_text"] = $wfs_response["features"][$q1]["properties"][$q2]["official_text_" . $lang];
										unset($doc2["features"][$q1]["properties"]["info"][0][$q2]["official_text_eu"]);
										unset($doc2["features"][$q1]["properties"]["info"][0][$q2]["official_text_es"]);
										unset($doc2["features"][$q1]["properties"]["info"][0][$q2]["official_text_en"]);
									}

									// Class and Category
									if ($q2 == "class_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["class"] = $wfs_response["features"][$q1]["properties"][$q2];
									if ($q2 == "class_description_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["class_description"] = $wfs_response["features"][$q1]["properties"][$q2];
									if ($q2 == "category_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["category"] = $wfs_response["features"][$q1]["properties"][$q2];
									if ($q2 == "category_description_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["category_description"] = $wfs_response["features"][$q1]["properties"][$q2];
									if ($q2 == "poi_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["poi"] = $wfs_response["features"][$q1]["properties"][$q2];

									// Way
									if ($q2 == "way_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["way"] = $wfs_response["features"][$q1]["properties"][$q2];

									// More info
									if ($q2 == "more_info_" . $lang)
										$doc2["features"][$q1]["properties"]["info"][0]["more_info"] = $wfs_response["features"][$q1]["properties"][$q2];

									// Filter download by lang
									if ($lang == "en")
										$lang_name_grid = "eu";
									else
										$lang_name_grid = $lang;
									if ($q2 == "name_grid_" . $lang_name_grid) {
										$doc2["features"][$q1]["properties"]["info"][0]["name_grid"] = $wfs_response["features"][$q1]["properties"][$q2];
									}
									unset($doc2["features"][$q1]["properties"]["info"][0]["name_grid_eu"]);
									unset($doc2["features"][$q1]["properties"]["info"][0]["name_grid_es"]);
									if ($q2 == "type_grid_" . $lang) {
										$doc2["features"][$q1]["properties"]["info"][0]["type_grid"] = $wfs_response["features"][$q1]["properties"][$q2];
									}
									unset($doc2["features"][$q1]["properties"]["info"][0]["type_grid_eu"]);
									unset($doc2["features"][$q1]["properties"]["info"][0]["type_grid_es"]);
									unset($doc2["features"][$q1]["properties"]["info"][0]["type_grid_en"]);
									if ($q2 == "types_dw") {
										foreach ($r2 as $q3 => $r3) {
											foreach ($r3 as $q4 => $r4) {
												if ($q4 == "name_" . $lang) {
													$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["name"] = $r3["name_" . $lang];
													$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["series_dw2"] = $doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["series_dw"];
													unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["series_dw"]);
													$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["series_dw"] = $doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["series_dw2"];
													unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["series_dw2"]);
												}
												unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["name_eu"]);
												unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["name_es"]);
												unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3]["name_en"]);
												if ($q4 == "series_dw") {
													foreach ($r4 as $q5 => $r5) {
														foreach ($r5 as $q6 => $r6) {
															if ($q6 == "metadata") {
																foreach ($r6 as $q7 => $r7) {
																	if ($q7 == "url_" . $lang)
																		$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url"] = $r7;
																	if ($q7 == "owner_" . $lang)
																		$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["owner"] = $r7;
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url_eu"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url_es"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url_en"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["owner_eu"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["owner_es"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["owner_en"]);
																}
															} else if ($q6 == "lidar_features") {
																foreach ($r6 as $q8 => $r8) {
																	if ($q8 == "model_type") {
																		foreach ($r8 as $q9 => $r9) {
																			if ($q9 == "description_" . $lang)
																				$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description"] = $r9;
																			if ($q9 == "url_ref_" . $lang)
																				$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref"] = $r9;
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_eu"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_es"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_en"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref_eu"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref_es"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref_en"]);
																		}
																	} else if ($q8 == "height_type") {
																		foreach ($r8 as $q10 => $r10) {
																			if ($q10 == "description_" . $lang)
																				$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description"] = $r10;
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_eu"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_es"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_en"]);
																			if ($q10 == "url_ref1_" . $lang)
																				$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref1"] = $r10;
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref1_eu"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref1_es"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref1_en"]);
																			if ($q10 == "url_ref2_" . $lang)
																				$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref2"] = $r10;
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref2_eu"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref2_es"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["url_ref2_en"]);
																		}
																	} else if ($q8 == "data_processing") {
																		foreach ($r8 as $q11 => $r11) {
																			if ($q11 == "description_" . $lang)
																				$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description"] = $r11;
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_eu"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_es"]);
																			unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6][$q8]["description_en"]);
																		}
																	}
																}
															} else if ($q6 == "viewer") {
																foreach ($r6 as $q12 => $r12) {
																	if ($q12 == "url_" . $lang)
																		$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url"] = $r12;
																	if ($q12 == "description_" . $lang)
																		$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["description"] = $r12;
																	if ($q12 == "documentation_" . $lang)
																		$doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["documentation"] = $r12;
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url_eu"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url_es"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["url_en"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["description_eu"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["description_es"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["description_en"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["documentation_eu"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["documentation_es"]);
																	unset($doc2["features"][$q1]["properties"]["info"][0]["types_dw"][$q3][$q4][$q5][$q6]["documentation_en"]);
																}
															}
														}
													}
												}
											}
										}
									}
									// End of filter download by lang
								}
							}

							// Downloads
							if ($statuscode != "7" && $featuretypenames == "" && $downloads == 1) {
								$wfs_response_dw = get_dw_list();
								foreach ($wfs_response_dw["features"] as $q1_dw => $r1_dw) {
									$r1_dw = tidy_dw($r1_dw, $lang, $r1_dw);
									$doc2["downloads"][$q1_dw] = [$r1_dw][0]["properties"];
									unset($doc2["downloads"][$q1_dw]["dw_type_ids"]);
								}
							}

							if ($geom != "false")
								if ($val["featuretypename"] == $wfs_typename_k) {
									// k_streets_buildings case, include axis in a GeometryCollection type
									$url_request2 = str_replace($wfs_typename_k, $wfs_typename_v, $url_request1);
									$url_request2 = str_replace("K_", "V_", $url_request2);
									$wfs_response2 = json_decode((get_url_info($url_request2)['content']), true);
									get_time($time_i, $url_request2);
									$wfs_response_feat2 = $wfs_response2["features"];
									$wfs_response_count2 = count($wfs_response_feat2);
									if ($wfs_response_count2 == 0) {
										$doc2["features"][$q1]["geometry"] = $wfs_response["features"][$q1]["geometry"];
									} else {
										$doc2["features"][$q1]["geometry"]["type"] = "GeometryCollection";
										$doc2["features"][$q1]["geometry"]["geometries"][0] = $wfs_response["features"][$q1]["geometry"];
										$doc2["features"][$q1]["geometry"]["geometries"][1] = $wfs_response2["features"][$q1]["geometry"];
									}
								} else {
									$doc2["features"][$q1]["geometry"] = $wfs_response["features"][$q1]["geometry"];
								}
						}
					}
				} else {
					if ($wfs_response_count > 0) {
						// more_info_coors
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

	// More info coors
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
	// End more info coors

	// External Links
	if (count($coors_a) == 2 && $featuretypenames != $wfs_typename_dw) {
		// One point, there are external links
		$i_ext = -1;
		$doc2["external_links"] = array();

		// 1. Cadastre
		// Municipality
		$codmuni_link = "";
		foreach ($doc2["features"] as $muni_s_01) {
			if ($muni_s_01["featuretypename"] == "m_municipalities") {
				$codmuni_link = $muni_s_01["properties"]["b5mcode"];
					break;
				}
		}
		if ($codmuni_link == "") {
			foreach ($doc2["more_info_coors"] as $muni_s_02) {
				if ($muni_s_02["featuretypename"] == "m_municipalities") {
					$codmuni_link = $muni_s_02["features"][0]["b5mcode"];
					break;
				}
			}
		}
		if ($codmuni_link != "") {
			$i_ext++;
			$extlink_code = "EXT_001";
			$codmuni_link = substr($codmuni_link, 3, 2);
			if (substr($codmuni_link, 0 ,1) == "0")
				$codmuni_link = substr($codmuni_link, 1, 1);

			// Special cases of Cadastre
			if ($codmuni_link == "97" || $codmuni_link == "99")
				$codmuni_link = 98;
			else if ($codmuni_link == "98")
				$codmuni_link = 99;
			else if ($codmuni_link == "96")
				$codmuni_link = 45;

			// SRS
			if ($srs != "epsg:4326") {
				$coors_4326_a = cs2cs($x1, $y1, 5, $srs, "epsg:4326");
				$x1_4326 = $coors_4326_a[0];
				$y1_4326 = $coors_4326_a[1];
			} else {
				$x1_4326 = $x1;
				$y1_4326 = $y1;
			}

			// Scale
			if ($scale == "")
				$scale = $scale_v;
			if ($scale == "")
				$scale = 5000;
			if ($scale > 30000)
				$scale = 30000;

			// Lang
			if ($lang == "es")
				$lang3 = "esp";
			else
				$lang3 = "eus";

			$locales_array = json_decode("[" . file_get_contents($file_json_locales) . "]");
			foreach ($locales_array as $obj_locales) {
				$cadastre_name_locales = "cadastre_name_" . $lang;
				$cadastre_desc_locales = "cadastre_desc_" . $lang;
				$cadastre_name = $obj_locales->$cadastre_name_locales;
				$cadastre_desc = $obj_locales->$cadastre_desc_locales;
			}
			$cadastre_url2 = str_replace("_LONCADAS", $x1_4326, $cadastre_url);
			$cadastre_url2 = str_replace("_LATCADAS", $y1_4326, $cadastre_url2);
			$cadastre_url2 = str_replace("_CODMCADAS", $codmuni_link, $cadastre_url2);
			$cadastre_url2 = str_replace("_SCALECADAS", $scale, $cadastre_url2);
			$cadastre_url2 = str_replace("_LANGCADAS", $lang3, $cadastre_url2);
			$doc2["external_links"][$i_ext]["extlink_code"] = $extlink_code;
			$doc2["external_links"][$i_ext]["name"] = $cadastre_name;
			$doc2["external_links"][$i_ext]["description"] = $cadastre_desc;
			$doc2["external_links"][$i_ext]["url_link"] = $cadastre_url2;
		}
	}

	if (count($doc2) == 0 && $statuscode != 8 && $statuscode != 9)
		$statuscode = 5;
}

// Remove not desired dwtypeids
if ($dwtypeid != "" && $dwtypeid != 7) {
	$doc2a = array();
	foreach ($doc2["features"] as $q1_dwt => $r1_dwt) {
		$doc2a["features"][$q1_dwt]["type"] = $doc2["features"][$q1_dwt]["type"];
		$doc2a["features"][$q1_dwt]["featuretypename"] = $doc2["features"][$q1_dwt]["featuretypename"];
		$doc2a["features"][$q1_dwt]["description"] = $doc2["features"][$q1_dwt]["description"];
		$doc2a["features"][$q1_dwt]["abstract"] = $doc2["features"][$q1_dwt]["abstract"];
		$doc2a["features"][$q1_dwt]["properties"]["b5mcode"] = $doc2["features"][$q1_dwt]["properties"]["b5mcode"];
		$doc2a["features"][$q1_dwt]["properties"]["b5maplink"] = $doc2["features"][$q1_dwt]["properties"]["b5maplink"];
		$doc2a["features"][$q1_dwt]["properties"]["url_dw_info"] = $doc2["features"][$q1_dwt]["properties"]["url_dw_info"];
		foreach ($r1_dwt["properties"]["info"] as $q2_dwt => $r2_dwt) {
			$doc2a["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["b5mcode2"] = $doc2["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["b5mcode2"];
			$doc2a["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["name_grid"] = $doc2["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["name_grid"];
			$doc2a["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["type_grid"] = $doc2["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["type_grid"];
			$doc2a["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["official"] = $doc2["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["official"];
			foreach ($r2_dwt["types_dw"] as $q3_dwt => $r3_dwt) {
				if ($r3_dwt["dw_type_id"] == $dwtypeid) {
					$doc2a["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["types_dw"][0] = $doc2["features"][$q1_dwt]["properties"]["info"][$q2_dwt]["types_dw"][$q3_dwt];
				}
			}
		}
		$doc2a["features"][$q1_dwt]["geometry"] = $doc2["features"][$q1_dwt]["geometry"];
	}
	$doc2 = $doc2a;
	$doc2a = array();
}

if ($statuscode == 0 || $statuscode == 4 || $statuscode == 5 || $statuscode == 6 || $statuscode == 7 || $statuscode == 8 || $statuscode == 9 || $statuscode ==10) {
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
	if ($statuscode != 4 && $statuscode != 10) {
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
