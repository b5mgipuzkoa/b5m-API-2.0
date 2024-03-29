<?php
// Municipal Boundaries

// Connection Path
define("SOLR_SERVER_PATH", "solr/b5mboundaries");

// Includes
include_once("includes/config.php");
include_once("includes/subrulesolr.php");
include_once("includes/json2xml.php");

// Input Parameters
$lang = @$_REQUEST["lang"];
$q = @$_REQUEST["q"];
$format = @$_REQUEST["format"];
$limit = @$_REQUEST["limit"];
$debug = @$_REQUEST["debug"];

// Language Encoding
if ($lang != "eu" && $lang != "es" && $lang != "en") {
	$lang = "eu";
}
if ($lang == "es") {
	$lang2 = "es";
} else {
	$lang2 = "eu";
}

// Record Limit
if (empty($limit)) $limit = 10000;

// Debug
if (empty($debug)) $debug = "0";

// Search, Order and Display Fields
$field_en = "encl";
$field_mu = "muni";
if ($lang2 == "es") {
	$field_bo1 = $field_en . "1_es";
	$field_bo2 = $field_mu . "1_es";
	$field_bo3 = $field_en . "2_es";
	$field_bo4 = $field_mu . "2_es";
	$field_map = "map_link_es";
	$field_comment = "comment_es";
} else {
	$field_bo1 = $field_en . "1_eu";
	$field_bo2 = $field_mu . "1_eu";
	$field_bo3 = $field_en . "2_eu";
	$field_bo4 = $field_mu . "2_eu";
	$field_map = "map_link_eu";
	$field_comment = "comment_eu";
}

$options = array
(
	"hostname" => SOLR_SERVER_HOSTNAME,
	"port"     => SOLR_SERVER_PORT,
	"path"	   => SOLR_SERVER_PATH,
	"proxy_host" => SOLR_PROXY_HOST,
	"proxy_port" => SOLR_PROXY_PORT
);

$solr_client = new SolrClient($options);

// Query
$solr_query = new SolrQuery();
$solr_query->setQuery("*:*");
$solr_query->setRows($limit);

// If there is only one character, the subrulesolr encoding is not used
if (strlen($q) == 1) {
	$string = strtoupper($q);
} else {
	// Search String Encoding
	$string = subrulesolr($q);
}
$field_bu1 = $field_en . "1_eu_aux";
$field_bu2 = $field_mu . "1_eu_aux";
$field_bu3 = $field_en . "1_es_aux";
$field_bu4 = $field_mu . "1_es_aux";
$field_bu5 = "id_enclave";
if (is_numeric($string)) {
	$filter = $field_bu5 . ":" . $q;
} else {
	$string = "*" . $string . "*";
	$q = str_replace("_", "-", $q);
	$q = str_replace("onati", "oñati", $q);
	$q = str_replace("Onati", "oñati", $q);
	$q = str_replace("ONATI", "oñati", $q);
	$filter = $field_mu . "1_eu:\"" . $q . "\" or " . $field_mu . "1_es:\"" . $q . "\"";
}
$solr_query->addFilterQuery($filter);
$solr_query->setStart(0);
$solr_query->addField("*");
$solr_query->setOmitHeader(true);

// Sort
$sort_1 = $field_bo2 . "_sort";
$sort_2 = $field_bo1 . "_sort";
$solr_query->addSortField("main_muni1", SolrQuery::ORDER_DESC);
$solr_query->addSortField($sort_1, SolrQuery::ORDER_ASC);
$solr_query->addSortField($sort_2, SolrQuery::ORDER_ASC);

// Debug
if ($debug != 0) {
	$solr_query->setEchoParams("all");
	$solr_query->setShowDebugInfo(true);
	$solr_query->setOmitHeader(false);
}

$response_query = $solr_client->query($solr_query);
$response = $response_query->getResponse();

// If nothing is found, a broader search is launched
if ($response["response"]["numFound"] == 0) {
	$filter2 = $field_bu1 . ":" . $string . " or " . $field_bu2 . ":" . $string . " or " . $field_bu3 . ":" . $string . " or " . $field_bu4 . ":" . $string;
	$solr_query->removeFilterQuery($filter);
	$solr_query->addFilterQuery($filter2);
	$response_query = $solr_client->query($solr_query);
	$response = $response_query->getResponse();
}

// Create a new array
$doc = array();

// Debug
if ($debug != 0) {
	$doc["responseHeader"]["status"] = $response["responseHeader"]["status"];
	$doc["responseHeader"]["QTime"] = $response["responseHeader"]["QTime"];
	$doc["responseHeader"]["params"]["q"] = $response["responseHeader"]["params"]["q"];
	$doc["responseHeader"]["params"]["df"] = $response["responseHeader"]["params"]["df"];
	$doc["responseHeader"]["params"]["indent"] = $response["responseHeader"]["params"]["indent"];
	$doc["responseHeader"]["params"]["omitHeader"] = $response["responseHeader"]["params"]["omitHeader"];
	$doc["responseHeader"]["params"]["echoParams"] = $response["responseHeader"]["params"]["echoParams"];
	$doc["responseHeader"]["params"]["fl"] = $response["responseHeader"]["params"]["fl"];
	$doc["responseHeader"]["params"]["start"] = $response["responseHeader"]["params"]["start"];
	$doc["responseHeader"]["params"]["fq"] = $response["responseHeader"]["params"]["fq"];
	$doc["responseHeader"]["params"]["sort"] = $response["responseHeader"]["params"]["sort"];
	$doc["responseHeader"]["params"]["rows"] = $response["responseHeader"]["params"]["rows"];
	$doc["responseHeader"]["params"]["version"] = $response["responseHeader"]["params"]["version"];
	$doc["responseHeader"]["params"]["wt"] = $response["responseHeader"]["params"]["wt"];
}

// Response
$doc["response"]["numFound"] = $response["response"]["numFound"];
$doc["response"]["start"] = $response["response"]["start"];

// Covering Enclaves
if ($response["response"]["numFound"] > 0) {
	for ($i = 0; $i < count($response["response"]["docs"]); $i++) {
		$doc["response"]["docs"][$i]["id_enclave"] = $response["response"]["docs"][$i]["id_enclave"];
		$doc["response"]["docs"][$i]["enclave1"] = $response["response"]["docs"][$i]["encl1_" . $lang2];
		$doc["response"]["docs"][$i]["municipality1"] = $response["response"]["docs"][$i]["muni1_" . $lang2];
		$doc["response"]["docs"][$i]["territory1"] = $response["response"]["docs"][$i]["terr1_" . $lang2];

		// Searching Agreeements
		$idp1 = $response["response"]["docs"][$i]["id"];
		$solr_query2 = new SolrQuery();
		$solr_query2->setQuery("*:*");
		$solr_query2->setRows($limit);
		$solr_query2->addFilterQuery("id_parent:" . $idp1 . "");
		$solr_query2->setStart(0);
		$solr_query2->addField("*");
		$solr_query2->setOmitHeader(true);
		$sort_3 = $field_bo4 . "_sort";
		$sort_4 = $field_bo3 . "_sort";
		$solr_query2->addSortField($sort_3, SolrQuery::ORDER_ASC);
		$solr_query2->addSortField($sort_4, SolrQuery::ORDER_ASC);
		$response_query2 = $solr_client->query($solr_query2);
		$response2 = $response_query2->getResponse();
		if (is_array($response2["response"]["docs"]) == TRUE) {
			for ($j = 0; $j < count($response2["response"]["docs"]); $j++) {
				$doc["response"]["docs"][$i]["agreements"][$j]["id_agreement"] = $response2["response"]["docs"][$j]["id_acta"];
				$doc["response"]["docs"][$i]["agreements"][$j]["enclave2"] = $response2["response"]["docs"][$j]["encl2_" . $lang2];
				$doc["response"]["docs"][$i]["agreements"][$j]["municipality2"] = $response2["response"]["docs"][$j]["muni2_" . $lang2];
				$doc["response"]["docs"][$i]["agreements"][$j]["territory2"] = $response2["response"]["docs"][$j]["terr2_" . $lang2];
				$doc["response"]["docs"][$i]["agreements"][$j]["agreement"] = $response2["response"]["docs"][$j]["agreement"];
				$doc["response"]["docs"][$i]["agreements"][$j]["agreement_file_type"] = $response2["response"]["docs"][$j]["agreement_file_type"];
				$doc["response"]["docs"][$i]["agreements"][$j]["agreement_link_type"] = $response2["response"]["docs"][$j]["agreement_link_type"];
				$doc["response"]["docs"][$i]["agreements"][$j]["agreement_size_kb"] = $response2["response"]["docs"][$j]["agreement_size_kb"];
				$doc["response"]["docs"][$i]["agreements"][$j]["fieldlog"] = $response2["response"]["docs"][$j]["fieldlog"];
				$doc["response"]["docs"][$i]["agreements"][$j]["fieldlog_file_type"] = $response2["response"]["docs"][$j]["fieldlog_file_type"];
				$doc["response"]["docs"][$i]["agreements"][$j]["fieldlog_link_type"] = $response2["response"]["docs"][$j]["fieldlog_link_type"];
				$doc["response"]["docs"][$i]["agreements"][$j]["fieldlog_size_kb"] = $response2["response"]["docs"][$j]["fieldlog_size_kb"];
				$doc["response"]["docs"][$i]["agreements"][$j]["comment"] = $response2["response"]["docs"][$j][$field_comment];
				$doc["response"]["docs"][$i]["agreements"][$j]["map_link"] = $response2["response"]["docs"][$j]["map_link_agreement_" . $lang2];
				$doc["response"]["docs"][$i]["agreements"][$j]["number_of_boundarystones"] = $response2["response"]["docs"][$j]["number_of_boundarystones"];

				// Searching Boundary Stones
				$idp2 = $response2["response"]["docs"][$j]["id"];
				$solr_query3 = new SolrQuery();
				$solr_query3->setQuery("*:*");
				$solr_query3->setRows($limit);
				$solr_query3->addFilterQuery("id_parent:" . $idp2 . "");
				$solr_query3->setStart(0);
				$solr_query3->addField("*");
				$solr_query3->setOmitHeader(true);
				$sort_3 = "number";
				$solr_query3->addSortField($sort_3, SolrQuery::ORDER_ASC);
				$response_query3 = $solr_client->query($solr_query3);
				$response3 = $response_query3->getResponse();
				if (is_array($response3["response"]["docs"]) == TRUE) {
					for ($k = 0; $k < count($response3["response"]["docs"]); $k++) {
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["id_boundarystone"] = $response3["response"]["docs"][$k]["id_mojon"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["number"] = $response3["response"]["docs"][$k]["number"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["location"] = $response3["response"]["docs"][$k]["location"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["type"] = $response3["response"]["docs"][$k]["boundarystone_type"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["type_name"] = $response3["response"]["docs"][$k]["boundarystone_type_" . $lang];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["accuracy"] = $response3["response"]["docs"][$k]["accuracy"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["accuracy_name"] = $response3["response"]["docs"][$k]["accuracy_" .$lang];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["accuracy_description"] = $response3["response"]["docs"][$k]["accuracy_description_" . $lang];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["comment"] = $response3["response"]["docs"][$k]["boundarystone_comment_" . $lang];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["relationship"] = $response3["response"]["docs"][$k]["relationship"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["relationship_comment"] = $response3["response"]["docs"][$k]["relationship_comment"];
						$doc["response"]["docs"][$i]["agreements"][$j]["boundarystones"][$k]["map_link"] = $response3["response"]["docs"][$k]["map_link_boundarystone_" . $lang2];
					}
				}
			}
		}
	}
}

// Output Format (JSON by default)
header('Access-Control-Allow-Origin: *');
if ((strtolower($format) == "php") || (strtolower($format) == "phps")) {
	header("Content-type: text/plain;charset=utf-8");
	print_r($doc);
} else {
	$jsonres = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	if (strtolower($format) == "xml") {
		header("Content-type: application/xml;charset=utf-8");
		print_r(json2xml($jsonres));
	} else {
		header("Content-type: application/json;charset=utf-8");
		print_r($jsonres);
	}
}
?>
