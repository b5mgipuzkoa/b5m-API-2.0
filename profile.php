<?php
// b5m profile API
//
// Data comes from the b5m OGC WCS Service
//
// Coordinate transformation with PROJ (proj.org): cs2cs
// Raster query to get heights with GDAL (gdal.org): gdallocationinfo
//

// Requests
$coors = $_REQUEST["coors"];
$srs = $_REQUEST["srs"];
$fmt = $_REQUEST["format"];

// Function to convert coordinates
function coordinate_convert($x, $y, $srs1, $srs2)
{
	if (strtolower($srs2) == "epsg:4326") {
		$floating_number = 6;
	} else {
		$floating_number = 2;
	}
	$conversion_array1 = explode(" ", exec("echo \"" . $x . " " . $y . "\" | cs2cs -f \"%." . $floating_number . "f\" +init=" . $srs1 . " +to +init=" . $srs2));
	$conversion_array2 = explode("	", $conversion_array1[0]);
	return $conversion_array2[0] . " " . $conversion_array2[1];
}

// Function to get height values
function height($x, $y) {
	global $session_id, $srs_global, $offset, $width, $height, $wcs;
	$wcs2 = $wcs . $srs_global . "&RESPONSE_CRS=" . $srs_global . "&WIDTH=" . $width . "&HEIGHT=" . $height . "&BBOX=";

	$xo = $x + $offset;
	$yo = $y - $offset;
	$wcs_query = $wcs2 . $x . "," . $y . "," . $xo . "," . $yo;
	$f_name = "/tmp/" . $session_id . ".tif";

	$ch = curl_init($wcs_query);
	$fp = fopen($f_name, "w");

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	curl_exec($ch);
	if(curl_error($ch)) {
	    fwrite($fp, curl_error($ch));
	}
	curl_close($ch);
	fclose($fp);

	$output = null;
	$retval = null;
	exec("gdallocationinfo " . $f_name . " 0 0", $output, $retval);

	unlink($f_name);

	$height_wcs = explode(" ", $output[3]);
	return round($height_wcs[5], 2);
}

// Profile points making function
function profile_points($x1_srs_ori, $y1_srs_ori, $x2_srs_ori, $y2_srs_ori, $srs) {
	global $srs_global, $distance_min, $point_max;
	// Converting to $srs_global to calculate the distance between two points
	if (strtolower($srs) != $srs_global) {
		$coord1_array = explode(" ", coordinate_convert($x1_srs_ori, $y1_srs_ori, strtolower($srs), $srs_global));
		$coord2_array = explode(" ", coordinate_convert($x2_srs_ori, $y2_srs_ori, strtolower($srs), $srs_global));
		$x1 = $coord1_array[0];
		$y1 = $coord1_array[1];
		$x2 = $coord2_array[0];
		$y2 = $coord2_array[1];
	}	else {
		$x1 = $x1_srs_ori;
		$y1 = $y1_srs_ori;
		$x2 = $x2_srs_ori;
		$y2 = $y2_srs_ori;
	}

	// Distance of the line
	$distance = sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));

	// Coordinates to query
	if ($distance <= $distance_min) {
		$coor_query_array[0] = $x1 . " " . $y1 . " 0.00";
		//$coor_query_array[1] = $x2 . " " . $y2 . " " . round($distance, 2);
		$coor_query_array[1] = $x2 . " " . $y2 . " " . $distance;
	} else {
		$number_of_points = floor($distance / $distance_min);
		//echo "ZZ31: $number_of_points " . microtime(true) . "<br />";
		if ($number_of_points > $point_max) {
			$distance_min = $distance / $point_max;
			$number_of_points = $point_max - 1;
			//echo "ZZ32: $distance_min " . microtime(true) . "<br />";
		}
		$coor_query_array[0] = $x1 . " " . $y1 . " " . $x1_srs_ori . " " . $y1_srs_ori . " 0.00";

		// Calculation of intermediate points
		$x_offset = (($x2 - $x1) / $distance) * $distance_min;
		$y_offset = (($y2 - $y1) / $distance) * $distance_min;
		$x_inter_new = $x1;
		$y_inter_new = $y1;
		//echo "ZZ33: " . microtime(true) . "<br />";
		for ($k = 1; $k <= $number_of_points; $k++) {
			$x_inter = $x_inter_new + $x_offset;
			$y_inter = $y_inter_new + $y_offset;
			$x_inter_new = $x_inter;
			$y_inter_new = $y_inter;

			// Intermediate point in the original srs
			if (strtolower($srs) != $srs_global) {
				$coord3_array = explode(" ", coordinate_convert($x_inter, $y_inter, $srs_global, strtolower($srs)));
				$x_inter_srs_ori = $coord3_array[0];
				$y_inter_srs_ori = $coord3_array[1];
			} else {
				$x_inter_srs_ori = $x_inter;
				$y_inter_srs_ori = $y_inter;
			}
			//$coor_query_array[$k] = $x_inter . " " . $y_inter . " " . round($distance_min, 2);
			$coor_query_array[$k] = $x_inter . " " . $y_inter . " " . $x_inter_srs_ori . " " . $y_inter_srs_ori . " " . $distance_min;
		}
		//echo "ZZ35: " . microtime(true) . "<br />";
		$distance_final = sqrt(pow($x2 - $x_inter, 2) + pow($y2 - $y_inter, 2));
		//$coor_query_array[$k] = $x2 . " " . $y2 . " " . round($distance_final, 2);
		$coor_query_array[$k] = $x2 . " " . $y2 . " " . $x2_srs_ori . " " . $y2_srs_ori . " " . $distance_final;
	}
	/*
	echo "ZZ61 <br/>";
	print_r($coor_query_array);
	echo "ZZ62 <br/>";
	$j = 1;
	$k = 0;
	foreach ($coor_query_array as $coor_query)  {
		$coor_query_isol_array = explode(" ", $coor_query);
		$x_alt = $coor_query_isol_array[0];
		$y_alt = $coor_query_isol_array[1];
		$distance_alt = $coor_query_isol_array[2];
		//$height = height($x_alt, $y_alt, $srs_global);
		// echo "ZZ21: " . microtime(true) . "<br />";
		//$point_data_array[$k] = $x_alt . " " . $y_alt . " " . $distance_alt . " " . $height;
		$point_data_array[$k] = $x_alt . " " . $y_alt . " " . $distance_alt;
		$k++;
	}
	echo "ZZ71 <br/>";
	print_r($point_data_array);
	echo "ZZ72 <br/>";
	return $point_data_array;
	*/
	return $coor_query_array;
}

// Variables
$wcs = "https://b5mprod/ogc/wcs/gipuzkoa_wcs?SERVICE=WCS&VERSION=1.0.0&REQUEST=GetCoverage&FORMAT=GEOTIFFFLOAT32&COVERAGE=LIDAR%202008:%20Lur%20eredua-Modelo%20de%20suelo&CRS=";
$srs_global = "epsg:25830";
$offset = 0.1;
$width = 1;
$height = $width;
$distance_min = 20; // Minimum distance between two points
$point_max = 40; // Maximum number of points in a section

// Session start
$session_id = session_create_id();
session_id($session_id);
session_start();

// Coordinates loop
echo "<br />ZZ91: " . microtime(true) . "<br /><br/ >";
$distance_profile = 0;
$coors_array = explode(",", $coors);
$i = 1;
$j = 1;
foreach ($coors_array as $coor)  {
	if ($i == 1) {
		$x1 = $coor;
	} else if ($i == 2) {
		$y1 = $coor;
	} else if ($i == 3) {
		$x2 = $coor;
	} else if ($i == 4) {
		$y2 = $coor;
		//echo "ZZ1: " . microtime(true) . "$x1 $y1 $x2 $y2 <br />";
		$profile_array = profile_points($x1, $y1, $x2, $y2, $srs);
		//echo "ZZ2: " . microtime(true) . "<br />";
		//print_r($profile_array);
		//echo "<br />ZZ3: " . microtime(true) . "<br /><br/ >";

		// Profile loop
		foreach ($profile_array as $profile_coor)  {
			$profile_array_2 = explode(" ", $profile_coor);
			$x_fin = $profile_array_2[0];
			$y_fin = $profile_array_2[1];
			$x_fin_srs_ori = $profile_array_2[2];
			$y_fin_srs_ori = $profile_array_2[3];
			$distance_fin = $profile_array_2[4];
			//$distance_coor = (float)$profile_array_2[4];
			//echo "ZZ4: " . $profile_coor . " - " . $profile_array_2[2] . " - ". $j . " - " . $distance_coor . "<br />";
			if (($j == 1) || ($distance_fin != 0)) {
			//if (($j == 1) || ($distance_coor != 0)) {
				$distance_profile = $distance_profile + $distance_fin;
				$height = height($x_fin, $y_fin);
				echo "ZZ5: " . $x_fin . " " . $y_fin . " " . $x_fin_srs_ori. " " . $y_fin_srs_ori . " " . $distance_profile . " " . $height . "<br />";
				$j++;
			}
		}
		$x1 = $x2;
		$y1 = $y2;
		$i = 2;
	}
	$i++;
}

echo "<br/ >$coors<br />";

// Session end
session_destroy();
session_write_close();
echo "<br />ZZ92: " . microtime(true) . "<br /><br/ >";
