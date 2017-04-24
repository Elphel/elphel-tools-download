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

function print_help(){
  global $argv;
  
  echo <<<"TXT"
Help:
  * Usage:
    ~$ {$argv[0]} path=[path-to-dir] dest_path=[dest-subdir]
    
    where:
      * path-to-dir            - string - work at this path + 1 dir down
      * dest-subdir            - string - save results to "path-to-dir/dest-subdir/"
    
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

if (isset($_GET['dest_path'])){
  $dest_path = $_GET['dest_path'];
}

if (!is_dir($dest_path)) {
  //creating a folder with access rights - 0777
  $old = umask(0);
  @mkdir($dest_path);
  umask($old);
}

$filelist = scandir($path);

echo "<pre>\n";

foreach ($filelist as $value) {
	//echo $value."\n";
	if ($value!=$dest_path) process_folder($value,"jp4");
}

function process_folder($file,$type) {

	global $path;
	global $processing_folder;
	global $dest_path;

	$tmp_arr = Array();

	$url = "$pre_path/$processing_folder";

	$ext=get_file_extension($file);

	// exclude "." & ".."
	if (substr($file,0,1)!=".") {
		if ($ext=="") {
		    if (is_dir($url."/".$file)) {
			//echo $url."  ".$file."\n";
			if ($type=="") {
			    // do nothing
			}
			else {
			    $list = scandir($url."/".$file);
			    // getting deeper into indexed subfodlers
			    foreach($list as $elem){
				if (get_file_extension($url."/".$file."/".$elem)==$type) {
				      //echo $url."/".$file."/".$elem."\n";
				      // initialize array
				      if (!isset($tmp_arr[substr($elem,0,17)])) $tmp_arr[substr($elem,0,17)] = 0;
				      // 9th and 10th images are not part of the panorama
				      //if (!strstr($elem,"_9.")&&!strstr($elem,"_10.")) 
				      $tmp_arr[substr($elem,0,17)]++;
				}
			    }
			    //do actual copying
			    //print_r($tmp_arr);
			    foreach($tmp_arr as $key=>$val){
				if ($val!=10) {
				    for ($i=1;$i<11;$i++){
					if (is_file("$url/$file/{$key}_$i.$type")){
                                          //echo "$url/$file/{$key}_$i.$type to $url/$dest_path/{$key}_$i.$type\n";
                                          rename("$url/$file/{$key}_$i.$type","$url/$dest_path/{$key}_$i.$type");
                                        }
				    }
				}
			    }
			}
		    }
		}else{
		    //do nothing
		}
	}
}

function get_file_extension($filename) {
	//return substr(strrchr($filename, '.'), 1);
	return pathinfo($filename, PATHINFO_EXTENSION);
}
  
?>