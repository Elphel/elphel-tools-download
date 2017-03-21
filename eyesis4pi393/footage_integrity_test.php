#!/usr/bin/env php
<?php
/**
 * @file check_footage.php
 * @brief will check (by timestamps) if any frames in footage are missing (recording errors)
 * @copyright Copyright (C) 2017 Elphel Inc.
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

set_time_limit(60*60*24);

//$chunksize=10000000; //10MB
$chunksize=10000000; //10MB 
$startMarkerWithExif=chr(hexdec("ff")).chr(hexdec("d8")).chr(hexdec("ff")).chr(hexdec("e1"));
$input_exts = array("img","bin","mov");

// use current dir
$path=".";
$destination = "0";

$move_processed = false;
$processed_subdir = "processed";

$forced_ext = "";

function print_help(){
  global $argv;
  
  echo <<<"TXT"
Help:
  * Usage:
    ~$ {$argv[0]} path=[path-to-dir] dest_path=[dest-subdir] move_processed=[move-processed-files] ext=[forced-ext]
    
    where:
      * path-to-dir            - string - scan this path + 1 dir down
    
  * Examples:

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

$list = preg_grep('/^([^.])/', scandir($path));

$FOOTAGE_ARRAY = Array();

foreach ($list as $item) {
  if (is_dir("$path/$item")){
    if ($item==$processed_subdir) continue;
    $sublist = preg_grep('/^([^.])/', scandir("$path/$item"));
    foreach($sublist as $subitem){
        register_file("$path/$item","$subitem","../$destination");
    }
  }else{
    register_file("$path","$item","$destination");
  }
}

function register_file($path,$file,$destination){

  global $FOOTAGE_ARRAY;

  global $startMarkerWithExif;
  global $chunksize;
  global $input_exts;
  
  if (in_array(get_ext("$path/$file"),$input_exts)) {
    echo "Processing $path/$file\n";
    
    $markers=array();
    $offset =0;
    
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
    
    $old_footage_index = 0;
    
    for ($i=0;$i<(count($markers)-1);$i++) {

      fseek($f,$markers[$i]);
      $s = fread($f,$markers[$i+1]-$markers[$i]);

      $tmp_name = "$path/image.tmp";
      file_put_contents($tmp_name,$s);

      $result_name = elphel_specific_result_name($tmp_name);

      //echo "    $result_name\n";
      
      $tmp0 = explode(".",$result_name);
      $tmp1 = explode("_",$tmp0[0]);
      
      if (count($tmp1)==5){
        $footage_index = $tmp1[0]."_".$tmp1[1];
        $footage_subelement = $tmp1[2];
        
        if (!isset($FOOTAGE_ARRAY[$footage_index])){
          //echo "NEW: $footage_index, OLD: $old_footage_index\n";
          $FOOTAGE_ARRAY[$footage_index] = Array();
          $FOOTAGE_ARRAY[$footage_index]['error'] = 0;
          $FOOTAGE_ARRAY[$footage_index]['warning'] = 0;
          if (isset($FOOTAGE_ARRAY[$old_footage_index]['data'][0]['number'])){
            $FOOTAGE_ARRAY[$footage_index]['prevnumber'] = $FOOTAGE_ARRAY[$old_footage_index]['data'][0]['number'];
            //echo "set prev to ".$FOOTAGE_ARRAY[$old_footage_index]['data'][0]['number']."\n";
          }else{
            $FOOTAGE_ARRAY[$footage_index]['prevnumber'] = 0;
          }
          $FOOTAGE_ARRAY[$footage_index]['data'] = Array();
          $old_footage_index = $footage_index;
        }else{
          if ($footage_index!=$old_footage_index){
            #$FOOTAGE_ARRAY[$old_footage_index]['warning'] |= 0x1;
            $FOOTAGE_ARRAY[$footage_index]['warning'] |= 0x1;
            echo "\033[38;5;214m";
            echo "notice: timestamps and ports overlap: $footage_index, index: $footage_subelement";
            echo "\033[0m";
            echo "\n";
          }
        } 
        
        //echo "    pushed to $footage_index: index: $footage_subelement, number: {$tmp1[3]}\n";
        array_push($FOOTAGE_ARRAY[$footage_index]['data'],Array('index'=>$footage_subelement,'number'=>$tmp1[3],'size'=>$tmp1[4]));
      }
      
      unlink($tmp_name);
      //rename($tmp_name,"$path/$destination/$result_name");
    }
    
    // Analisys phase
    foreach($FOOTAGE_ARRAY as $key=>$val){
      if (count($val['data'])==4){
        $in = $val['data'][0]['number'];
        
        if ($in!=($val['prevnumber']+1)&&($val['prevnumber']!=0)){
          //echo "Meet the prevnumber ".$val['prevnumber']." vs current ".$in."\n";
          $FOOTAGE_ARRAY[$key]['error'] |= 0x2;
        }
        
        foreach($val['data'] as $k2=>$v2){
          if ($in!=$v2['number']){
            $FOOTAGE_ARRAY[$key]['error'] |= 0x4;
          }
        }
      }else{
        $FOOTAGE_ARRAY[$key]['error'] |= 0x1;
      }
      
    }
    
    // Report phase
    echo "Total files: $i\n";
    echo "Unique timestamps: ".count($FOOTAGE_ARRAY)."\n";
    
    foreach($FOOTAGE_ARRAY as $key=>$val){
      if      ($val['error']!=0)   echo "\033[91m";
      else if ($val['warning']!=0) echo "\033[38;5;214m";
      //echo "$key: ".implode(",",$val['data']);
      echo "n= ".$val['data'][0]['number']." : ts= $key : ports=";
      
      for($j=0;$j<4;$j++){
        if (isset($val['data'][$j])){
          echo $val['data'][$j]['index'];
        }else{
          echo " ";
        }
        if ($j!=3) echo ",";
      }
      
      echo " : sizes(MB)= ";
      
      for($j=0;$j<4;$j++){
        if (isset($val['data'][$j])){
          $tmp = number_format(round(($val['data'][$j]['size'])/1024/1024,2),2,".","");
        }else{
          $tmp = "";
        }

        echo str_pad($tmp,4);
        
        if ($j!=3) echo ", ";
      }


      if ($val['error']!=0){
        echo " : error code=".$val['error'];
      }
      
      if ($val['error']!=0||$val['warning']!=0) echo "\033[0m";
      echo "\n";
    }
    
    // reset 
    $FOOTAGE_ARRAY = Array();
    
    return 0;
  }else{
    return -1;
  }
}

function elphel_specific_result_name($file){

  global $forced_ext;

  $exif = exif_read_data($file);
  
  $ext = elphel_specific_result_ext($exif,$forced_ext);
  
  //converting GMT a local time GMT+7
  $timestamp_local=strtotime($exif['DateTimeOriginal']);/*-25200;*/
  
  $subsecs = $exif['SubSecTimeOriginal'];
  
  $tmp = explode("_",$exif['Model']);
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
      $k = intval($exif['PageNumber'])+1;
    }
    
  }else{
    $k = intval($exif['PageNumber'])+1;
  }
  
  $img_number = $exif['ImageNumber'];
  $fsize = $exif['FileSize'];
  
  return "{$timestamp_local}_{$subsecs}_{$k}_{$img_number}_{$fsize}.$ext";
  
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
