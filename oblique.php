<?php
// b5m oblique API
//
// Dependencies
// cs2cs from PROJ: https://proj.org/apps/cs2cs.html
//
// Other Dependencies
// obliquo API installed from voxel3D: https://voxel3d.es/obliquo
//

// Memory
ini_set("memory_limit", "1000M");

// Includes

// Requests
if (isset($_REQUEST['year'])) $year = $_REQUEST['year']; else $year = "";
if (isset($_REQUEST['x'])) $x = $_REQUEST['x']; else $x = "";
if (isset($_REQUEST['y'])) $y = $_REQUEST['y']; else $y = "";
if (isset($_REQUEST['px'])) $px = $_REQUEST['px']; else $px = "";
if (isset($_REQUEST['py'])) $py = $_REQUEST['py']; else $py = "";
if (isset($_REQUEST['srid'])) $srid = $_REQUEST['srid']; else $srid = "";
if (isset($_REQUEST['name'])) $name = $_REQUEST['name']; else $name = "";
if (isset($_REQUEST['noimage'])) $noimage = $_REQUEST['noimage']; else $noimage = "";
if (isset($_REQUEST['type'])) $type = $_REQUEST['type']; else $type = "";
if (isset($_REQUEST['format'])) $format = $_REQUEST['format']; else $format = "";
if (isset($_REQUEST['debug'])) $debug = $_REQUEST['debug']; else $debug = 0;

// Variables
if ($_SERVER['SERVER_NAME'] == "172.23.128.130")
	$b5m_server = "http://" . $_SERVER['SERVER_NAME'];
else if ($_SERVER['SERVER_NAME'] == "b5mlive1.gipuzkoa.eus" || $_SERVER['SERVER_NAME'] == "b5mlive2.gipuzkoa.eus")
	$b5m_server = "https://b5m.gipuzkoa.eus";
else
	$b5m_server = "https://" . $_SERVER['SERVER_NAME'];
$obliquo_api = "/obliquo/api/oblique";
$obliquo_api_features = $obliquo_api . "/features";
$obliquo_api_measure1 = $obliquo_api . "/measure3";
$obliquo_api_measure2 = $obliquo_api . "/measure";
$directions = array("N", "S", "E", "W");
$years_a = array(2007, 2009);
$years_oblique = [];

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

function cs2cs($c1, $c2, $fl, $srs1, $srs2) {
	$coors_c_01 = shell_exec('echo "' . $c1 . ' ' . $c2 . '" | /usr/local/bin/cs2cs -f "%.' . $fl . 'f" +init="' . $srs1 . '" +to +init="' .$srs2 . '" 2> /dev/null');
	$coors_c_02 = explode("	", $coors_c_01);
	$coors_c_03 = explode(" ", $coors_c_02[1]);
	return array($coors_c_02[0], $coors_c_03[0]);
}

// Centroid of a polygon with four vertices
function polygonCentroid($llx, $lly, $lrx, $lry, $urx, $ury, $ulx, $uly) {
    // X eta Y koordenatuen zentroidea kalkulatu
		$centroidX = ($llx + $lrx + $urx + $ulx) / 4;
		$centroidY = ($lly + $lry + $ury + $uly) / 4;

    return ['x' => $centroidX, 'y' => $centroidY];
}

// End of functions

// Messages
$msg000 = "None";
$msg001 = "Missing required parameter: x or px";
$msg999 = "No data found";

// License and metadata variables
$yearl = date("Y");
$provider = "b5m - Gipuzkoa Spatial Data Infrastructure - Gipuzkoa Provincial Council - " .$yearl;
$url_license = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/en/legal-information";
$url_base = "https://" . $_SERVER['SERVER_NAME'] . "/web5000";
$image_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/img/logo-b5m-black.svg";
$response_time_units = "seconds";
$messages = "";

// Init statuscode and time
$statuscode = 0;
$init_time = microtime(true);

if ($x == "" && $px == "") {
	$statuscode = 1;
	$messages = $msg001;
}

// Request type

// No year
$is_oblique = 2;
if ($year === "") {
	$is_oblique = 0;
	foreach ($years_a as $year_q) {
		$url_request = $b5m_server . $obliquo_api_features . "?layers=guipuzcoa%3Aguipuzcoa" . $year_q. "%3Aguipuzcoa" . $year_q . "&feature_count=200&x=" . $x . "&y=" . $y . "&srid=" . $srid . "&op=getFeatureInfo";
		$response = get_url_info($url_request)['content'];
		$response = json_decode($response, true);
		if ($response['data'] !== null) {
			$is_oblique = 1;
			$years_oblique[] = $year_q;
		}
	}
	if ($is_oblique === 0)
		$years_oblique = null;
	$response = [];
	$response["data"]["years"] = $years_oblique;
} else if ($type ==  "measure") {
	if ($x != "")
		$url_request = $b5m_server . $obliquo_api_measure1 . "?u_x=" . $x. "&u_y=" . $y;
	else
		$url_request = $b5m_server . $obliquo_api_measure2 . "?pixelX=" . $px. "&pixelY=" . $py;
	$url_request = $url_request  . "&name=" . $name . "&layer=guipuzcoa" . $year;
} else {
	$url_request = $b5m_server . $obliquo_api_features . "?layers=guipuzcoa%3Aguipuzcoa" . $year. "%3Aguipuzcoa" . $year . "&feature_count=200&x=" . $x . "&y=" . $y . "&srid=" . $srid . "&op=getFeatureInfo";
}

if ($is_oblique == 2) {
	// Request
	$response = get_url_info($url_request)['content'];
	$response = json_decode($response, true);

	// Type features, return only the most centered oblique image
	if ($type == "features" && $response["features"] != 0) {
		// Point data in EPSG:25830 srid
		if ($srid != "25830") {
			$coors_25830 = cs2cs($x, $y, 0, "epsg:" . $srid, "epsg:25830");
			$x_25830 = $coors_25830[0];
			$y_25830 = $coors_25830[1];
		} else {
			$x_25830 = $x;
			$y_25830 = $y;
		}


		// Directions
		$image_selected = array();
		foreach ($directions as $direction) {
			$image_distance = array();
			foreach ($response["data"]["features"] as $feature) {
				$shotorient = $feature["properties"]["shotorient"];
				$imagename = $feature["properties"]["imagename"];
				$llx = $feature["properties"]["llx"];
				$lly = $feature["properties"]["lly"];
				$lrx = $feature["properties"]["lrx"];
				$lry = $feature["properties"]["lry"];
				$ulx = $feature["properties"]["ulx"];
				$uly = $feature["properties"]["uly"];
				$urx = $feature["properties"]["urx"];
				$ury = $feature["properties"]["ury"];
				if ($shotorient == $direction && $imagename != $noimage) {
					// Obtain the distance from the image centroid to the point
					$centroid = polygonCentroid($llx, $lly, $lrx, $lry, $urx, $ury, $ulx, $uly);
					$distance = round(sqrt(pow($x_25830 - $centroid["x"], 2) + pow($y_25830 - $centroid["y"], 2)), 2);
					$image_distance[] = array("Image" => $imagename, "Distance" => $distance);
				}
			}
			if (count($image_distance) > 0) {
				$nearest = $image_distance[0];
				foreach ($image_distance as $image_d) {
						if ($image_d["Distance"] < $nearest["Distance"]) {
								$nearest = $image_d;
				    }
				}
				$image_selected[] = $nearest;
			}
		}
		$response2["features"] = count($image_selected);
		$response2["data"]["type"] = $response["data"]["type"];
		foreach ($response["data"]["features"] as $feature) {
				$imagename = $feature["properties"]["imagename"];
				foreach ($image_selected as $item) {
					if ($imagename == $item["Image"])
						$response2["data"]["features"][] = $feature;
				}
		}
		$response = $response2;
	}

	// Filters
	if ($type == "features") {
		if (is_null($response['data'])) {
			$result = $response;
		} else {
			$result['features'] = $response['features'] ?? null;
			$result['data']['type'] = $response['data']['type'] ?? null;
			if (isset($response['data']['features'])) {
				foreach ($response['data']['features'] as $feature) {
			    if (isset($feature['properties'])) {
			    	$properties = $feature['properties'];
							$result['data']['features'][]['properties'] = [
			      	'imagename' => $properties['imagename'] ?? null,
			        'imagecols' => $properties['imagecols'] ?? null,
			        'imagerows' => $properties['imagerows'] ?? null,
			        'shotdate' => $properties['shotdate'] ?? null,
			        'shotorient' => $properties['shotorient'] ?? null
			        ];
			    }
			  }
			}
		}
	} else {
		$result['message'] = $response['message'] ?? null;
		if ($response['message'] == "No query DB.") {
			$result = $response;
		} else if ($response['message'] == "Correct RealCoordinates to Photocoordinates transformation") {
			$result['x'] = $response['x'] ?? null;
			$result['y'] = $response['y'] ?? null;
		} else {
			$result['X'] = $response['X'] ?? null;
			$result['Y'] = $response['Y'] ?? null;
			$result['Z'] = $response['Z'] ?? null;
		}
	}
	$response = $result;
}

if ($statuscode == 0 ) {
	// Data license
	$final_time = microtime(true);
	$response_time = sprintf("%.2f", $final_time - $init_time);
	if ($debug == 1) {
		$doc["debug"]["total_time"] = $time_t;
		$doc["debug"]["partial_times"] = $time_deb;
	}
	$doc["info"]["license"]["provider"] = $provider;
	$doc["info"]["license"]["urlLicense"] = $url_license;
	$doc["info"]["license"]["urlBase"] = $url_base;
	$doc["info"]["license"]["imageUrl"] = $image_url;
	$doc["info"]["license"]["urlObliquo"] = $url_request;
	$doc["info"]["responseTime"]["time"] = $response_time;
	$doc["info"]["responseTime"]["units"] = $response_time_units;
	$doc["info"]["statuscode"] = $statuscode;
	if ($statuscode == 0)
		$doc["info"]["messages"]["warning"] = $msg000;
	$doc = array_merge($doc, $response);
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
