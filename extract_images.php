#!/usr/local/sbin/php -q
<?php
/*!*******************************************************************************
*! FILE NAME  : split_mov.php
*! DESCRIPTION: splits a *.mov file into frames naming them by the timestamp
*! Copyright (C) 2011 Elphel, Inc
*! -----------------------------------------------------------------------------**
*!  This program is free software: you can redistribute it and/or modify
*!  it under the terms of the GNU General Public License as published by
*!  the Free Software Foundation, either version 3 of the License, or
*!  (at your option) any later version.
*!
*!  This program is distributed in the hope that it will be useful,
*!  but WITHOUT ANY WARRANTY; without even the implied warranty of
*!  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*!  GNU General Public License for more details.
*!
*!  The four essential freedoms with GNU GPL software:
*!  * the freedom to run the program for any purpose
*!  * the freedom to study how the program works and change it to make it do what you wish
*!  * the freedom to redistribute copies so you can help your neighbor
*!  * the freedom to distribute copies of your modified versions to others
*!
*!  You should have received a copy of the GNU General Public License
*!  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*! -----------------------------------------------------------------------------**
*!  $Log: split_mov.php,v $
*! 
*/

set_time_limit(60*60*24);

$chunksize=10000000; // 10MB 
$startMarkerWithExif=chr(hexdec("ff")).chr(hexdec("d8")).chr(hexdec("ff")).chr(hexdec("e1"));

echo "<pre>\n";

// if (!isset($_GET['path']) || !isset($_GET['ext'])) {
// 	echo "Usage split_mov.php?path=&lt;path_of_the_mov_file&gt;&ext=&lt;extension&gt;";
// 	//echo "Usage split_mov.php?path=path_of_the_mov_file&ext=extension";
// 	echo "</pre>\n";
// 	exit (1);
// }

if (isset($_GET['path'])) $path=$_GET['path'];
else $path=".";

if (isset($_GET['ext'])) $extension = $_GET['ext']; 
else                     $extension = "jp4";

if (isset($_GET['dest_path'])) $destination = $_GET['dest_path']; 
else                           $destination = "result";


$files = scandir("$path");

if (!is_dir("$path/$destination")) mkdir("$path/$destination",0777);

foreach ($files as $file) {
    if (get_file_extension($file)=="bin") {
	echo "Splitting $path/$file into {$extension}s\n";
	split_mov("$path",$file,$destination,$extension,$startMarkerWithExif,$chunksize);
    }
}


function split_mov($path,$mov_file,$dest,$ext,$startMarkerWithExif,$chunksize) {

	$path_with_name = "$path/$mov_file";

	if (!is_file($path_with_name)) {
		return -1;
	}

	$file=fopen($path_with_name,'r');

	$markers=array(0);
	$offset=0;

	while (!feof($file)) {
		fseek($file,$offset);
		$s = fread($file,$chunksize);
		$index=0;
		$pos=0;
		while (true) {
			$pos=strpos($s,$startMarkerWithExif,$pos);
			if ($pos === false) break;
			$markers[count($markers)]=$offset+$pos;
			$pos++;
		}
		$offset+=(strlen($s)-strlen($startMarkerWithExif)+1); // so each marker will appear once
	}

	$markers[count($markers)]=$offset+strlen($s); // full length of the file

	for ($i=1;$i<(count($markers)-1);$i++) {
		fseek($file,$markers[$i]);
		$s = fread($file,$markers[$i+1]-$markers[$i]);

		$old_file_name= "$path/tmp.".$ext;

		$outFile=fopen($old_file_name,'w');
		fwrite($outFile,$s);
		fclose($outFile);

		//read exif & rename
		$exif_data = exif_read_data($old_file_name);
    
		//converting GMT a local time GMT+7
		$DateTimeOriginal_local=strtotime($exif_data['DateTimeOriginal']);/*-25200;*/
		
		$tmp = explode("_",$exif_data['Model']);
		
		if (count($tmp)==2){
                  $model = intval(trim($tmp[1]));
                  $chn = intval($exif_data['PageNumber'])+1;
                  if        ($model==1001) {
                    $k=$chn;
                  }else  if ($model==1002) {
                    $k=$chn+4;
                  }else  if ($model==1003) {
                    $k=$chn+6;
                  }
                  $new_file_name = $DateTimeOriginal_local."_".$exif_data['SubSecTimeOriginal']."_".$k.".".$ext;
		}

		rename($old_file_name,"$path/$dest/$new_file_name");
	}    
	return 0;
}

function get_file_extension($filename) {
	return pathinfo($filename, PATHINFO_EXTENSION);
}

?>