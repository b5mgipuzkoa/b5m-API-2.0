<?php
// CORS permissions
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Origin, Content-Type, X-Requested-With");

// Use the "OPTIONS" method for CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	// Finish validation and grant permission
	exit(0);
}

// Fetch the z, x, y, year, and image parameters from the URL
$z = isset($_GET['z']) ? $_GET['z'] : null;
$x = isset($_GET['x']) ? $_GET['x'] : null;
$y = isset($_GET['y']) ? $_GET['y'] : null;
$year = isset($_GET['year']) ? $_GET['year'] : null;
$image = isset($_GET['image']) ? $_GET['image'] : null;

// Construct the file path for the tile
$file = '/home9/obliquo/guipuzcoa/' . $year . '/guipuzcoa' . $year . '-LIB/' . $image . '/TileGroup0/' . $z . '-' . $x . '-' . $y . '.jpg';

// Check if the file exists
if (file_exists($file)) {
  // Load the image
  header('Content-Type: image/jpeg');
  // Read and send the file content
  readfile($file);
} else {
  header("HTTP/1.1 404 Not Found");
	echo "Tile not found.";
}
?>
