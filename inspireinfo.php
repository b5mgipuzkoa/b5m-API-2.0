<?php
// API to report available INSPIRE services

// Connection Path
define("SOLR_SERVER_PATH", "solr/b5minspire");

// Includes
include_once("includes/config.php");
include_once("includes/subrulesolr.php");
include_once("includes/json2xml.php");

// Input Parameters
$lang = @$_REQUEST["lang"];
$q = @$_REQUEST["q"];
$sort = @$_REQUEST["sort"];
$sort_type = @$_REQUEST["sort_type"];
$limit = @$_REQUEST["limit"];
$format = @$_REQUEST["format"];

// Language Encoding
if ($lang != "eu" && $lang != "es" && $lang != "en") {
	$lang = "eu";
}

// Sort Encoding
$sort_type = strtolower($sort_type);
if ($sort_type == "desc") {
	$sort_type = SolrQuery::ORDER_DESC;
} else {
	$sort_type = SolrQuery::ORDER_ASC;
}

// Record Limit
if (empty($limit)) $limit = 10000;

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
$field_1 = "name";
$field_bu1 = $field_1 . "_" . $lang . "_aux";
$field_bu2 = "id_inspire";
if (is_numeric($string)) {
	$filter = $field_bu2 . ":" . $string;
} else {
	$string = "*" . $string . "*";
	$filter = $field_bu1 . ":" . $string;
}
$solr_query->addFilterQuery("type:inspire_dataset");
$solr_query->addFilterQuery($filter);
$solr_query->setStart(0);
$solr_query->addField("*");
$solr_query->setOmitHeader(true);

// Sort
$sort_a = explode(",", $sort);
foreach ($sort_a as $sort_b) {
	$sort_c = "";
	if ($sort_b == "id_inspire") $sort_c = $sort_b;
	if ($sort_b == "order") $sort_c = $sort_b;
	if ($sort_b == "name") $sort_c = $sort_b . "_" . $lang . "_sort";
	if ($sort_b == "description") $sort_c = $sort_b . "_" . $lang;
	if ($sort_b == "author") $sort_c = $sort_b . "_" . $lang;
	if (!empty ($sort_c)) $solr_query->addSortField($sort_c, $sort_type);
}

$response_query = $solr_client->query($solr_query);
$response = $response_query->getResponse();

// Create a new array
$doc = array();
$doc["response"]["numFound"] = $response["response"]["numFound"];
$doc["response"]["start"] = $response["response"]["start"];

// Dataset Looping
for ($i = 0; $i < count($response["response"]["docs"]); $i++) {
	$doc["response"]["docs"][$i]["id_inspire"] = $response["response"]["docs"][$i]["id_inspire"];
	$doc["response"]["docs"][$i]["order"] = $response["response"]["docs"][$i]["order"];
	$doc["response"]["docs"][$i]["name"] = $response["response"]["docs"][$i]["name_" . $lang];
	$doc["response"]["docs"][$i]["description"] = $response["response"]["docs"][$i]["description_" . $lang];
	$doc["response"]["docs"][$i]["author"] = $response["response"]["docs"][$i]["author_" . $lang];
	$doc["response"]["docs"][$i]["legal_info_link"] = $response["response"]["docs"][$i]["legal_info_link_" . $lang];
	$doc["response"]["docs"][$i]["metadata_link"] = $response["response"]["docs"][$i]["metadata_link"];

	// File Search
	$idp1 = $response["response"]["docs"][$i]["id"];
	$solr_query2 = new SolrQuery();
	$solr_query2->setQuery("*:*");
	$solr_query2->setRows($limit);
	$solr_query2->addFilterQuery("id_parent:" . $idp1 . "");
	$solr_query2->setStart(0);
	$solr_query2->addField("*");
	$solr_query2->setOmitHeader(true);
	$response_query2 = $solr_client->query($solr_query2);
	$response2 = $response_query2->getResponse();
	if (is_array($response2["response"]["docs"]) == TRUE) {
		for ($j = 0; $j < count($response2["response"]["docs"]); $j++) {
			$doc["response"]["docs"][$i]["services"][$j]["id_service"] = $response2["response"]["docs"][$j]["id_service"];
			$doc["response"]["docs"][$i]["services"][$j]["service_type"] = $response2["response"]["docs"][$j]["service_type"];
			$doc["response"]["docs"][$i]["services"][$j]["service_link"] = $response2["response"]["docs"][$j]["service_link"];
			$doc["response"]["docs"][$i]["services"][$j]["service_metadata_link"] = $response2["response"]["docs"][$j]["service_metadata_link"];
		}
	}
}

// Output Format (JSON by default)
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
