#!/usr/bin/env python

# Download footage from ssd's raw partition:
# * standalone SSDs connected using a docking station or enclosure or else, raw partition, camera can be powered off
#   ** All information is in the Exif headers, so no need to match SSDs to subcameras
# * camera's internal SSD, raw partition, camera is powered on.
#

__author__ = "Elphel"
__copyright__ = "Copyright 2016, Elphel, Inc."
__license__ = "GPL"
__version__ = "3.0+"
__maintainer__ = "Oleg K Dzhimiev"
__email__ = "oleg@elphel.com"
__status__ = "Development"

import argparse
import os
import sys
import time

import x393
from x393 import bcolors

# TODO: Download imu logs

ssd_detect_timeout = 20

usage = """

keys:
  To access camera without a password run:
  
  $ ssh-copy-id root@192.168.0.9
  
  To create keys, run:
  
  $ ssh-keygen

examples:\n

I.
* internal SSD, camera is powered on
* The data is on the raw partition /dev/sdb2
* Destination path: /data/footage/test
  Download:
  * Whole partition in 10GB chunks:
    {1}${0} -c root@192.168.0.9 /data/footage/test{2}
  * Whole partition in 1GB chunks:
    {1}${0} -c root@192.168.0.9 -bc 50 /data/footage/test{2}
  * 5GB in 1GB chunks, skip the first 3GBs:
    {1}${0} -c root@192.168.0.9 -bc 50 -s 3 -n 5 /data/footage/test{2}
    
II. multiple cameras modes:
  * "eyesis4pi"
      * Whole partition in 10GB chunks:
        {1}${0} -m eyesis4pi /data/footage/test{2}  

""".format(sys.argv[0],bcolors.OKGREEN,bcolors.ENDC)

parser = argparse.ArgumentParser(description=usage,formatter_class=argparse.RawDescriptionHelpFormatter)
parser.add_argument("-c","--camera", default="", help="provide camera ip address if downloading data from internal SSD, default = root@192.168.0.9")
parser.add_argument("-m","--mode",default="",help="preset for multiple cameras, available modes: 'eyesis4pi'")
parser.add_argument("-s","--skip",type=int,default=0, help="Number of chunks to skip from the beginning")
parser.add_argument("-n","--n",type=int,default=0,help="Number of chunks to download")
parser.add_argument("-bs",type=int,default=20,help="block size in MB, default = 20")
parser.add_argument("-bc",type=int,default=512,help="Number of blocks of size [bs] in a single chunk, default = 512, so the default chunk size is 10GB")
parser.add_argument("dest",help="desitnation directory: /data/footage/test")

args = parser.parse_args()

cams = []
dirs = []

if args.camera!="":
  tmp = args.camera.split("@")
  if len(tmp)==2:
    cams = [{"user":tmp[0],"ip":tmp[1],"imu_log":0}]
elif args.mode=="eyesis4pi":
  cams = [
    {"user":"root","ip":"192.168.0.161","imu_log":0},
    {"user":"root","ip":"192.168.0.162","imu_log":0},
    {"user":"root","ip":"192.168.0.163","imu_log":0}
    ]
else:
  cams = [{"user":"root","ip":"192.168.0.9","imu_log":0}]
  print("running default: "+cams[0]['user']+"@"+cams[0]['ip'])

# init checks all connections
for cam in cams:
  cam['obj'] = x393.Camera(cam['user'],cam['ip'])
  if cam['obj'].disable:
    print(bcolors.WARNING+"Will skip "+cam['user']+"@"+cam['ip']+bcolors.ENDC)

cams[:] = [tmp for tmp in cams if not tmp['obj'].disable]

if len(cams)==0:
  print(usage)

pc = x393.PC()

# ssd to camera for all
for cam in cams:
  cam['obj'].ssd_to_camera()

# get raw partitions
for cam in cams:
  d = cam['obj'].first_found_raw_partition_name()
  if d!="undefined":
    dirs.append(d)
    print(cam['user']+"@"+cam['ip']+": raw partition name: "+d)
  else:
    cam['disable']=1
    print(bcolors.FAIL+cam['user']+"@"+cam['ip']+" : error: already switched or raw partition not found"+bcolors.ENDC)
    #raise Exception(cam['user']+"@"+cam['ip']+" : error: already switched or raw partition not found")

# no need
cams[:] = [tmp for tmp in cams if tmp.get('disable')!=1]

# switch ssd to pc for all (not caring which cable is in)
for cam in cams:
  cam['obj'].ssd_to_pc()

# download

plist = []
all_downloaded = False

for i in range(len(cams)):
  raw_input(bcolors.OKGREEN+"Connect camera (eSATA) to PC (eSATA/SATA). Press Enter to continue..."+bcolors.ENDC)
  proceed_to_next = False
  t = 0
  while not all_downloaded:
    plist = pc.list_partitions()
    for d in dirs:
      for p in plist:
        if d==p[0]:
          pc.download(args.dest,"/dev/"+p[1],args.bs,args.bc,args.skip,args.n)
          dirs.remove(d)
          proceed_to_next = True
          if len(dirs)!=0:
            print("wait for the next ssd")
          else:
            all_downloaded = True
          break
    if proceed_to_next:
      break
    print("Waiting for disks to show up:")
    print(dirs)
    t = t + 1
    time.sleep(1)
    if t>ssd_detect_timeout:
      print(bcolors.FAIL+"PC: Error: Disks were not found:")
      print(dirs)
      print(bcolors.ENDC)
      break
    #cam['rp_name'].ssd_to_pc()

# ssd to camera for all
for cam in cams:
  cam['obj'].ssd_to_camera()

print("Done")