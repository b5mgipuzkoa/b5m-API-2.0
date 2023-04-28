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
$home_dev = "/home9/mapcache1/b5m";
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
	$path = "$home_dev/$tileset/$z/$x/$y";
} else {
	$path = "$home/$version/$tileset/$z/$x/$y";
}

// Definition of the action to perform according to the type of tileset
// Equivalences with the basemap
// map -> oin
// mam -> oim
// meu -> oiu
// mes -> ois
// teu -> otu
// tes -> ots
if (in_array($type, array("mam", "ort", "teu", "tes", "heu", "hes", "pap", "err", "pk2", "oim", "otu", "ots"))) {
	// Image is served directly
	if (file_exists($path)) {
		$image_type = image_type_to_mime_type(exif_imagetype($path));
		header('Access-Control-Allow-Origin: *');
		header('Content-Type: '.$image_type);
		if ($fp = @fopen($path, 'rb')) {
			header('Content-Length: ' . filesize($path));
			fpassthru($fp);
		}
	} else {
		message_404("You requested a map tile [ $osgeo/$service/$version/$tileset/$z/$x/$y ] that does not exist");
		exit();
	}
} else if (in_array($type, array("map", "meu", "mes", "hib", "oeu", "oes", "oin", "oiu", "ois"))) {
	$type2 = substr($path, strlen($tileset)-3, 3);

	// Two images overlappping
	if ($type == "meu") {
		$path1 = str_replace('meu', 'mam', $path);
		$path2 = str_replace('meu', 'teu', $path);
	} else if ($type == "mes") {
		$path1 = str_replace('mes', 'mam', $path);
		$path2 = str_replace('mes', 'tes', $path);
	} else if ($type == "map") {
		$path1 = str_replace('map', 'mam', $path);
		$path2 = str_replace('map', 't'.$lang2, $path);
	} else if ($type == "hib") {
		$path1 = str_replace('hib', 'ort', $path);
		$path1 = str_replace('png', 'jpg', $path1);
		$path2 = str_replace('hib', 'h'.$lang2, $path);
	} else if ($type == "oeu") {
		$path1 = str_replace('oeu', 'ort', $path);
		$path1 = str_replace('png', 'jpg', $path1);
		$path2 = str_replace('oeu', 'heu', $path);
	} else if ($type == "oes") {
		$path1 = str_replace('oes', 'ort', $path);
		$path1 = str_replace('png', 'jpg', $path1);
		$path2 = str_replace('oes', 'hes', $path);
	} else if ($type == "oiu") {
		$path1 = str_replace('oiu', 'oim', $path);
		$path2 = str_replace('oiu', 'otu', $path);
	} else if ($type == "ois") {
		$path1 = str_replace('ois', 'oim', $path);
		$path2 = str_replace('ois', 'ots', $path);
	} else if ($type == "oin") {
		$path1 = str_replace('oin', 'oim', $path);
		$path2 = str_replace('oin', 'ot'.substr($lang2,1,1), $path);
	}
	if (file_exists($path1) && file_exists($path2)) {
		$image_type1=image_type_to_mime_type(exif_imagetype($path1));
		if ($image_type1 == "image/png" ) {
			$image1 = imagecreatefrompng($path1);
		} else {
			$image1 = imagecreatefromjpeg($path1);
		}
		$image2 = imagecreatefrompng($path2);

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
