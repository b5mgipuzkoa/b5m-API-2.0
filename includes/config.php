<?php
// Solr Server Domain Name
define("SOLR_SERVER_HOSTNAME", "172.23.128.130");

// HTTP Port for the Connection
define("SOLR_SERVER_PORT", 8983);

// Proxy
if ($_SERVER['SERVER_NAME'] == SOLR_SERVER_HOSTNAME) {
	define("SOLR_PROXY_HOST", "");
	define("SOLR_PROXY_PORT", "");
} else {
	define("SOLR_PROXY_HOST", "proxy.sare.gipuzkoa.net");
	define("SOLR_PROXY_PORT", "8080");
}
?>
