#!/usr/bin/env php
<?php

/**
 * @file save_image_ext.php
 * @brief run from host pc, trigger and download images (from all ports) to the host pc.
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

if (isset($argv[1])) $_GET['ip'] = $argv[1];
if (isset($_GET['ip'])) $ip = $_GET['ip'];

$path = "images";

$port0 = 2323;

if (!is_dir($path)) {
    $old = umask(0);
    mkdir($path,0777);
    umask($old);
}

$oldp = simplexml_load_file("http://$ip/parsedit.php?immediate&TRIG_PERIOD");
$period = $oldp->TRIG_PERIOD;

$error = false;

if ($fp = simplexml_load_file("http://$ip:2323/trig/pointers")) {

    //elphel_set_P_value(2,ELPHEL_TRIG_PERIOD,0,ELPHEL_CONST_FRAME_IMMED);
    //usleep(200000);
    //elphel_set_P_value(2,ELPHEL_TRIG_PERIOD,1,ELPHEL_CONST_FRAME_IMMED);
    usleep(200000);
    //$system_status = system("./images.sh $ip $n $path");
    for($i=0;$i<4;$i++){
        $port = $port0+$i;
        exec("wget $ip:$port/img -O $path/{$ip}_{$port}.jp4 -o $path/{$ip}_{$port}.log");
        rename_image($path,"{$ip}_{$port}.jp4");
    }
    //why fopen?
    //$add_str = "http://{$master['ip']}/camogm_interface.php?cmd=set_parameter&pname=TRIG_PERIOD&pvalue=";
    $addr_str = "http://$ip/parsedit.php?immediate&TRIG_PERIOD=";
    $fp = fopen($addr_str.($period+1)."*-2&sensor_port=0", 'r');
    $fp = fopen($addr_str.($period)."*-2&sensor_port=0", 'r');
}else{
    $error = true;
}

$res_xml = "<Document>\n";
if ($error){
  $res_xml .= "\t<result>error</result>\n";
}else{
  $res_xml .= "\t<result>ok</result>\n";
}
$res_xml .= "</Document>";

header("Content-Type: text/xml");
header("Content-Length: ".strlen($res_xml)."\n");
header("Pragma: no-cache\n");
printf("%s", $res_xml);
flush();

function rename_image($path,$file){
	//read exif & rename
	$ext = pathinfo("$path/$file", PATHINFO_EXTENSION);
	$exif_data = @exif_read_data("$path/$file");
	//converting GMT a local time GMT+7
	$DateTimeOriginal_local=@strtotime($exif_data['DateTimeOriginal']);/*-25200;*/
	
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
          }else{
            $k=$chn;
          }
          $new_file_name = $DateTimeOriginal_local."_".$exif_data['SubSecTimeOriginal']."_$k.".$ext;
          rename("$path/$file","$path/$new_file_name");
	}
}

?>