<?php
// b5m topoquery API
//
// Data comes from the b5m OGC WFS Service
//
// Dependencies
// cs2cs from PROJ: https://proj.org/apps/cs2cs.html
//

// Includes
include_once("./includes/gipuzkoa_wfs_featuretypes_except.php");
include_once("./includes/json2xml.php");
$file_json = "./json/topoquery2_zoom.json";

// Requests
if (isset($_REQUEST['lang'])) $lang = $_REQUEST['lang']; else $lang = "";
if (isset($_REQUEST['b5mcode'])) $b5m_code = strtoupper($_REQUEST['b5mcode']); else $b5m_code = "";
if (isset($_REQUEST['coors'])) $coors = $_REQUEST['coors']; else $coors = "";
if (isset($_REQUEST['z'])) $z = $_REQUEST['z']; else $z = "";
if (isset($_REQUEST['scale'])) $scale = $_REQUEST['scale']; else $scale = "";
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
$b5m_server = "https://" . $_SERVER['SERVER_NAME'];
$wfs_server = $b5m_server . "/ogc/wfs2/gipuzkoa_wfs";
$wfs_service = "?service=wfs";
$wfs_valueref = "b5mcode";
$wfs_capab = $wfs_service . "&request=getcapabilities";
$wfs_feature = $wfs_service . "&version=1.1.0&request=describefeaturetype&typename=";
$wfs_request1 = $wfs_service . "&version=2.0.0&request=getFeature&typeNames=";
$wfs_request2 = $wfs_service . "&version=2.0.0&request=getPropertyValue&valueReference=" . $wfs_valueref . "&typeNames=";
$wfs_output = "&outputFormat=application/json;%20subtype=geojson";
$offset_default = 1;
$offset_units = "metres";
$b5mcode = "b5mcode";
$bbox = "";
$featuretypes_a = array();
$doc1 = array();
$doc2 = array();

// Variable Links
$b5map_link["eu"] = $b5m_server . "/b5map/r1/eu/mapa/lekutu/";
$b5map_link["es"] = $b5m_server . "/b5map/r1/es/mapa/localizar/";
$b5map_link["en"] = $b5m_server . "/b5map/r1/en/map/locate/";

// Functions
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

// Messages
$msg001 = "Missing required parameter: coors";
$msg002 = "SRS not supported. It should be EPSG:25830, EPSG:4326 or EPSG:3857 (https://epsg.io)";
$msg003 = "SRS epsg:4326, but invalid latitude or longitude (https://epsg.io)";
$msg004 = "Request type: Featuretypes list";
$msg005 = "No data found";
$msg006 = "Invalid featuretypenames";
$msg008 = "Out of range";

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
	$x = $coors_a[0];
	$y = $coors_a[1];
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
if ($offset == "") $offset = $offset_default;
if ($statuscode == 7) $offset = "";

// Trying to guess the SRS
if ($srs == "" && $statuscode == 0) {
	if ($x >= -180 && $x <= 180)
		$srs = "epsg:4326";
	else if ($x > 180)
		$srs = "epsg:25830";
	else
		$srs = "epsg:3857";
} else if ($srs == "" && $statuscode == 7) {
		$srs = "epsg:25830";
}

if ($statuscode == 0 || $statuscode == 4 || $statuscode == 7) {
	// Collecting featuretype data from getcapabilites request
	$url_capab = $wfs_server . $wfs_capab;
	$wfs_capab_response = (get_url_info($url_capab)['content']);
	$wfs_capab_xml = new SimpleXMLElement($wfs_capab_response);
	$i = 0;

	// Typenames
	foreach ($wfs_capab_xml->FeatureTypeList->FeatureType as $featuretype) {
		$except_flag = 0;
		$featuretype_name = str_replace("ms:", "", $featuretype->Name);
		$featuretype_desc =	explode(" / ", $featuretype->Title);
		$featuretype_abstract = $featuretype->Abstract->__toString();
		if ($statuscode == 7) {
			// Id Request
			if (strtolower(explode("_", $b5m_code)[0]) == strtolower(explode("_", explode(":", $featuretype->Name->__toString())[1])[0])) {
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

			if ($featuretypenames == "")
				$featuretypes_a[$i]["properties"] = get_feat_info($featuretype_name);
		}
		$i++;
	}
}

// Zoom restriction
if ($z != "" || $featuretypenames != "") {
	$data_json = file_get_contents($file_json);
	$zoom_array = json_decode($data_json);
	foreach($zoom_array as $obj) {
		if($obj->zoom == $z)
			$featuretypenames_a = $obj->featuretypenames;
	}
	if ($featuretypenames != "")
		$featuretypenames_a = explode(",", $featuretypenames);
	$i=0;
	foreach ($featuretypenames_a as $featuretypenames_n) {
		$j=0;
		foreach ($featuretypes_a as $featuretypes_n) {
			if ($featuretypenames_n == $featuretypes_n["featuretypename"]) {
				$featuretypes_a2[$i] = $featuretypes_a[$j];
				$featuretypes_a2[$i]["properties"] = get_feat_info($featuretypenames_n);
				$i++;
			}
			$j++;
		}
	}
	$featuretypes_a = $featuretypes_a2;
}

// Featuretypes count
if (count($featuretypes_a) == 0 && $statuscode != 3 && $statuscode != 7)
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

if ($statuscode == 0 || $statuscode == 7) {
	// SRS related extra parameters
	if (strtolower($srs) == "epsg:25830")
	 $srs_extra="urn:ogc:def:crs:EPSG::25830";
	else if (strtolower($srs) == "epsg:4326")
	 $srs_extra="urn:ogc:def:crs:EPSG::4326";
	else if (strtolower($srs) == "epsg:3857")
	 $srs_extra="urn:ogc:def:crs:EPSG::3857";
	else
	 $srs_extra="";

	if ($statuscode != 7) {
		// BBOX
		if (strtolower($srs) == "epsg:4326") {
			$coors_25830 = shell_exec('echo "' . $x . ' ' . $y . '" | /usr/local/bin/cs2cs -f "%.2f" +init=epsg:4326 +to +init=epsg:25830 2> /dev/null');
			$coors_25830_a1 = explode("	", $coors_25830);
			$coors_25830_a2 = explode(" ", $coors_25830_a1[1]);
			$x1 = $coors_25830_a1[0];
			$y1 = $coors_25830_a2[0];
			if (is_numeric($x) && is_numeric($y))
				$statuscode = $statuscode;
			else
				$statuscode = 8;
			if ($x1 == "*" || $statuscode == 8) {
				// Out of range
				$statuscode = 8;
			} else {
				$coors_4326_1 = shell_exec('echo "' . $x1 - $offset . ' ' . $y1 - $offset . '" | /usr/local/bin/cs2cs -f "%.6f" +init=epsg:25830 +to +init=epsg:4326 2> /dev/null');
				$coors_4326_2 = shell_exec('echo "' . $x1 + $offset . ' ' . $y1 + $offset . '" | /usr/local/bin/cs2cs -f "%.6f" +init=epsg:25830 +to +init=epsg:4326 2> /dev/null');
				$coors_4326_a11 = explode("	", $coors_4326_1);
				$coors_4326_a12 = explode(" ", $coors_4326_a11[1]);
				$coors_4326_a21 = explode("	", $coors_4326_2);
				$coors_4326_a22 = explode(" ", $coors_4326_a21[1]);
				$bbox = $coors_4326_a12[0] . "," . $coors_4326_a11[0] . "," . $coors_4326_a22[0] . "," . $coors_4326_a21[0];
			}
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
	} else {
		if ($srs != "")
			$wfs_srsname = "&srsname=" . $srs;
		else
			$wfs_srsname = "";
	}

	// Data request
	if ($statuscode != 8) {
		if ($statuscode != 7)
			$statuscode = 5;
		$i = 0;
		$j = 0;
		foreach ($featuretypes_a as $val) {
			$wfs_typename = $val["featuretypename"];
			if ($statuscode != 7) {
				$wfs_bbox = "&bbox=" . $bbox2;
				$wfs_filter = "";
			} else {
				$wfs_bbox = "";
				$wfs_filter = "&filter=<Filter><PropertyIsEqualTo><PropertyName>" . $b5mcode . "</PropertyName><Literal>" . $b5m_code . "</Literal></PropertyIsEqualTo></Filter>";
			}
			if ($i == 0)
				$url_request = $wfs_server . $wfs_request1 . $wfs_typename . $wfs_bbox . $wfs_srsname . $wfs_filter . $wfs_output;
			else
				$url_request = $wfs_server . $wfs_request2 . $wfs_typename . $wfs_bbox . $wfs_srsname . $wfs_filter;

			// Request
			if (count($featuretypes_a) > 0) {
				if ($i == 0) {
					$wfs_response = json_decode((get_url_info($url_request)['content']), true);
					$wfs_response_feat = $wfs_response["features"];
					$wfs_response_count = count($wfs_response_feat);
				} else {
					$wfs_response = (get_url_info($url_request)['content']);
					$p = xml_parser_create();
					xml_parse_into_struct($p, $wfs_response, $vals, $index);
					xml_parser_free($p);
					$wfs_response_count =  $vals[0]["attributes"]["NUMBERRETURNED"];
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
						$doc2["features"][0]["type"] = $wfs_response["features"][0]["type"];
						$doc2["features"][0]["featuretypename"] = $val["featuretypename"];
						$doc2["features"][0]["description"] = $val["description"][$lang2];
						$doc2["features"][0]["abstract"] = $val["abstract"];
						$b5m_code_e = $wfs_response["features"][0]["properties"]["b5mcode"];
						$doc2["features"][0]["properties"]["b5mcode"] = $b5m_code_e;
						$doc2["features"][0]["properties"]["b5maplink"] = $b5map_link[$lang] . $b5m_code_e;
						$z=0;
						foreach($wfs_response["features"] as $x1 => $y1) {
							foreach($y1["properties"] as $x2 => $y2) {
								if ($x2 != "idname" && $x2 != "b5mcode")
									$doc2["features"][0]["properties"]["info"][$z][$x2] = $y2;
							}
							$z++;
						}
						$doc2["features"][0]["geometry"] = $wfs_response["features"][0]["geometry"];
					} else {
						foreach($wfs_response["features"] as $x1 => $y1) {
							$doc2["features"][$x1]["type"] = $wfs_response["features"][$x1]["type"];
							$doc2["features"][$x1]["featuretypename"] = $val["featuretypename"];
							$doc2["features"][$x1]["description"] = $val["description"][$lang2];
							$doc2["features"][$x1]["abstract"] = $val["abstract"];
							foreach($y1["properties"] as $x2 => $y2) {
								$doc2["features"][$x1]["properties"][$x2] = $wfs_response["features"][$x1]["properties"][$x2];
								if ($x2 == "b5mcode")
									$doc2["features"][$x1]["properties"]["b5maplink"] = $b5map_link[$lang] . $wfs_response["features"][$x1]["properties"][$x2];
							}
							$doc2["features"][$x1]["geometry"] = $wfs_response["features"][$x1]["geometry"];
						}
					}
				} else {
					if ($wfs_response_count > 0) {
						$wfs_response_vals = $vals[2]["value"];
						$doc2["features"][0]["properties"]["more_info"][$j]["featuretypename"] = $val["featuretypename"];
						$doc2["features"][0]["properties"]["more_info"][$j]["description"] = $val["description"][$lang2];
						$doc2["features"][0]["properties"]["more_info"][$j]["abstract"] = $val["abstract"];
						$doc2["features"][0]["properties"]["more_info"][$j]["b5mcode"] = $wfs_response_vals;
						$j++;
					}
				}
				$i++;
			}
		}
	}
	if (count($doc2) == 0 && $statuscode != 8)
		$statuscode = 5;
}

if ($statuscode == 0 || $statuscode == 4 || $statuscode == 5 || $statuscode == 6 || $statuscode == 7 || $statuscode == 8) {
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
	if ($statuscode == 8)
		$doc1["info"]["messages"]["warning"] = $msg008;
	if ($statuscode != 4) {
		if ($statuscode != 6 && $statuscode != 7) {
			if ($coors != "") $doc1["coors"] = $coors;
			if ($offset != "") $doc1["offset"]["value"] = $offset;
			if ($offset != "") $doc1["offset"]["units"] = $offset_units;
			if ($bbox != "") $doc1["bbox"] = $bbox;
			if ($z != "") $doc1["z"] = $z;
			if ($scale != "") $doc1["scale"] = $scale;
		}
		if ($statuscode != 6) {
			$doc1["crs"]["type"] = "name";
			$doc1["crs"]["properties"]["name"] = $srs_extra;
		}
		if ($featuretypenames != "") {
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
