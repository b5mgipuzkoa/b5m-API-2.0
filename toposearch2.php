<?php
// Toponymic Finder

// Solr Server Domain Name
define("SOLR_SERVER_HOSTNAME", "b5mdev");

// HTTP Port for the Connection
define("SOLR_SERVER_PORT", 8983);

// Connection Path
define("SOLR_SERVER_PATH_A", array(
	"solr/b5mtopo",
	"solr/b5maddr",
	"solr/b5mpk"
));

// Includes
include_once("includes/subrulesolr.php");
include_once("includes/json2xml.php");

// Input Parameters
$lang = @$_REQUEST["lang"];
$q = @$_REQUEST["q"];
$format = @$_REQUEST["format"];
$debug = @$_REQUEST["debug"];
$rows = @$_REQUEST["rows"];
$start = @$_REQUEST["start"];
$addr = @$_REQUEST["addr"];
$city = @$_REQUEST["city"];
$riverbasin = @$_REQUEST["riverbasin"];
$road = @$_REQUEST["road"];
$street = @$_REQUEST["street"];
$b5m_id = @$_REQUEST["b5m_id"];
$type = @$_REQUEST["type"];
$viewbox = @$_REQUEST["viewbox"];
$pt = @$_REQUEST["pt"];
$dist = @$_REQUEST["dist"];
$types = @$_REQUEST["types"];
$nor = @$_REQUEST["nor"];
$numfound = @$_REQUEST["numfound"];
$sort = strtolower(@$_REQUEST["sort"]);

function query_function($search_type) {
	// Global Variables
	global $lang, $q, $format, $debug, $rows, $start, $addr, $city, $riverbasin, $road, $street, $b5m_id, $type, $viewbox, $pt, $dist, $types, $nor, $sort, $numfound;
	global $response, $count;
	global $types_a;

	// Connection Type
	if (substr($search_type, 0, 2) == "ad") $i = 1;
	else if (substr($search_type, 0, 2) == "pk") $i = 2;
	else $i = 0;

	// Coding Empty Parameters
	if (empty($street)) $street = 0;
	if (empty($addr)) $addr = 0;
	else if ($addr != "1") $addr = 0;
	else $type = 'posta helbidea'; $addr = 0;
	if ($street != "0") $addr = 1;
	if (empty($nor) || $nor != "1") $nor = 0;
	if (empty($types) || $types != "1") $types = 0;
	if (empty($road)) $road = 0;
	if (empty($riverbasin)) $riverbasin = 0;
	if (empty($b5m_id)) $b5m_id = 0;
	if (empty($city)) $city = 0;

	// We cannot have all 3 parameters at the same time and, if we have street, we must also have city
	if ($b5m_id != "0") {
		$city = 0;
		$riverbasin = 0;
		$road = 0;
		$street = 0;
		$q = "*";
	}
	if ($city != "0") {
		$b5m_id = 0;
		$riverbasin = 0;
		$road = 0;
	} else $street = 0;
	if ($riverbasin != "0") {
		$b5m_id = 0;
		$city = 0;
		$road = 0;
		$street = 0;
	}
	if (empty($type)) $type = 0;
	if (empty($q)) {
		if ($b5m_id == "0" && $road == "0" && $riverbasin == "0" && $city == "0" && $type == "0" && $types == "0") {
			$count = -1;
			return;
		} else {
			$q = "*";
		}
	}

	// Record Limit and Pagination Start
	if (empty($start)) $start = 0;
	if (empty($rows)) $rows = 1000000;
	if ($numfound == 1) $rows = 1;

	// Language Coding
	if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if ($lang != "eu" && $lang != "es" && $lang != "en") $lang = "eu";
	if ($lang == "en") $lang2 = "eu"; else $lang2 = $lang;

	// Search Type
	$there_is_num = 0;
	$main_string = explode(" ", $q);
	foreach ($main_string as $val) {
		$val_usa = strtr($val, ',', '.');
	 	if (is_numeric($val_usa)) {
	 		$there_is_num = 1;
	 	}
	}
	if ($there_is_num == 0) {
		// If there is no number, it is not searched by postal address in the first and second instance (yes in the others), except for street; and it is not searched for PK, except for road
		$not_addr = 0;
		$not_pks = 0;
		if ($street == "0") $not_addr = 1;
		if ($road == "0") $not_pks = 1;
	} else {
		$not_addr = 0;
		$not_pks = 0;
	}

	// Convert the characters that come with the coordinates
	$string1 = preg_replace("#".chr(194).chr(176)."#", " ", $q);
	$string1 = preg_replace("#".chr(226).chr(128).chr(178)."#", " ", $string1);
	$string1 = preg_replace("#".chr(39)."#", " ", $string1);
	$string1 = preg_replace("#".chr(226).chr(128).chr(179)."#", " ", $string1);
	$string1 = preg_replace("#".chr(34)."#", " ", $string1);
	$q = $string1;

	// If there is only one character the subrulesolr encoding is not used
	if (strlen($q) == 1) {
		$string = strtoupper($q);
	} else {
		// Encode Search String
		$string = subrulesolr($q);
	}

	// Debug
	if (empty($debug)) $debug = "0";
	if ($debug == "0") {
		$debug1 = "false";
		$echo_params = "none";
		$omit_header = "true";
		}
	else if ($debug == "1") {
		$debug1 = "false";
		$echo_params = "explicit";
		$omit_header = "false";
		}
	else if ($debug == "2") {
		$debug1 = "false";
		$echo_params = "all";
		$omit_header = "false";
		}
	else if ($debug == "3") {
		$debug1 = "true";
		$echo_params = "all";
		$omit_header = "false";
		}
	else $debug = "false";

	// Solr Connection Options
	$options = array
	(
		"hostname" => SOLR_SERVER_HOSTNAME,
		"port"     => SOLR_SERVER_PORT,
		"path"	   => SOLR_SERVER_PATH_A[$i]
	);
	$solr_client = new SolrClient($options);

	// Query
	$solr_query = new SolrQuery();

	// Search Field and String
	if ($search_type == "topo3") $search_field = "field_search2";
		else $search_field = "field_search";
	if ($search_type == "topo2" || $search_type == "addr2" || $search_type == "topo3" || $search_type == "pk2") {
		$string2 = explode(" ", $string);
		$string3 = "";
		foreach($string2 as $val) {
			if (strlen($val) > 0) {
				if (strlen($val) < 10) $string3 = $string3 . $val . "~1 ";
				else $string3 = $string3 . $val . "~ ";
			}
		}
		$string = $string3;
	} else if ($search_type == "addr3") {
		$string4 = explode(" ", $string);
		$string5 = "";
		foreach($string4 as $val) {
			if (strlen($val) > 0) {
				if (strlen($val) < 10) $string5 = $string5 . $val . "* ";
				else $string5 = $string5 . $val . " ";
			}
		}
		$string = $string5;
	} else if ($search_type == "pk3") {
		$string6 = explode(" ", $string);
		$string7 = "";
		$cu = 0;
		foreach($string6 as $val) {
			if (strlen($val) > 0) {
				$cu = $cu + 1;
				$string7 = $string7 . $val . "* ";
			}
		}
		if ($cu == 1 && strlen($val) <= 4) $string7 = $val . "*";
		$string = $string7;
	} else {
		$search_field = "field_search";
	}

	// Selection
	$sele = "{!q.op=AND}";
	if ($q == "*")
		$sele = $sele . "*";
	else
		$sele = $sele . $string;

	// Spatial Filter
	$latm = 0;
	$lonm = 0;
	$latx = 0;
	$lonx = 0;
	if (empty($viewbox)) $filter = "";
	else {
		$viewbox = str_replace(", ", " ", $viewbox);
		$viewbox = str_replace(",", " ", $viewbox);
		$coormbr = explode(" ", $viewbox);
		$latm=$coormbr[3];
		$lonm=$coormbr[0];
		$latx=$coormbr[1];
		$lonx=$coormbr[2];
		if ($latm == "0" && $lonm == "0" && $latx == "0" && $lonx == "0")
			$filter = "";
		else
			$filter = "{!field f=bbox}Intersects(ENVELOPE(" . $latm . "," . $latx . "," . $lonx . "," . $lonm. "))";
			$solr_query->addFilterQuery($filter);
	}

	// Score Field
	$field_sc = "importance:score";

	// Sort
	if ($q == "*" && $pt == 0) {
		if (empty($sort)) $sort="asc";
		$sort_field = "name_" . $lang2 . "_sort";
	} else {
		if (empty($sort)) $sort="desc";
		$sort_field = "score";
	}
	$sort_asc = SolrQuery::ORDER_ASC;
	$sort_desc = SolrQuery::ORDER_DESC;
	if ($sort == "asc") {
		$sort = $sort_asc;
	} else {
		$sort = $sort_desc;
	}

	// Special Fields
	$field_street = "street_" . $lang2;
	$field_muni = "muni_" . $lang2;
	$field_road = "carre";
	$field_basin = "cuen_" . $lang2;
	$field_type = "type_" . $lang;
	$field_type_s = "type_" . $lang . "_search";
	$field_type_f = "type_" . $lang . "_facet";

	// Types
	if ($types == "1") {
		$solr_query->setQuery($sele);
		$solr_query->setFacet(true);
		$solr_query->setFacetMinCount(1);
		$solr_query->setFacetLimit(-1);
		$solr_query->addFacetField($field_type_f);
		$solr_query->setRows(0);
		$response_query = $solr_client->query($solr_query);
		$response = $response_query->getResponse();
		$objects = $response["facet_counts"]["facet_fields"][$field_type_f];
		foreach ($objects as $obj => $value) {
			$types_a[] = $obj;
		}
		$count = -2;
		return;
	}

	// Filters by Codes and Types
	if ((substr($search_type, 0, 2) == "to" || substr($search_type, 0, 2) == "pk") && $street != "0")
		return;
	if ((substr($search_type, 0, 2) == "to" || substr($search_type, 0, 2) == "ad") && $road != "0")
			return;
	if ($b5m_id != "0")
		$solr_query->addFilterQuery("code:" . "\"" . $b5m_id . "\"");
	if ($street != "0") {
		if (is_numeric($street)) $field_street2 = "codstreet:0"; else $field_street2 = $field_street . ":";
		$solr_query->addFilterQuery($field_street2 . "\"" . $street . "\"");
		if (empty($pt))
			$solr_query->addSortField("door_number_sort", $sort_asc);
	}
	if ($riverbasin != "0") {
		$solr_query->addFilterQuery($field_basin . ":\"" . $riverbasin . "\"");
		if (empty($pt))
			$solr_query->addSortField($sort_field, $sort_asc);
	}
	if ($city != "0") {
		$field_muni2 = $field_muni;
		if (is_numeric($city)) {
			if ($search_type == "addr1" || $search_type == "addr2" || $search_type == "addr3") $field_muni2 = "codmuni"; else $field_muni2 = "codmuni_search";
			$solr_query->addFilterQuery($field_muni2 . ":*" . $city . "*");
		} else {
			$solr_query->addFilterQuery($field_muni2 . ":\"" . $city . "\"");
		}
	}
	if ($road != "0") {
		if (is_numeric($road)) $field_road="id_carre";
		$solr_query->addFilterQuery($field_road . ":\"" . $road . "\"");
		$solr_query->addFilterQuery("type_en:(\"KM\" )");
		if (empty($pt)) {
			$solr_query->addSortField("kil_sort", $sort_asc);
			$solr_query->addSortField("sentido_sort", $sort_asc);
		}
	}
	if ($type != "0") {
		$cc = explode("|", $type);
		$ccn = "(";
		foreach($cc as $val) {
			if (strlen($val) > 0) {
				$ccn = $ccn . "\"" . $val . "\" ";
			}
		}
		$ccn = $ccn . ")";
		$solr_query->addFilterQuery($field_type_s . ":" . $ccn);
	}
	if (($street == "0" && $road == "0" && $riverbasin == "0") || (!empty($pt)))
		$solr_query->addSortField($sort_field, $sort);

	// Centroid Filter
	if (!empty($pt)) {
		if (empty($dist)) $dist="80";
		$pt = str_replace(", ", ",", $pt);
		$pt = str_replace(" ", ",", $pt);
		$solr_query->setQuery(" _query_:\"{!func}scale(query(\$main_string),1,50)\" AND _query_:\"{!func}div(50,map(geodist(),0,1,1))\"");
		$solr_query->addFilterQuery("{!cache=false v=\$main_string}");
		$solr_query->addFilterQuery("{!geofilt}");
		$solr_query->addParam("pt", $pt);
		$solr_query->addParam("sfield", "center");
		$solr_query->addParam("d", $dist);
		$solr_query->addParam("cadena", $string);
		$solr_query->addField("distance:div(rint(product(geodist(),100)),100)");
		$solr_query->addParam("q.op", "AND");
	} else {
		if ($nor == "1") {
			$solr_query->setQuery("_query_:\"{!func}scale(query(\$main_string),1,100)\"");
			$solr_query->addFilterQuery("{!cache=false v=\$main_string}");
			$solr_query->addParam("cadena", " " . $string);
			$solr_query->addParam("q.op", "AND");
		} else {
			$solr_query->setQuery($sele);
		}
	}
	$solr_query->addParam("df", $search_field);

	// Records and Paging
	$solr_query->setRows($rows);
	$solr_query->setStart($start);

	// Fields to Show
	if ($lang == "es")
		$kp="PK";
	else if ($lang == "en")
		$kp="KM";
	else
		$kp="KP";
	if ($debug!=0) {
		$solr_query->addField("field_search");
		$solr_query->addField("field_search2");
		$solr_query->addField("name_eu_sort");
		$solr_query->addField("name_es_sort");
	}
	if (substr($search_type, 0, 2) == "pk") $solr_query->addField("display_name:name");
	$solr_query->addField($field_sc);
	$solr_query->addField("b5m_id:code");
	$solr_query->addField("GFA_code1:idnombre1");
	$solr_query->addField("GFA_code2:idnombre2");
	$solr_query->addField("GFA_id:idnombre");
	$solr_query->addField("display_name:name_" . $lang2);
	$solr_query->addField("type:" . $field_type);
	$solr_query->addField("class:clas_" . $lang2);
	$solr_query->addField("description:desc_" . $lang2);
	$solr_query->addField("riverbasin:" . $field_basin);
	$solr_query->addField("city:" . $field_muni);
	$solr_query->addField("street:" . $field_street);
	$solr_query->addField("door_number");
	$solr_query->addField("bis");
	$solr_query->addField("postcode");
	$solr_query->addField("building_name:nomedif_" . $lang2);
	$solr_query->addField("road:" . $field_road);
	$solr_query->addField("GFA_idcarre:id_carre");
	$solr_query->addField("GFA_idut:id_pk");
	$solr_query->addField($kp . ":kil");
	$solr_query->addField("way:sentido_" . $lang);
	$solr_query->addField("boundingbox");
	$solr_query->addField("lon:xcen");
	$solr_query->addField("lat:ycen");
	$solr_query->addField("map_link:urlmapa");
	$solr_query->addField("synonyms:sinonimos");

	// Debug
	$solr_query->setEchoParams($echo_params);
	if ($debug1 == "true")
		$solr_query->setShowDebugInfo($debug1);
	if ($omit_header == "true")
		$solr_query->setOmitHeader($omit_header);

	// Query
	$response_query = $solr_client->query($solr_query);
	$response = $response_query->getResponse();
	$count = $response["response"]["numFound"];
}

// Launch the Query
query_function("topo1");
if ($count == 0 || $count == -2) query_function("addr1");
if ($count == 0) query_function("topo2");
if ($count == 0) query_function("addr2");
if ($count == 0) query_function("topo3");
if ($count == 0) query_function("addr3");
if ($count == 0 || $count == -2) query_function("pk1");
if ($count == 0) query_function("pk2");
if ($count == 0) query_function("pk3");

// See if only the number of records has been requested
if (empty($numfound) || $numfound != "1") $numfound = 0;
if ($numfound == 1) {
	$doc = array();
	$doc["response"]["numFound"] = $response["response"]["numFound"];
	$response = $doc;
}

// Show the types, if $types = 1
header("Content-type: text/plain;charset=utf-8");
if ($count == -2) {
	$response = array();
	$response["response"]["numFound"] = count($types_a);
	$i = 0;
	$doc = array();
	foreach ($types_a as &$type_v) {
		$doc[$i] = $type_v;
		$i++;
	}
	array_multisort($doc);
	$response["response"]["types"] = $doc;
}

// Test if they are coordinates with the initial string q
$lon = "";
$lat = "";
$que = "";

$stringc1 = preg_replace("#N#", " ", $q);
$stringc1 = preg_replace("#W#", " " , $stringc1);
$stringc1 = preg_replace("#O#", " " , $stringc1);
$stringc = preg_replace("#-#", " " , $stringc1);
$pattern1 = "/^(?<longitude>(\s*)[-]?[1-2](?:\.[0-9]{1,10})?)(\s+)(?<latitude>[-]?[4][2-3](?:\.[0-9]{1,10})?)(\s*)$/";
$pattern2 = "/^(?<latitude>(\s*)[-]?[4][2-3](?:\.[0-9]{1,10})?)(\s+)(?<longitude>[-]?[1-2](?:\.[0-9]{1,10})?)(\s*)$/";
if (preg_match($pattern1, $stringc) || preg_match($pattern2, $stringc)) {
	$que = "DECIMAL";
  if (!preg_match($pattern1, $stringc, $matches))
  	preg_match($pattern2, $stringc, $matches);
  if (substr(trim($matches["longitude"]), 0, 1) != "-")
		$longitude = "-" . trim($matches["longitude"]);
  else
		$longitude = trim($matches["longitude"]);

  $lon = $longitude;
  $lat = trim($matches["latitude"]);
} else {
  $pattern1 = "/^(?<X>(\s*)[-]?[5-6][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(\s+)(?<Y>[-]?[4][7-8][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(\s*)$/";
  $pattern2 = "/^(?<Y>(\s*)[-]?[4][7-8][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(\s+)(?<X>[-]?[5-6][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(\s*)$/";
  if (preg_match($pattern1, $stringc) || preg_match($pattern2, $stringc)) {
  	$que = "ETRS89";
    if (!preg_match($pattern1, $stringc, $matches))
  		preg_match($pattern2, $stringc, $matches);

    $lon = trim($matches["X"]);
    $lat = trim($matches["Y"]);
  } else {
    $pattern1 = "/^(?<longitude>(\s*)[-]?[1-2](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s+)(?<latitude>[-]?[4][2-3](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s*)$/";
    $pattern2 = "/^(?<latitude>(\s*)[-]?[4][2-3](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s+)(?<longitude>[-]?[1-2](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s*)$/";
    if (preg_match($pattern1, $stringc) || preg_match($pattern2, $stringc)) {
    	$que = "SEXAGESIMAL";
      if (!preg_match($pattern1, $stringc, $matches))
      	preg_match($pattern2, $stringc, $matches);
      if (substr(trim($matches["longitude"]), 0, 1) != "-")
				$longitude = "-" . trim($matches["longitude"]);
      else
				$longitude = trim($matches["longitude"]);

      $lon = $longitude;
      $lat = trim($matches["latitude"]);
    }
  }
}

// If they are coordinates the search is aborted
if ($lon != "" and $lat != "" and $que != "") {
	echo "type=" . $que . " lon=" . $lon . " lat=" . $lat;
	exit;
}

// If there are no parameters (count = -1), refer to documentation
if ($count == -1) {
	if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if ($lang == "es")
		$base_url = "https://b5mdev/web5000/es/api-rest";
	else if ($lang == "en")
		$base_url = "https://b5mdev/web5000/en/rest-api";
	else
		$base_url = "https://b5mdev/web5000/eu/rest-apia";
	$response = (object) [
    'help' => 'Documentation',
    'url' => $base_url
  ];
}

// Result Display
if ((strtolower($format) == "php") || (strtolower($format) == "phps")) {
	header("Content-type: text/plain;charset=utf-8");
	print_r($response);
} else {
	$jsonres = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	if (strtolower($format) == "xml") {
		header("Content-type: application/xml;charset=utf-8");
		print_r(json2xml($jsonres));
	} else {
		header("Content-type: application/json;charset=utf-8");
		print_r($jsonres);
	}
}
?>
