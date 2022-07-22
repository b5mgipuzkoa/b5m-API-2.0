<?php
// Toponymic Finder

// Dependencies
// cs2cs from PROJ: https://proj.org/apps/cs2cs.html

// Connection Path
define("SOLR_SERVER_PATH_A", array(
	"solr/b5mtopo",
	"solr/b5maddr",
	"solr/b5mpk"
));

// Memory
ini_set('memory_limit', '1024M');

// Includes
include_once("includes/config.php");
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
$word = strtolower(@$_REQUEST["word"]);
$listwords = strtolower(@$_REQUEST["listwords"]);

// map_link
$map_link_eu="/map-2021/mapa/";
$map_link_es="/map-2021/mapa/";
$map_link_en="/map-2021/mapa/";
if ($lang == "en") $map_link = $map_link_en;
else if ($lang == "es") $map_link = $map_link_es;
else $map_link = $map_link_eu;

// Language Coding
if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if ($lang != "eu" && $lang != "es" && $lang != "en") $lang = "en";
if ($lang == "en") $lang2 = "eu"; else $lang2 = $lang;

function query_function($search_type) {
	// Global Variables
	global $lang, $lang2, $q, $format, $debug, $rows, $start, $addr, $city, $riverbasin, $road, $street, $b5m_id, $type, $viewbox, $pt, $dist, $types, $nor, $sort, $word, $numfound;
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
	else {
		if ($lang == "eu") $type = 'posta-helbidea';
		else if ($lang == "es") $type = 'direccion-postal';
		else $type = 'postal-address';
		$addr = 0;
	}
	if ($street != "0") $addr = 1;
	if (empty($nor) || $nor != "1") $nor = 0;
	if (empty($types) || $types != "1") $types = 0;
	if (empty($road)) $road = 0;
	if (empty($riverbasin)) $riverbasin = 0;
	if (empty($b5m_id)) $b5m_id = 0;
	if (empty($city)) $city = 0;

	// If the search is by b5m_id and type is D_*, then is an address
	if ((substr($b5m_id, 0, 2) == "D_") && ($search_type == "topo1")) {
		return null;
	}

	// If the search is a KP, cannot contain certain parameters
	if ((substr($search_type, 0, 2) == "pk") && ($street == "0")) {
		$city = 0;
		$street = 0;
		$addr = 0;
	}

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
		"path"	   => SOLR_SERVER_PATH_A[$i],
		"proxy_host" => SOLR_PROXY_HOST,
		"proxy_port" => SOLR_PROXY_PORT
	);
	$solr_client = new SolrClient($options);

	// Query
	$solr_query = new SolrQuery();

	// Search Field and String
	if ($search_type == "topo4" || $search_type == "topo5") $search_field = "field_search2";
		else $search_field = "field_search";
	if ($search_type == "topo2" || $search_type == "addr2" || $search_type == "topo4" || $search_type == "pk2") {
		$string2 = explode(" ", $string);
		$string3 = "";
		foreach($string2 as $val) {
			if (strlen($val) > 0) {
				if (strlen($val) < 10) $string3 = $string3 . $val . "~1 ";
				else $string3 = $string3 . $val . "~ ";
			}
		}
		$string = $string3;
	} else if ($search_type == "addr3" || $search_type == "topo3" || $search_type == "topo5") {
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

	// Selection: if there is a word, only names that begin with that word
	$sele = "{!q.op=AND}";
	if ($word != "")
		$sele = "name_" . $lang2 . "_search2:" . strtolower(mb_substr($word, 0, 1)) . "*";
	else if ($q == "*")
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
	$field_name_s = "name_" . $lang2 . "_search";
	$field_street_s = "street_" . $lang2 . "_search";
	$field_address_s = "address_" . $lang2 . "_search";

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
			$obj = str_replace("_", " ", $obj);
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
		if (is_numeric($street)) $field_street2 = "codstreet:0"; else $field_street2 = $field_street;
		$street = str_replace("(", "", $street);
		$street = str_replace(")", "", $street);
		$solr_query->addFilterQuery($field_street2 . "_search:\"" . $street . "\"");
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
			$solr_query->addFilterQuery($field_muni2 . "_search:\"" . $city . "\"");
		}
	}
	if ($road != "0") {
		if (is_numeric($road)) $field_road="id_carre";
		$solr_query->addFilterQuery($field_road . ":\"" . $road . "\"");
		$solr_query->addFilterQuery("type_en:(\"kilometre point\" )");
		if (empty($pt)) {
			$solr_query->addSortField("kil_sort", $sort_asc);
			$solr_query->addSortField("sentido_sort", $sort_asc);
		}
	}
	if ($type != "0") {
		// Exceptions in types
		$type = str_replace("-", " ", $type);
		$type = str_replace("ñ", "n", $type);
		$type = str_replace("á", "a", $type);
		$type = str_replace("é", "e", $type);
		$type = str_replace("í", "i", $type);
		$type = str_replace("ó", "o", $type);
		$type = str_replace("ú", "u", $type);
		$type = str_replace("y o", "y/o", $type);
		$type = str_replace("eta edo", "eta/edo", $type);
		$type = str_replace("and or", "and/or", $type);
		if (preg_match("/\|/", $type) == 1) {
			$cc = explode("|", $type);
		} else {
			$cc = explode("_", $type);
		}
		$ccn = "(";
		foreach($cc as $val) {
			if (strlen($val) > 0) {
				$ccn = $ccn . "\"" . $val . "\"";
			}
		}
		$ccn = $ccn . ")";
		$solr_query->addFilterQuery($field_type_s . ":" . $ccn);
		if ($search_type == "topo1" || $search_type == "topo2" || $search_type == "topo3") {
			if ($q != "*") {
				$q = str_replace("(", "", $q);
				$q = str_replace(")", "", $q);
				$solr_query->addFilterQuery($field_name_s . ":\"" . $q . "\"");
				$sele = "{!q.op=AND}*";
			}
		}
		if ($search_type == "addr1" || $search_type == "addr2" || $search_type == "addr3") {
			if ($q != "*" && $street == 0) {
				$solr_query->addFilterQuery($field_street_s . ":\"" . $q . "\"");
				$sele = "{!q.op=AND}*";
			} else if ($q != "*" && $street != 0) {
				$solr_query->addFilterQuery($field_address_s . ":\"" . $q . "\"");
				$sele = "{!q.op=AND}*";
			}
		}
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
		$solr_query->addParam("main_string", $string);
		$solr_query->addField("distance:div(rint(product(geodist(),100)),100)");
		$solr_query->addParam("q.op", "AND");
	} else {
		if ($nor == "1") {
			$solr_query->setQuery("_query_:\"{!func}scale(query(\$main_string),1,100)\"");
			$solr_query->addFilterQuery("{!cache=false v=\$main_string}");
			$solr_query->addParam("main_string", " " . $string);
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
	$solr_query->addField("kilometre_point:kil");
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

// Coordinate Detection Function
function coor_detect($q) {
	global $lang, $map_link;
	global $response_coor, $count;

	$x = "";
	$y = "";
	$tco = "";

	$stringc1 = preg_replace("#N#", " ", $q);
	$stringc1 = preg_replace("#I#", " " , $stringc1);
	$stringc1 = preg_replace("#W#", " " , $stringc1);
	$stringc1 = preg_replace("#O#", " " , $stringc1);
	$stringc1 = preg_replace("#M#", " " , $stringc1);
	$stringc1 = preg_replace("#º#", " " , $stringc1);
	$stringc1 = preg_replace("#°#", " " , $stringc1);
	$stringc1 = preg_replace("#d#", " " , $stringc1);
	$stringc1 = preg_replace("#'#", " " , $stringc1);
	$stringc1 = preg_replace("#, #", "," , $stringc1);
	$stringc = preg_replace("#\"#", " " , $stringc1);
	$pattern1 = "/^(?<longitude>(\s*)[-]?[1-2](?:\.[0-9]{1,10})?)(?<delimeter>.)(?<latitude>[-]?[4][2-3](?:\.[0-9]{1,10})?)(\s*)$/";
	$pattern2 = "/^(?<latitude>(\s*)[-]?[4][2-3](?:\.[0-9]{1,10})?)(?<delimeter>.)(?<longitude>[-]?[1-2](?:\.[0-9]{1,10})?)(\s*)$/";
	if (preg_match($pattern1, $stringc) || preg_match($pattern2, $stringc)) {
		$tco = "epsg:4326";
	  if (!preg_match($pattern1, $stringc, $matches))
	  	preg_match($pattern2, $stringc, $matches);
	  if (substr(trim($matches["longitude"]), 0, 1) != "-")
			$longitude = "-" . trim($matches["longitude"]);
	  else
			$longitude = trim($matches["longitude"]);

	  $x = $longitude;
	  $y = trim($matches["latitude"]);
	} else {
	  $pattern1 = "/^(?<X>(\s*)[-]?[5-6][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(?<delimeter>.)(?<Y>[-]?[4][7-8][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(\s*)$/";
	  $pattern2 = "/^(?<Y>(\s*)[-]?[4][7-8][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(?<delimeter>.)(?<X>[-]?[5-6][0-9][0-9][0-9][0-9][0-9](?:\.[0-9]{1,10})?)(\s*)$/";
	  if (preg_match($pattern1, $stringc) || preg_match($pattern2, $stringc)) {
	  	$tco = "epsg:25830";
	    if (!preg_match($pattern1, $stringc, $matches))
	  		preg_match($pattern2, $stringc, $matches);

	    $x = trim($matches["X"]);
	    $y = trim($matches["Y"]);
	  } else {
	    $pattern1 = "/^(?<longitude>(\s*)[-]?[1-2](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s+.)(?<latitude>[-]?[4][2-3](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s*)$/";
	    $pattern2 = "/^(?<latitude>(\s*)[-]?[4][2-3](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s+.)(?<longitude>[-]?[1-2](?:\s+[0-9]{1,2})?(?:\s+[0-9]{1,2}(?:\.*[0-9]{1,10})?)?)(\s*)$/";
	    if (preg_match($pattern1, $stringc) || preg_match($pattern2, $stringc)) {
	    	$tco = "epsg:4326s";
	      if (!preg_match($pattern1, $stringc, $matches))
	      	preg_match($pattern2, $stringc, $matches);
	      if (substr(trim($matches["longitude"]), 0, 1) != "-")
					$longitude = "-" . trim($matches["longitude"]);
	      else
					$longitude = trim($matches["longitude"]);

	      $x = $longitude;
	      $y = trim($matches["latitude"]);
	    }
	  }
	}
	// Search by coordinates
	if ($x != "" and $y != "" and $tco != "") {
		if ($tco == "epsg:25830") {
			// epsg:25830
			$x_25830 = $x;
			$y_25830 = $y;

			// epsg:4326
			$coor_1 = shell_exec('echo "' . $x . ' ' . $y . '" | /usr/local/bin/cs2cs -f "%.5f" +init=epsg:25830 +to +init=epsg:4326 2> /dev/null');
			$coor_11 = explode("	", $coor_1);
			$x_4326 = $coor_11[0];
			$coor_12 = explode(" ", $coor_11[1]);
			$y_4326 = $coor_12[0];

			// epsg:4326 sexagesimal
			$coor_2 = shell_exec('echo "' . $x . ' ' . $y . '" | /usr/local/bin/cs2cs init=epsg:25830 +to +init=epsg:4326 2> /dev/null');
			$coor_21 = explode("	", $coor_2);
			$coor_211 = explode("'", $coor_21[0]);
			$coor_212 = explode('"', $coor_211[1]);
			$coor_213 = sprintf("%.1f", $coor_212[0]);
			$x_4326s = str_replace($coor_212[0], $coor_213, $coor_21[0]);
			$coor_22 = explode(" ", $coor_21[1]);
			$coor_221 = explode("'", $coor_22[0]);
			$coor_222 = explode('"', $coor_221[1]);
			$coor_223 = sprintf("%.1f", $coor_222[0]);
			$y_4326s = str_replace($coor_222[0], $coor_223, $coor_22[0]);
		} else if ($tco == "epsg:4326") {
			// epsg:4326
			$x_4326 = $x;
			$y_4326 = $y;

			// epsg:25830
			$coor_1 = shell_exec('echo "' . $x . ' ' . $y . '" | /usr/local/bin/cs2cs -f "%.2f" +init=epsg:4326 +to +init=epsg:25830 2> /dev/null');
			$coor_11 = explode("	", $coor_1);
			$x_25830 = $coor_11[0];
			$coor_12 = explode(" ", $coor_11[1]);
			$y_25830 = $coor_12[0];

			// epsg:4326 sexagesimal
			$coor_2 = shell_exec('echo "' . $x . ' ' . $y . '" | /usr/local/bin/cs2cs init=epsg:4326 +to +init=epsg:4326 2> /dev/null');
			$coor_21 = explode("	", $coor_2);
			$coor_211 = explode("'", $coor_21[0]);
			$coor_212 = explode('"', $coor_211[1]);
			$coor_213 = sprintf("%.1f", $coor_212[0]);
			$x_4326s = str_replace($coor_212[0], $coor_213, $coor_21[0]);
			$coor_22 = explode(" ", $coor_21[1]);
			$coor_221 = explode("'", $coor_22[0]);
			$coor_222 = explode('"', $coor_221[1]);
			$coor_223 = sprintf("%.1f", $coor_222[0]);
			$y_4326s = str_replace($coor_222[0], $coor_223, $coor_22[0]);
		} else {
			// epsg:4326s
			$x_1 = explode(" ", $x);
			if ($x_1[0] < 0)
				$xt = "W";
			else
				$xt = "E";
			$y_1 = explode(" ", $y);
			if ($y_1[0] > 0)
				$yt = "N";
			else
				$yt = "S";
			$x_4326s = str_replace("-", "", $x_1[0]) . "d" . $x_1[1] . "'" . $x_1[2] . '"' . $xt;
			$y_4326s = str_replace("-", "", $y_1[0]) . "d" . $y_1[1] . "'" . $y_1[2] . '"' . $yt;

			// epsg:25830
			$coor_1 = shell_exec('echo "' . str_replace("\"", "\\\"", $x_4326s) . ' ' . str_replace("\"", "\\\"", $y_4326s) . '" | /usr/local/bin/cs2cs -f "%.2f" +init=epsg:4326 +to +init=epsg:25830 2> /dev/null');
			$coor_11 = explode("	", $coor_1);
			$x_25830 = $coor_11[0];
			$coor_12 = explode(" ", $coor_11[1]);
			$y_25830 = $coor_12[0];

			// epsg:4326
			$coor_2 = shell_exec('echo "' . str_replace("\"", "\\\"", $x_4326s) . ' ' . str_replace("\"", "\\\"", $y_4326s) . '" | /usr/local/bin/cs2cs -f "%.5f" +init=epsg:4326 +to +init=epsg:4326 2> /dev/null');
			$coor_21 = explode("	", $coor_2);
			$x_4326 = $coor_21[0];
			$coor_22 = explode(" ", $coor_21[1]);
			$y_4326 = $coor_22[0];
		}
		if ($lang == "eu") $type = 'koordenatuak';
		else if ($lang == "es") $type = 'coordenadas';
		else $type = 'coordinates';
		$type2 = strtoupper(substr($type, 0 , 1)) . substr($type, 1, strlen($type)-1);
		$coord_25830 = "ETRS89-UTM30N (EPSG:25830)";
		$coord_4326 = "WGS84 (EPSG:4326)";
		$coord_4326s = "WGS84 sexag. (EPSG:4326)";
		$coord_display_name = $type2 . " - " . $coord_25830 . ": " . $x_25830 . ", " . $y_25830 . " / "  . $coord_4326 . ": " . $x_4326 . ", " . $y_4326 . " / "  . $coord_4326s . ": " . $x_4326s . ", " . $y_4326s;
		$doc = array();
		$doc["response"]["numFound"] = 1;
		$doc["response"]["start"] = 0;
		$doc["response"]["maxScore"] = 1;
		$doc["response"]["docs"][0]["boundingbox"][0] = $x_4326;
		$doc["response"]["docs"][0]["boundingbox"][1] = $y_4326;
		$doc["response"]["docs"][0]["boundingbox"][2] = $x_4326;
		$doc["response"]["docs"][0]["boundingbox"][3] = $y_4326;
		$doc["response"]["docs"][0]["display_name"] = $coord_display_name;
		$doc["response"]["docs"][0]["b5m_id"] = "L_" . $x_4326 . "_" . $y_4326 . "_WGS84";
		$doc["response"]["docs"][0]["map_link"] = "https://" . $_SERVER['SERVER_NAME'] . $map_link . $doc["response"]["docs"][0]["b5m_id"];
		$doc["response"]["docs"][0]["type"] = $type;
		$doc["response"]["docs"][0]["coords_epsg:25830"][0] = $x_25830;
		$doc["response"]["docs"][0]["coords_epsg:25830"][1] = $y_25830;
		$doc["response"]["docs"][0]["coords_epsg:4326"][0] = $x_4326;
		$doc["response"]["docs"][0]["coords_epsg:4326"][1] = $y_4326;
		$doc["response"]["docs"][0]["coords_epsg:4326_sexagesimal"][0] = $x_4326s;
		$doc["response"]["docs"][0]["coords_epsg:4326_sexagesimal"][1] = $y_4326s;
		$response_coor = $doc;
	}
}

// Coordinate detection
coor_detect($q);

if ($response_coor) {
	// Show coordinates
	$response = $response_coor;
} else if ($listwords == 1 && ($type == "eraikina" || $type == "edificio" || "$type" == "building") && ($city == "")) {
	// List of first words, case of type=building
	$response = array();
	$range_a = array();
	$range_a = array_merge(range(1, 9), range('A', 'Z'));
	$eli = 0;
	foreach ($range_a as $elem) {
		$response["response"]["docs"][$eli]["display_name"] = $elem;
		$eli ++;
	}
	$response["response"]["docs"][$eli]["display_name"] = "Ñ";
	$eli ++;
	$count = $eli;
} else {
	// Launch the Query
	query_function("topo1");
	if (empty($word)) {
		if ($count == 0 || $count == -2) query_function("addr1");
		if ($count == 0) query_function("topo2");
		if ($count == 0) query_function("addr2");
		if ($count == 0) query_function("topo3");
		if ($count == 0) query_function("addr3");
		if ($count == 0) query_function("topo4");
		if ($count == 0) query_function("topo5");
		if ($count == 0 || $count == -2) query_function("pk1");
		if ($count == 0) query_function("pk2");
		if ($count == 0) query_function("pk3");
	}
}

// See if only the number of records has been requested
if (empty($numfound) || $numfound != "1") $numfound = 0;
if ($numfound == 1) {
	$doc = array();
	$doc["response"]["numFound"] = $response["response"]["numFound"];
	$response = $doc;
}

// Show the types, if $types = 1
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

// Show list of first words if requested
if ($listwords == 1) {
	$responsew = array();
	if ($count == 0) {
		$responsew["response"]["numFound"] = $count;
	} else {
		$doc = array();
		for($i = 0; $i < count($response["response"]["docs"]); $i++) {
			$str_name = mb_substr(mb_strtoupper($response["response"]["docs"][$i]["display_name"]), 0, 1);
			if ($str_name != 'Á' && $str_name != 'É' && $str_name != 'Í' && $str_name != 'Ó' && $str_name != 'Ú')
				$doc[$i] = $str_name;
		}
		$doc_unique = array_unique($doc);
		setlocale(LC_COLLATE, 'es_ES.utf8');
		function custom_sort($a, $b) {
  		return strcoll ($a, $b);
		}
		usort($doc_unique, 'custom_sort');
		$doc_unique2 = array();
		$j = 0;
		for($i = 0; $i < count($doc_unique); $i++) {
			if (! is_numeric($doc_unique[$i])) {
				$doc_unique2[$j] = $doc_unique[$i];
				$j++;
			}
		}
		for($i = 0; $i < count($doc_unique); $i++) {
			if (is_numeric($doc_unique[$i])) {
				$doc_unique2[$j] = $doc_unique[$i];
				$j++;
			}
		}
		$responsew["response"]["numFound"] = count($doc_unique2);
		$responsew["response"]["words"] = $doc_unique2;
	}
	$response = $responsew;
}

// If there are no parameters (count = -1), refer to documentation
if ($count == -1) {
	if (empty($lang)) $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
	if ($lang == "es")
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/es/api-rest/geographical-search-engine";
	else if ($lang == "en")
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/en/rest-api/buscador-geografico";
	else
		$base_url = "https://" . $_SERVER['SERVER_NAME'] . "/web5000/eu/rest-apia/bilatzaile-geografikoa";
	$response = (object) [
    'help' => 'Documentation',
    'url' => $base_url
  ];
}

// Output Format (JSON by default)
header('Access-Control-Allow-Origin: *');
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
