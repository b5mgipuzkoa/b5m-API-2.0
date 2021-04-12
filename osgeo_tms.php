<?php
// OSGeo TMS Service Server API

// Tile doesn't exist
function message_404($message) {
	header("HTTP/1.0 404 Not Found");
	header("Content-type: text/xml");
	print "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
	print "<TileMapServerError>\n";
	print "  <Message>$message</Message>\n";
	print "</TileMapServerError>\n";
}

// Service
$server = $_SERVER['SERVER_NAME'];

// Detect browser language
$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
if ($lang == "es") {
	$lang2 = "es";
} else {
	$lang2 = "eu";
}

// See if referer has language
if(isset($_SERVER['HTTP_REFERER'])) {
	$referer = $_SERVER['HTTP_REFERER'];
	if (preg_match("/\/es\//i", $referer)) {
		$lang2 = "es";
	} else if (preg_match("/\/eu\//i", $referer)) {
		$lang2 = "eu";
	}
}

// Input parameters
if (isset($_REQUEST['osgeo'])) $osgeo = $_REQUEST['osgeo']; else $osgeo = "";
if (isset($_REQUEST['service'])) $service = $_REQUEST['service']; else $service = "";
if (isset($_REQUEST['version'])) $version = $_REQUEST['version']; else $version = "";
if (isset($_REQUEST['tileset'])) $tileset = $_REQUEST['tileset']; else $tileset = "";
if (isset($_REQUEST['z'])) $z = $_REQUEST['z']; else $z = "";
if (isset($_REQUEST['x'])) $x = $_REQUEST['x']; else $x = "";
if (isset($_REQUEST['y'])) $y = $_REQUEST['y']; else $y = "";

// Variables
$home = "/home9/tiles";
$home_dev = "/home9/mapcache2/b5m";
$api1 = "api";
$api2 = "2.0";
$type = substr($tileset, 0, 3);
$tilemapservice = "tilemapservice.xml";
$tileset_dir = "virtual";
$tileset_file = "tileset_";
$tileset_file2 = "tileset.xml";

// Service
if ($service == "") {
	$r_uri = $_SERVER['REQUEST_URI'];
	$service = "tms";
	if (substr($r_uri, strlen($r_uri)-1, 1) == "/" ) {
		header("Location: " . $service);
	} else {
		header("Location: " . $osgeo . "/" . $service);
	}
}

if ($service != "tms") {
	message_404("Service $service not supported");
	exit();
}

// Version
if ($version == "" || $version == "root.xml") {
	$version = "1.0.0";
	header("Content-type: text/xml");
	print "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
	print "<Services>\n";
	print "  <TileMapService title=\"Gipuzkoa Tile Map Service\" version=\"1.0.0\" href=\"https://$server/$api1/$api2/$osgeo/$service/$version/\" />\n";
	print "</Services>\n";
	exit();
}

if ($version == "1.0.0" && $tileset == "") {
	$tms_file = "$home/$version/$tilemapservice";
	if (file_exists($tms_file)) {
		header("Content-type: text/xml");
		readfile($tms_file);
	} else {
		message_404("The tilemapservice file [ $osgeo/$service/$version/$tilemapservice ] does not exist");
	}
	exit();
} else if ($version != "1.0.0" && $tileset == "") {
	message_404("Version $version not supported");
	exit();
}

// Tileset
if (($z == "") || ($z == $tileset_file2)) {
	$tileset_path = "$home/$version/$tileset_dir/$tileset_file$tileset".".xml";
	if (file_exists($tileset_path)) {
		header("Content-type: text/xml");
		readfile($tileset_path);
	} else {
		message_404("The tileset file [ $osgeo/$service/$version/$tileset/$tileset_file2 ] does not exist");
	}
	exit();
}

// Location
if (($x == "") || ( $y == "" )) {
	if ( $x == "" ) {
		$xy = $z;
	} else {
		$xy = "$z/$x";
	}
	message_404("The tileset file [ $osgeo/$service/$version/$tileset/$xy ] does not exist");
	exit();
}

// Development version
if ($version == "1.0.0_desa") {
	$ruta = "$home_dev/$tileset/$z/$x/$y";
} else {
	$ruta = "$home/$version/$tileset/$z/$x/$y";
}

// Definition of the action to perform according to the type of tileset
if (in_array($type, array("mam", "ort", "teu", "tes", "heu", "hes", "pap", "err", "pk2"))) {
	// Image is served directly
	if (file_exists($ruta)) {
		$type_imagen = image_type_to_mime_type(exif_imagetype($ruta));
		header('Access-Control-Allow-Origin: *');
		header('Content-Type: '.$type_imagen);
		if ($fp = @fopen($ruta, 'rb')) {
			header('Content-Length: ' . filesize($ruta));
			fpassthru($fp);
		}
	} else {
		message_404("You requested a map tile [ $osgeo/$service/$version/$tileset/$z/$x/$y ] that does not exist");
		exit();
	}
} else if (in_array($type, array("map", "meu", "mes", "hib", "oeu", "oes"))) {
	$type2 = substr($ruta, strlen($tileset)-3, 3);

	// Two images overlappping
	if ($type == "meu") {
		$ruta1 = str_replace('meu', 'mam', $ruta);
		$ruta2 = str_replace('meu', 'teu', $ruta);
	} else if ($type == "mes") {
		$ruta1 = str_replace('mes', 'mam', $ruta);
		$ruta2 = str_replace('mes', 'tes', $ruta);
	} else if ($type == "map") {
		$ruta1 = str_replace('map', 'mam', $ruta);
		$ruta2 = str_replace('map', 't'.$lang2, $ruta);
	} else if ($type == "hib") {
		$ruta1 = str_replace('hib', 'ort', $ruta);
		$ruta1 = str_replace('png', 'jpg', $ruta1);
		$ruta2 = str_replace('hib', 'h'.$lang2, $ruta);
	} else if ($type == "oeu") {
		$ruta1 = str_replace('oeu', 'ort', $ruta);
		$ruta1 = str_replace('png', 'jpg', $ruta1);
		$ruta2 = str_replace('oeu', 'heu', $ruta);
	} else if ($type == "oes") {
		$ruta1 = str_replace('oes', 'ort', $ruta);
		$ruta1 = str_replace('png', 'jpg', $ruta1);
		$ruta2 = str_replace('oes', 'hes', $ruta);
	}
	if (file_exists($ruta1) && file_exists($ruta2)) {
		$type_imagen1=image_type_to_mime_type(exif_imagetype($ruta1));
		if ($type_imagen1 == "image/png" ) {
			$image1 = imagecreatefrompng($ruta1);
		} else {
			$image1 = imagecreatefromjpeg($ruta1);
		}
		$image2 = imagecreatefrompng($ruta2);

		imagealphablending($image1, true);
		imagesavealpha($image1, true);

		imagecopy($image1, $image2, 0, 0, 0, 0, 256, 256);

		header('Access-Control-Allow-Origin: *');
		header('Content-Type: image/png');
		imagepng($image1);

		imagedestroy($image1);
		imagedestroy($image2);
	} else {
		message_404("You requested a map tile [ $osgeo/$service/$version/$tileset/$z/$x/$y ] that does not exist");
		exit();
	}
} else {
	message_404("You requested a map tile [ $osgeo/$service/$version/$tileset/$z/$x/$y ] that does not exist");
	exit();
}
?>
