#!/usr/bin/env php
<?php
/**
 * @file footage_filter.php
 * @brief filters out incomplete panorama sets from the footage directory
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

//disable the default time limit for php scripts.
set_time_limit(0);

//CONSTANTS
$path="/data/footage/test"; // footage root
$dest_path="trash"; // folder for collecting non-matching results
$type = "jp4";
$N = 10;

function print_help(){
  global $argv;
  
  echo <<<"TXT"
Help:
  * Usage:
    ~$ {$argv[0]} path=[path-to-dir] trash_path=[trash-subdir] n=[N]
    
    where:
      * path-to-dir            - string - scan for images at this path
      * trash-subdir           - string - move filtered out images to this path
      * N                      - integer - number of images in set - optional, default: 10
    
  * Examples:
    ** Filter all images at /data/footage/test/0 puts incomplete images to '/data/footage/test/trash':
      ~$ {$argv[0]} path=/data/footage/test/0 dest_path=/data/footage/test/trash

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

if (isset($_GET['trash_path'])){
  $dest_path = $_GET['trash_path'];
}

if (isset($_GET['n'])){
  $N = $_GET['n'];
}

if (!is_dir($dest_path)) {
  //creating a folder with access rights - 0777
  $old = umask(0);
  @mkdir($dest_path);
  umask($old);
}

$filelist = scandir($path);

$tmp_arr = Array();
$err_arr = Array();

foreach($filelist as $elem){
  if (get_file_extension($path."/".$elem)==$type) {
    //echo $url."/".$file."/".$elem."\n";
    // initialize array
    if (!isset($tmp_arr[substr($elem,0,17)])) $tmp_arr[substr($elem,0,17)] = 0;
    // 9th and 10th images are not part of the panorama
    //if (!strstr($elem,"_9.")&&!strstr($elem,"_10.")) 
    $tmp_arr[substr($elem,0,17)]++;
  }
}

if (count($tmp_arr)==0){
  die("No images found at the path. Exit.\n");
}

//do actual copying
//print_r($tmp_arr);
foreach($tmp_arr as $key=>$val){
  if ($val!=$N){
    for ($i=1;$i<=$N;$i++){
      if (is_file("$path/{$key}_$i.$type")){
        array_push($err_arr,"{$key}_$i.$type");
        //echo "$url/$file/{$key}_$i.$type to $url/$dest_path/{$key}_$i.$type\n";
        rename("$path/{$key}_$i.$type","$dest_path/{$key}_$i.$type");
      }
    }
  }
}

if (count($err_arr)!=0){
  print("Filtered out images:\n");
  print_r($err_arr);
}

print("Number of images that were filtered out is ".count($err_arr).". Done.\n");

function get_file_extension($filename) {
  //return substr(strrchr($filename, '.'), 1);
  return pathinfo($filename, PATHINFO_EXTENSION);
}
  
?>