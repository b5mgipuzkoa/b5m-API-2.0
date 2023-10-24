#!/bin/bash
#
# Topographic profile from coordinates and a LIDAR file with the height data
#
# Taken from Koko Alberti's idea:
# https://kokoalberti.com/articles/creating-elevation-profiles-with-gdal-and-two-point-equidistant-projection/
#
# Requeriments: GDAL (https://gdal.org) and PROJ4 (http://proj4js.org)

if [ $# -ne 1 ] && [ $# -ne 2 ] && [ $# -ne 3 ]
then
	echo "Usage: $0 coors epsg precision [$0 592000,4800000,592090,4799910 EPSG:25830 precision]"
	exit 1
fi

# precision
if [ "$3" = "" ]
then
	precision=1
else
	precision=$3
fi

# Entry variables
coors="$1"
srs="$(echo "$2" | gawk '{print tolower($1)}')"

# Variables
let length_choice=3000/$precision
srs_base="epsg:25830"
base="/tmp/$$"
d_lidar="/home9/lidar"
f_lidar1="${d_lidar}/suelo2008_2.tif"
f_lidar2="${d_lidar}/suelo2008_05.tif"
f_lidar3="${d_lidar}/suelo2008_10.tif"
f_lidar4="${d_lidar}/suelo2008_20.tif"
f_lidar5="${d_lidar}/suelo2008_40.tif"
f_lidar6="${d_lidar}/suelo2008_80.tif"
f1="${base}.csv"
f2="${base}.gpkg"
f3="${base}.tif"
f4="${base}.xyz"
f5="${base}.txt"

profile_2p () {
	# Calculation of the profile from two points

	# If not SRS, trying to regcognize it
	if [ "$srs" = "" ]
	then
		if ((($(echo "$x1 <= 90" | bc -l))&&($(echo "$x1 >= -90" | bc -l))))
		then
			srs="epsg:4326"
		elif (($(echo "$x1 > 90" | bc -l)))
		then
			srs="epsg:25830"
		else
			srs="epsg:3987"
		fi
	fi

	# Coordinate conversion
	if [ "$srs" = "epsg:3857" ]
	then
		coor_conv1="$(echo "$x1 $y1" | cs2cs -f "%.4f" +init="$srs" +to +init="$srs_base")"
		coor_conv2="$(echo "$x2 $y2" | cs2cs -f "%.4f" +init="$srs" +to +init="$srs_base")"
		x1c="$(echo "$coor_conv1" | gawk '{print $1}')"
		y1c="$(echo "$coor_conv1" | gawk '{print $2}')"
		x2c="$(echo "$coor_conv2" | gawk '{print $1}')"
		y2c="$(echo "$coor_conv2" | gawk '{print $2}')"
	else
		x1c="$x1"
		y1c="$y1"
		x2c="$x2"
		y2c="$y2"
	fi

	# Length of the line
	rm "$f1" 2> /dev/null
	echo "$x1c $y1c $x2c $y2c" | gawk '
	BEGIN {
		i=1
		print "\"ID\", \"WKT\""
	}
	{
		printf("\"%s\", \"LINESTRING(%s %s, %s %s)\"\n",i,$1,$2,$3,$4)
		i++
	}
	' > "$f1"
	rm "$f2" 2> /dev/null
	ogr2ogr -f "GPKG" -s_srs "$srs" -t_srs "$srs" -nln "line" "$f2" "$f1" 2> /dev/null
	if [ "$srs" = "epsg:4326" ]
	then
		use_ellipsoid=",true"
	else
		use_ellipsoid=""
	fi
	rm "$f1" 2> /dev/null
	l1="$(ogrinfo "$f2" -q -dialect sqlite -sql "select st_length(geom${use_ellipsoid}) from line" 2> /dev/null | gawk '
	{
		if (($1=="st_length(geom)")||($1=="st_length(geom,true)"))
			print $NF
	}
	')"
	rm "$f2" 2> /dev/null

	# LIDAR file choice depending on the length of the line
	if (($(echo "$l1 <= $length_choice" | bc -l)))
	then
		f_lidar="$f_lidar1"
	elif (($(echo "$l1 <= $length_choice * 2" | bc -l)))
	then
		f_lidar="$f_lidar2"
	elif (($(echo "$l1 <= $length_choice * 4" | bc -l)))
	then
		f_lidar="$f_lidar3"
	elif (($(echo "$l1 <= $length_choice * 8" | bc -l)))
	then
		f_lidar="$f_lidar4"
	elif (($(echo "$l1 <= $length_choice * 16" | bc -l)))
	then
		f_lidar="$f_lidar5"
	else
		f_lidar="$f_lidar6"
	fi

	# LIDAR file resolution
	res1="$(gdalinfo "$f_lidar" 2> /dev/null | gawk '{if($1=="Pixel"){split($4,a,"(");split(a[2],b,",");printf("%.0f\n",b[1])}}')"

	# Warping data into a swath with two-point equidistant projection
	l2="$(echo "$l1 $res1" | gawk '{a=sprintf("%.0f",$1/$2);if(a==0) a=1;print a}')"
	l3=1
	xl2="$(echo "$l1" | gawk '{printf("%f\n",$1/2)}')"
	xl1="-${xl2}"
	yl2="$(echo "$res1" | gawk '{printf("%f\n",$1/2)}')"
	yl1="-${yl2}"
	if [ "$srs" != "epsg:4326" ]
	then
		coor1="$(echo "$x1 $y1" | cs2cs -f "%.12f" +init="$srs" +to +init=epsg:4326)"
		coor2="$(echo "$x2 $y2" | cs2cs -f "%.12f" +init="$srs" +to +init=epsg:4326)"
		x3="$(echo "$coor1" | gawk '{print $1}')"
		y3="$(echo "$coor1" | gawk '{print $2}')"
		x4="$(echo "$coor2" | gawk '{print $1}')"
		y4="$(echo "$coor2" | gawk '{print $2}')"
	else
		x3="$x1c"
		y3="$y1c"
		x4="$x2c"
		y4="$y2c"
	fi
	proj_tpeqd="+proj=tpeqd +lon_1=${x3} +lat_1=${y3} +lon_2=${x4} +lat_2=${y4}"
	rm "$f3" 2> /dev/null
	gdalwarp -overwrite -t_srs "$proj_tpeqd" -te $xl1, $yl1, $xl2, $yl2 -ts $l2 $l3 -r near "$f_lidar" "$f3" > /dev/null 2> /dev/null

	# Obtaining height data
	rm "$f4" 2> /dev/null
	gdal_translate -of "XYZ" "$f3" "$f4" > /dev/null 2> /dev/null
	res2="$(gdalinfo "$f3" 2> /dev/null | gawk '{if($1=="Pixel"){split($4,a,"(");split(a[2],b,",");printf("%f\n",b[1])}}')"
	rm "$f3" 2> /dev/null

	# Height data processing
	gawk -v res2=$res2 '
	{
		printf("%s,%.2f\n",res2,$3)
	}
	' "$f4" 2> /dev/null
	rm "$f4" 2> /dev/null
}

# Coordinate reading
rm "$f5" 2> /dev/null
i=1
j=0
IFS=',' read -a coors_array <<< "$coors"
for coor in "${coors_array[@]}"
do
	case "${i}" in
	  1) x1="$coor";;
	  2) y1="$coor";;
	  3) x2="$coor";;
	  4) y2="$coor";;
	esac
	let i=$i+1

	if [ $i -eq 5 ]
	then
		profile_2p "$x1,$y1,$x2,$y2" "$srs" >> "$f5"
		x1="$x2"
		y1="$y2"
		i=3
		j=1
	fi
done

# Last point
lph="$(gdallocationinfo -valonly -l_srs "$srs" "$f_lidar" $x2 $y2)"

# Result
gawk -v lph="$lph" '
function abs(v) { v += 0; return v < 0 ? -v : v }
BEGIN{
	FS=","
	l=0
	l2=0
	mah=-9999
	mih=9999
	avh1=0
	map=-9999
	el1=0
	el2=0
}
{
	if($2=="-9999.00") $2=sprintf("%.0f",$2)
	if(l==0)
		pt=0
	else
		pt=((abs($2-h2))/$1)*100
	if(mah<$2) mah=$2
	if(mih>$2 && $2!="-9999") mih=$2
	if ($2!="-9999") {
		avh1=avh1+$2
		l2++
	}
	if(map<pt) map=pt
	if(NR!=1 && $2>h2 && $2!="-9999") el1=el1+$2-h2
	if(NR!=1 && $2<h2 && $2!="-9999") el2=el2+$2-h2
	# No slope
	prf=prf""sprintf("%.2f %s\n",l,$2)
	h2=$2
	l=l+$1
}
END{
	pt=((abs(lph-h2))/$1)*100
	if(mah<lph) mah=lph
	if(mih>lph && lph!="-9999") mih=lph
	if(map<pt) map=pt
	if(lph!="-9999"){
		avh1=avh1+lph
		l2++
	 	lph=sprintf("%.2f",lph)
	}
	if(lph>h2 && lph!="-9999") el1=el1+lph-h2
	if(lph<h2 && lph!="-9999") el2=el2+lph-h2
	# No slope
	printf("%.2f %.2f %.2f %.2f %.2f %.2f\n",l,mah,mih,avh1/l2,el1,abs(el2))
	printf("%s",prf)
	printf("%.2f %s\n",l,lph)
}
' "$f5"
rm "$f5" 2> /dev/null

exit 0
