<?php
// Municipal Cartography

// Connection Path
define("SOLR_SERVER_PATH", "solr/b5mcartomuni");

// Includes
include_once("includes/config.php");
include_once("includes/subrulesolr.php");
include_once("includes/json2xml.php");

// Input Parameters
$lang = @$_REQUEST["lang"];
$municipality = @$_REQUEST["municipality"];
$sort = @$_REQUEST["sort"];
$format = @$_REQUEST["format"];
$limit = @$_REQUEST["limit"];

// Sort Filter
$sort = str_replace("kodea", "1", strtolower($sort));
$sort = str_replace("código", "1", strtolower($sort));
$sort = str_replace("codigo", "1", strtolower($sort));
$sort = str_replace("code", "1", strtolower($sort));
$sort = str_replace("udalerria", "2", strtolower($sort));
$sort = str_replace("municipio", "2", strtolower($sort));
$sort = str_replace("municipality", "2", strtolower($sort));

// Search, Order and Display Fields
if ($lang == "es") {
	$field_muni = "muni_es";
	$field_name = "nombre_es";
	$field_owner = "propietario_es";
	$field_map = "map_link_es";
} else {
	$field_muni = "muni_eu";
	$field_name = "nombre_eu";
	$field_owner = "propietario_eu";
	$field_map = "map_link_eu";
}
$field_company = "empresa";

// Sort
if ($sort == "1" || $sort == "2") {
	if ($sort == "1") {
		$sort_field = "codmuni";
	} else {
		$sort_field = $field_muni . "_sort";
	}
} else {
	$sort_field = "b5mcode";
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

$solr_query = new SolrQuery();

// Query
$solr_query->setQuery("*:*");
if (!empty($municipality)) {
	// Search String Encoding
	$string = subrulesolr($municipality);
	if (is_numeric($string)) {
		$filter = "codmuni:" . $municipality;
	} else {
		$municipality = str_replace("_", "-", $municipality);
		$municipality = str_replace("onati", "oñati", $municipality);
		$municipality = str_replace("Onati", "oñati", $municipality);
		$municipality = str_replace("ONATI", "oñati", $municipality);
		$filter = $field_muni . ":\"" . $municipality . "\"";
	}
	$solr_query->addFilterQuery($filter);
}

$solr_query->setStart(0);
$solr_query->setRows($limit);

// Fields to Show
$solr_query->addField("GFA_code:b5mcode");
$solr_query->addField("GFA_codmunicipality:codmuni");
$solr_query->addField("municipality:" . $field_muni);
$solr_query->addField("name:" . $field_name);
$solr_query->addField("owner:" . $field_owner);
$solr_query->addField("company:" . $field_company);
$solr_query->addField("scale:escala");
$solr_query->addField("digitalization_date:f_digitalizacion");
$solr_query->addField("survey_date:f_levanoriginal");
$solr_query->addField("update_date:f_ultactua");
$solr_query->addField("map_link:" . $field_map);

// Omitting Header
$solr_query->setOmitHeader(true);

// Sorting
$solr_query->addSortField($sort_field, SolrQuery::ORDER_ASC);

// Response
$response_query = $solr_client->query($solr_query);
$response = $response_query->getResponse();

// If nothing is found, a broader search is launched
if ($response["response"]["numFound"] == 0) {
	$filter2 = $field_muni . "_aux:" . $string . "*";
	$solr_query->removeFilterQuery($filter);
	$solr_query->addFilterQuery($filter2);
	$response_query = $solr_client->query($solr_query);
	$response = $response_query->getResponse();
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
