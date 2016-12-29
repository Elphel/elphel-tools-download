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

usage = """

help:

1. To locate a newly connected SSD device, run:

  $ dmesg
  
[] scsi 0:0:0:0: Direct-Access     ATA      Crucial_CT250MX2 MU02 PQ: 0 ANSI: 5
[] ata1.00: Enabling discard_zeroes_data
[] sd 0:0:0:0: Attached scsi generic sg0 type 0
[] sd 0:0:0:0: [sdb] 488397168 512-byte logical blocks: (250 GB/232 GiB)
[] sd 0:0:0:0: [sdb] 4096-byte physical blocks
[] sd 0:0:0:0: [sdb] Write Protect is off
[] sd 0:0:0:0: [sdb] Mode Sense: 00 3a 00 00
[] sd 0:0:0:0: [sdb] Write cache: enabled, read cache: enabled, doesn't support DPO or FUA
[] ata1.00: Enabling discard_zeroes_data
[]  sdb: sdb1 sdb2
[] ata1.00: Enabling discard_zeroes_data
[] sd 0:0:0:0: [sda] Attached SCSI disk

2. To locate a raw partition on the disk, run:
  
  $ sudo blkid
  
/dev/sdb1: UUID="ffffffff-ffff-ffff-ffff-ffffffffffff" TYPE="ext4" PARTUUID="ffffffff-ffff-ffff-ffff-ffffffffffff"
/dev/sdb2: PARTUUID="ffffffff-ffff-ffff-ffff-ffffffffffff"

* raw partition will have PARTUUID only 


examples:\n

I.
* SSD is connected to PC using a docking station/enclosure/etc.
* The data is on the raw partition /dev/sdb2
* Destination path: /data/footage/test
  Download:
  * Whole partition in 10GB chunks:
    {1}${0} -p /dev/sdb2 /data/footage/test{2}
  * Whole partition in 1GB chunks:
    {1}${0} -p /dev/sdb2 -bc 50 /data/footage/test{2}
  * 5GB in 1GB chunks, skip the first 3GBs:
    {1}${0} -p /dev/sdb2 -bc 50 -s 3 -n 5 /data/footage/test{2}

Note: Repeat for any other external SSDs of the multi-camera system

""".format(sys.argv[0],bcolors.OKGREEN,bcolors.ENDC)

parser = argparse.ArgumentParser(description=usage,formatter_class=argparse.RawDescriptionHelpFormatter)
parser.add_argument("-p","--partition",default="",help="partition name, example: /dev/sdb2")
parser.add_argument("-s","--skip",type=int,default=0, help="Number of chunks to skip from the beginning")
parser.add_argument("-n","--n",type=int,default=0,help="Number of chunks to download")
parser.add_argument("-bs",type=int,default=20,help="block size in MB, default = 20")
parser.add_argument("-bc",type=int,default=512,help="Number of blocks of size [bs] in a single chunk, default = 512, so the default chunk size is 10GB")
parser.add_argument("dest",help="desitnation directory: /data/footage/test")

args = parser.parse_args()

if args.partition!="":
  pc = x393.PC()
  if args.n==0:
    args.bc = 512*1000
    args.n = 1
  pc.download(args.dest,args.partition,args.bs,args.bc,args.skip,args.n)
else:
  print(bcolors.WARNING+"Please specify a partition"+bcolors.ENDC)
  print(usage)

