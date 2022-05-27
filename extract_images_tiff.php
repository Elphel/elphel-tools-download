#!/usr/bin/env php
<?php
/**
 * @file extract_images.php
 * @brief split mov/bin/img's (by exif header) into single image files - looks one dir down from the specified path
 * @copyright Copyright (C) 2017-2021 Elphel Inc.
 * @author Elphel Inc. <support-list@support.elphel.com>
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
   define('START_TIFF',     hex2bin('4d4d002a')); // start of a TIFF, JP/JP4 also have this TIFF marker
   define('START_JP',       hex2bin('ffd8ffe1')); // start of JPEG/JP4, TIFF usually does not have JPEG/JP4 markers
//disable the default time limit for php scripts.
set_time_limit(0);

$chunksize=10000000; //10MB 
//$startMarkerWithExif=chr(hexdec("4d")).chr(hexdec("4d")).chr(hexdec("00")).chr(hexdec("2a"));
//$startMarkerWithExif=chr(hexdec("ff")).chr(hexdec("d8")).chr(hexdec("ff")).chr(hexdec("e1"));

$input_exts = array("img","bin","mov");

// use current dir
$path=".";
$destination = "0";

$move_processed = false;
$processed_subdir = "processed";

$forced_ext = "";

$add_to_chn = -1; // do not use and do not create scene directories

function print_help(){
  global $argv;
  
  echo <<<"TXT"
Help:
  * Usage:
    ~$ {$argv[0]} path=[path-to-dir] dest_path=[dest-subdir] move_processed=[move-processed-files] ext=[forced-ext] chn_offs=[add-to-chn]
    
    where:
      * path-to-dir            - string - scan this path + 1 dir down
      * dest-subdir            - string - save results to "path-to-dir/dest-subdir/"
      * move-processed-files - 0(default) or 1 - if not 1 - will not move the processed files
      * forced-ext             - string - override extensions from exifs with this one
      * add-to-chn             - integer - add to decoded channel number
    
  * Examples:
    ** Split all *.img, *.bin and *.mov files in the current dir and 1 dir down, puts results to '0/':
      ~$ {$argv[0]}
    ** Split in /data/test + 1 dir down, create and move processed files to /data/test/processed for files in path and /data/test/any-found-subdir/processed for any files found in /data/test/any-found-subdir
      ~$ {$argv[0]} path=/data/test move_processed=1
    ** Split all *.img, *.bin and *.mov files in the current dir and 1 dir down, puts results to 'results/', override extensions with 'jpg':
      ~$ {$argv[0]} dest_path=results ext=jpg

TXT;

}

if ($argv){
  foreach($argv as $k=>$v){
    if ($k==0) continue;
    $args = explode("=",$v);
    if (isset($args[1])) $_GET[$args[0]] = $args[1];
    if ($v=="-h"||$v=="--help"||$v=="help") {
      print_help();
      die();
    }
  }
}

if (isset($_GET['path'])){
  $path=$_GET['path'];
}

// put results to $path/$destination/
// no need for argv
if (isset($_GET['dest_path'])){
  $destination = $_GET['dest_path'];
}

if (isset($_GET['move_processed'])){
  $move_processed = $_GET['move_processed'];
}

if ($move_processed=="1"){
  $move_processed = true;
}else{
  $move_processed = false;
}

// enforce extension
if (isset($_GET['ext'])){
  $forced_ext = $_GET['ext'];
}

if (isset($_GET['chn_offs'])){
  $add_to_chn = (integer) $_GET['chn_offs'];
}

$list = preg_grep('/^([^.])/', scandir($path));

if (!is_dir("$path/$destination")) mkdir("$path/$destination",0777);

foreach ($list as $item) {
  if (is_dir("$path/$item")){
    if ($item==$processed_subdir) continue;
    $sublist = preg_grep('/^([^.])/', scandir("$path/$item"));
    foreach($sublist as $subitem){
      if (split_file("$path/$item","$subitem","../$destination",$add_to_chn)==0){
        if ($move_processed){
          if (!is_dir("$path/$item/$processed_subdir")){
            mkdir("$path/$item/$processed_subdir",0777);
          }
          rename("$path/$item/$subitem","$path/$item/$processed_subdir/$subitem");
        }
      }
    }
  }else{
    if (split_file("$path","$item","$destination",$add_to_chn)==0){
      if ($move_processed){
        if (!is_dir("$path/$processed_subdir")){
          mkdir("$path/$processed_subdir",0777);
        }
        rename("$path/$item","$path/$processed_subdir/$item");
      }
    }
  }
}

//./extract_images_tiff.php path=/home/eyesis/captures/tests/jp4/ dest_path=results chn_offs=16
function split_file($path,$file,$destination,$add_to_chn=-1){

//  global $startMarkerWithExif = START_JP; // START_TIFF
  global $chunksize;
  global $input_exts;
  global $forced_ext;
  
  if (in_array(get_ext("$path/$file"),$input_exts)) {
    echo date(DATE_RFC2822).": Splitting $path/$file, results dir: $path/$destination\n";
    //split_mov("$path",$file,$destination,$extension,$startMarkerWithExif,$chunksize);
    $file_type = 0; // JP4/JPEG
    $markers=array();
    $offset =0;
    $f=fopen("$path/$file","r");
    
    $s = fread($f,$chunksize);
    $pos_jp =   strpos($s,START_JP);
    $pos_tiff = strpos($s,START_TIFF);
    if (($pos_jp === false) && ($pos_tiff === false)) {
        print ("None of TIFF (".bin2hex(START_TIFF).") or JP4/JPEG (".bin2hex(START_JP).") markers found in the first ".$chunksize." bytes of the file $path/$file\n");
        return -1;
    }
    if ($pos_jp === false) {
        $file_type = 1; // tiff
    } else if (($pos_tiff !== false) && ($pos_tiff < $pos_jp)){ // reducing probability of stray START_JP
        $file_type = 1; // tiff
    } else {
        $file_type = 0; // jpeg/jp4
    }
    $startMarkerWithExif = $file_type? START_TIFF: START_JP; // START_TIFF
    echo "Detected image type ".($file_type?'TIFF':'JPEG/JP4').".\n";
    $forced_ext = $file_type?'tiff':''; // was ".tiff":""
    fclose($f); // not sure if feof is cleraed by fseek, closing/ reopening
    $f=fopen("$path/$file","r");
    //first scan
    while (!feof($f)) {
      $pos=0;
      $index=0;
      fseek($f,$offset);
      $s = fread($f,$chunksize);
      while(true){
        $pos=strpos($s,$startMarkerWithExif,$pos);
        if ($pos === false) break;
        $markers[count($markers)]=$offset+$pos;
        $pos++;
      }
      $offset+=(strlen($s)-strlen($startMarkerWithExif)+1); // so each marker will appear once
    }
    
    $markers[count($markers)]=$offset+strlen($s); // full length of the file
   
    echo "  images found: ".(count($markers)-1)."\n";
   
    //second scan
    for ($i=0;$i<(count($markers)-1);$i++) {

      fseek($f,$markers[$i]);
      $s = fread($f,$markers[$i+1]-$markers[$i]);

      $tmp_name = "$path/$destination/image.tmp";
      file_put_contents($tmp_name,$s);

      $result_name = elphel_specific_result_name($tmp_name,$add_to_chn);
      $dest_image = "$path/$destination/$result_name"; // $result_name may now include "/"
      $dest_set_dir = dirname($dest_image);
      if (!is_dir($dest_set_dir)){
          mkdir($dest_set_dir,0777);
      }
      rename($tmp_name, $dest_image); // "$path/$destination/$result_name");
    }
    
    return 0;
  }else{
    return -1;
  }
}


function elphel_specific_result_name($file,$add_to_chn=-10){

  global $forced_ext;
  // $add_to_chn<0 - do not use it and do not use scene directories
  $use_scene_dirs = $add_to_chn >= 0;
  if ($add_to_chn < 0){
      $add_to_chn = 0;
  }

  $exif = exif_read_data($file); // gets false find, what is wrong
  
  $ext = elphel_specific_result_ext($exif,$forced_ext);
  
  //converting GMT a local time GMT+7
  $timestamp_local=strtotime($exif['DateTimeOriginal']);/*-25200;*/ //
  
  $subsecs = $exif['SubSecTimeOriginal']; // 
  
  $tmp = explode("_",$exif['Model']); //
  if (count($tmp)==2){
    
    if (trim($tmp[0])=="Eyesis4pi393"){
    
      $model = intval(trim($tmp[1]));
      $chn = intval($exif['PageNumber'])+1;
      
      if        ($model==1001) {
        $k=$chn;
      }else  if ($model==1002) {
        $k=$chn+4;
      }else  if ($model==1003) {
        $k=$chn+6;
      }
      
    }else{
        $ks = $exif['PageNumber'];
        if (is_array($ks) && (count($ks) ==2)){
            $k = $ks[0];
        }else{
            $k = intval($exif['PageNumber'])+1;
        }
    }
    
  }else{
    $k = intval($exif['PageNumber'])+1; //
  }
  if ($use_scene_dirs) {
      $k += $add_to_chn;
      return "{$timestamp_local}_{$subsecs}/{$timestamp_local}_{$subsecs}_$k.$ext";
  } else {
      return "{$timestamp_local}_{$subsecs}_$k.$ext";
  }
}

function get_ext($filename) {
  return pathinfo($filename, PATHINFO_EXTENSION);
}

/**
 * read image format and return extension, elphel elphel_specific
 * @param array $exif Array returned from the PHP's built-in exif_read_data function
 * @param string $override_ext
 * @return string extension - jpeg or jp4
 */
function elphel_specific_result_ext($exif,$override_ext=""){
  
  //default value
  $ext = "jpeg";
  
  if ($override_ext==""){
    if (isset($exif['MakerNote'][10])){
      $record = ($exif['MakerNote'][10]>>4)&0xf;
      if ($record==5) $ext = "jp4";
    }
  }else{
    $ext = $override_ext;
  }
  return $ext;
}

?>
