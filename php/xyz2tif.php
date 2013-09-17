#!/usr/bin/php
<?php

require_once 'class.db.php';

// DTM_DST_DIR einfuehren? -> berechnungen in einem work_dir aber am schluss noch wegkopieren.
//


// Skript ausfuehren im Verzeichnis wo xyz-Datei liegt (sonst passt Pfad in ogr.vrt-Datei nicht mehr).


define("DTM_SRC_DIR", "/home/stefan/Downloads/dtm_grid/");
define("WORK_DIR", "/home/stefan/tmp/dtm/v2/");
define("SMOOTH_DIR", "/home/stefan/tmp/dtm/v2/smooth/");
define("HILL_DIR", "/home/stefan/tmp/dtm/v2/hillshade/");

define("KANTON_NR", 11);

$db = new dbObj('pg', 'dbname=xanadu2_test host=localhost user=mspublic port=5432', 'mspublic');

$sql = "SELECT a.nummer, ST_XMin(box2d(the_geom)) as xmin, ST_XMax(box2d(the_geom)) as xmax, ST_YMin(box2d(the_geom)) as ymin, ST_YMax(box2d(the_geom)) as ymax FROM av_dtm_dom_meta.dtm_dom_index as a, av_dtm_dom_meta.kantonsgrenzen as b WHERE a.the_geom && b.geom AND ST_Intersects(a.the_geom, b.geom) AND b.kantonsnum = ".KANTON_NR.";";

$result = $db->read($sql);

for($i = 0; $i < count($result['NUMMER']); $i++)
{
    $nr = $result['NUMMER'][$i];
    $xmin = $result['XMIN'][$i];
    $xmax = $result['XMAX'][$i];
    $ymin = $result['YMIN'][$i];
    $ymax = $result['YMAX'][$i];
    
    $dtm_gz_name = "dtm" . str_replace("-", "", $nr) . ".xyz.gz";
    $dtm_xyz_name = "dtm" . str_replace("-", "", $nr) . ".xyz";
    $dtm_base = "dtm" . str_replace("-", "", $nr);
    $dtm_tif_name = "dtm" . str_replace("-", "", $nr) . ".tif";
    
    $dtm_gz_file = DTM_SRC_DIR . $dtm_gz_name;
    
   
    exec( "cp " . $dtm_gz_file . " " . WORK_DIR );
    exec( "gunzip " . WORK_DIR . $dtm_gz_name );
    exec( "fromdos " . WORK_DIR . $dtm_xyz_name );
    
    $cmd = "sed -e 's/\s/,/g' " . WORK_DIR . $dtm_xyz_name . " > " . WORK_DIR . "dtm_xyz.csv";
    exec( $cmd );
    
    $cmd = "sed -i '1s/^/x,y,z\\n/' " . WORK_DIR . "dtm_xyz.csv";
    exec( $cmd );
    
    // +1 Meter: 1/16-Kacheln sind in x-Richtung nicht durch 2 teilbar.
    $dx = $xmax - $xmin + 1;
    $dy = $ymax - $ymin;
    
    $px = $dx / 2;
    $py = $dy / 2;
        
        
    // Mit gdal_grid aus den Punktdaten ein GeoTiff erstellen.
    // Input ist hier das DTM 2m-Grid. Möglich wären auch die Rohdaten.
    // Dann dürfte es aber langsamer werden ('nearest' ginge dann nicht mehr).
    // (Kann man u.U. auch gdal_rasterize verwenden?)
    $cmd = "gdal_grid -a_srs epsg:21781 -a nearest:radius1=5.0:radius2=5.0 -txe " . $xmin . " " . ($xmax+1) . " -tye " . $ymin . " " . $ymax . " -outsize " . $px . " " . $py . " -of GTiff -ot Float32 -l dtm_xyz " . WORK_DIR . "dtm_xyz.vrt " . WORK_DIR . $dtm_base . "_tmp.tif";
    //$cmd = "gdal_grid -a_srs epsg:21781 -a average:min_points=9:radius1=20.0:radius2=20.0 -txe " . $xmin . " " . ($xmax+1) . " -tye " . $ymin . " " . $ymax . " -outsize " . $px . " " . $py . " -of GTiff -ot Float32 -l dtm_xyz " . WORK_DIR . "dtm_xyz.vrt " . WORK_DIR . $dtm_base . "_tmp.tif";    
    exec( $cmd );
    
    // Aus irgendeinem Grund sind die Pixel nicht 2m gross...??
    $cmd = "gdalwarp -co COMPRESS=PACKBITS -co TILED=YES -t_srs epsg:21781 -te " . $xmin . " " . $ymin . " " . ($xmax+1) . " " . $ymax . " -tr 2 2 -r near " . WORK_DIR . $dtm_base . "_tmp.tif " . WORK_DIR . $dtm_tif_name;
    exec( $cmd );
    
    exec ( "rm " . WORK_DIR . $dtm_base . "_tmp.tif" );
    exec ( "rm " . WORK_DIR . $dtm_xyz_name );
    exec ( "rm " . WORK_DIR . "dtm_xyz.csv" );
 
    // gdal_grid mit average geht seeeeehr lange. Schneller gehts wenn
    // das DTM x-mal mit cubic(spline) Methode gewarped wird. Das
    // Endergebnis (die Höhenkurven) sehen ähnlich smooth aus.
    exec( "cp " . WORK_DIR .$dtm_tif_name . " " . WORK_DIR . "input.tif" );
    $input = WORK_DIR . "input.tif";
    $output = WORK_DIR . "output.tif";

    for ($j = 0; $j < 10; $j++)
    {
        $cmd = "gdalwarp -co COMPRESS=PACKBITS -r cubicspline " . $input . " " . $output;
        exec( $cmd );
        exec( "cp " . $output . " " . $input );
        exec( "rm " . $output);
    }
    
    exec( "mv " . $input . " " . SMOOTH_DIR . $dtm_tif_name ) ;
   
    
    // Hillshading (mit gewarpten DTM)
    $cmd = "gdaldem hillshade -co COMPRESS=LZW -compute_edges -az 270 -alt 40 " . SMOOTH_DIR . $dtm_tif_name . " " . HILL_DIR . $dtm_base . ".tif";
    exec( $cmd );
    
    $cmd = "gdaladdo -r average " . HILL_DIR . $dtm_base . ".tif 2 4 8 16 32 64";
    exec( $cmd );    
    
    $cmd = "gdaldem color-relief -co COMPRESS=LZW " . HILL_DIR . $dtm_base . ".tif ramp.txt " . HILL_DIR ."/bp/" . $dtm_base . ".tif";
    exec( $cmd );

    $cmd = "gdaladdo -r average " . HILL_DIR . "/bp/" . $dtm_base . ".tif 2 4 8 16 32 64";
    exec( $cmd );    
  

    
    
  //break;
}

exec( "gdalbuildvrt " . SMOOTH_DIR . "dtm_contour.vrt " . SMOOTH_DIR . "*.tif" );
exec( "gdalbuildvrt " . WORK_DIR . "dtm.vrt " . WORK_DIR . "*.tif" );


// Hoehenkurven

for($i = 0; $i < count($result['NUMMER']); $i++)
{
    $nr = $result['NUMMER'][$i];
    $xmin = $result['XMIN'][$i];
    $xmax = $result['XMAX'][$i];
    $ymin = $result['YMIN'][$i];
    $ymax = $result['YMAX'][$i];
    
    $dtm_base = "dtm" . str_replace("-", "", $nr);
    $dtm_tif_name = "dtm" . str_replace("-", "", $nr) . ".tif";

    // Aus VRT Teil-DTM ausschneiden (groesser als eigentliche Kachel 
    // -> schoene Uebergaenge zwischen den Kacheln).
    $cmd = "gdal_translate -projwin " . ($xmin-50) . " " . ($ymax+50) . " " . ($xmax+50) . " " . ($ymin-50) . " " . WORK_DIR . "dtm.vrt " . WORK_DIR . "input.tif";
    exec( $cmd );
    
    // Das ganze Spiel nochmals mit smoothen. Uebergange sind mit den
    // einzelnen Kacheln nicht schoen.
    // Brauche ich dann den oberen Schritt noch? Oder nur 'nearest'-Hillshading?  
    // Mit den 'nearest'-Kacheln habe ich Streifen beim Hillshading gesehen...  
    $input = WORK_DIR . "input.tif";
    $output = WORK_DIR . "output.tif";

    //for ($j = 0; $j < 10; $j++)
    for ($j = 0; $j < 15; $j++)
    {
        $cmd = "gdalwarp -co COMPRESS=PACKBITS -r cubicspline " . $input . " " . $output;
        exec( $cmd );
        exec( "cp " . $output . " " . $input );
        exec( "rm " . $output);
    }
      
    $cmd = "gdal_contour -a elev -i 10.0 " . $input . " " . WORK_DIR . "contour_tmp.shp";
    exec( $cmd );

    $cmd = "ogr2ogr -append -clipsrc " . $xmin . " " . $ymin . " " . $xmax . " " . $ymax . " " . WORK_DIR . "contour.shp " . WORK_DIR . "contour_tmp.shp";
    exec( $cmd );
    
    exec( "rm " . $input );
    exec( "rm " . WORK_DIR . "contour_tmp.*" );
}


// Hoehenkurven noch auf Kanton zuschneiden und alles was kleiner 100 Meter ist, loeschen.
// 100 Meter -> noch zu testen was wirklich sinnvoll ist.
$dsrc = "PG:\"host=localhost dbname=xanadu2_test user=mspublic password=mspublic\"";
$sql = "\"SELECT ST_Force_2D(geom) FROM av_dtm_dom_meta.kantonsgrenzen WHERE kantonsnum = " . KANTON_NR . "\""; 

$cmd = "ogr2ogr -clipsrc " . $dsrc . " -clipsrcsql " . $sql . " " . WORK_DIR . "contour_tmp.shp " . WORK_DIR . "contour.shp";
exec( $cmd );

$cmd = "ogr2ogr -dialect SQLITE " . WORK_DIR . "contour_" . KANTON_NR . ".shp " . WORK_DIR . "contour_tmp.shp -sql \"SELECT * FROM  contour_tmp WHERE ST_Length(GEOMETRY) > 100\"";
exec( $cmd );

//exec( "rm " . WORK_DIR . "contour.*");
exec( "rm " . WORK_DIR . "contour_tmp.*");

print "hallo stefan\n";

?>
