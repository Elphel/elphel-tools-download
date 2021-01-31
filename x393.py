__author__ = "Elphel"
__copyright__ = "Copyright 2016, Elphel, Inc."
__license__ = "GPL"
__version__ = "3.0+"
__maintainer__ = "Oleg K Dzhimiev"
__email__ = "oleg@elphel.com"
__status__ = "Development"

import os
import re
import subprocess
import time
import tempfile

def shout(cmd):
  #subprocess.call prints to console
  #subprocess.call(cmd,shell=True)
  ret_str = ""
  try:
    ret_str = subprocess.check_output(cmd,shell=True)
  except subprocess.CalledProcessError as e:
    ret_str = str(e.returncode)
  return ret_str

#http://stackoverflow.com/questions/287871/print-in-terminal-with-colors-using-python
class bcolors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKGREEN = '\033[92m'
    WARNING = '\033[38;5;214m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    BOLDWHITE = '\033[1;37m'
    UNDERLINE = '\033[4m'

class Camera:
  def __init__(self,user="root",ip="192.168.0.9"):
    self.user = user
    self.ip = ip
    self.sshcmd = "ssh "+user+"@"+ip
    self.disable = False
    self.pattern = "ata-"
    self.check_connection()
    
  def check_connection(self):
    check = shout(self.sshcmd+" 'dmesg'")
    if check.strip() == str(255):
      self.disable = True
    else:
      print(self.user+"@"+self.ip+": connection ok")
  
  def ssd_to_pc(self):
    self.unmount_all()
    self.unload_ahci_driver()
    self.mux_ssd_to_esata()
    print(self.user+"@"+self.ip+": Enabled connection: internal SSD <-> PC")
  
  def ssd_to_camera(self):
    self.load_ahci_driver()
    self.mux_ssd_to_cpu()
    print(self.user+"@"+self.ip+": Enabled connection: internal SSD <-> Camera")
  
  def partition_name(self,partition=""):
    if partition!="":
      res = shout(self.sshcmd+" 'ls -all /dev/disk/by-id | grep '"+self.pattern+"' | grep '"+partition[-4:]+"''")
      if res!="1":
        for name in res.split(" "):
          if name[0:len(self.pattern)]==self.pattern:
            return name[len(self.pattern):]
    return "undefined"
  
  def list_partitions(self):
    res = shout(self.sshcmd+" 'cat /proc/partitions'")
    plist = []
    for line in res.splitlines():
      s0 = re.search('sd[a-z][0-9]',line)
      if s0:
        plist.append(s0.group(0))
      else:
        s0 = re.search('sd[a-z]',line)
        if s0:
          plist.append(s0.group(0))
    return plist
  
  def list_mounted_partitions(self):
    res = shout(self.sshcmd+" 'df -h'")
    df_list = []
    for line in res.splitlines():
      s0 = re.search('sd[a-z][0-9]',line)
      if s0:
        df_list.append(s0.group(0))
    return df_list
  
  # combine with list_partitions?
  def list_blkid(self):
    res = shout(self.sshcmd+" 'blkid'")
    blist = []
    for line in res.splitlines():
      s0 = re.search('sd[a-z][0-9]',line)
      if s0:
        s1 = re.search('TYPE=',line)
        if s1:
          blist.append(s0.group(0))
    return blist
  
  def list_raw_partitions(self):
    plist = self.list_partitions()
    blist = self.list_blkid()
        
    for p in plist:
      if p in blist:
        plist.remove(p)
        plist.remove(p[0:-1])
    
    return plist
  
  def first_found_raw_partition(self):
    plist = self.list_raw_partitions()
    if len(plist)!=0:
      return "/dev/"+plist[0]
    else:
      return ""
  
  def first_found_raw_partition_name(self):
    p = self.first_found_raw_partition()
    return self.partition_name(p)
  
  def mux_ssd_to_esata(self):
    shout(self.sshcmd+" '/usr/local/bin/x393sata_control.py set_esata_ssd'")

  def mux_ssd_to_cpu(self):
    shout(self.sshcmd+" '/usr/local/bin/x393sata_control.py set_zynq_ssd'")

  def device_partitions(self,device):
    test = shout(self.sshcmd+" 'ls /dev/disk/by-id/ -all'")
    return test

  def unmount_all(self):
    blist = self.list_mounted_partitions()
    if len(blist)==0:
      print("umount: ok")
    else:
      for b in blist:
        print("umounting /dev/"+b)
        self.unmount("/dev/"+b)
      
  def unmount(self,partition):
    shout(self.sshcmd+" 'umount "+partition+"'")

  def unload_ahci_driver(self):
    shout(self.sshcmd+" 'rmmod ahci_elphel'")
  
  def load_ahci_driver(self):
    subprocess.Popen(self.sshcmd+" 'modprobe ahci_elphel'",shell=True)
    time.sleep(1)
    shout(self.sshcmd+" 'echo 1 > /sys/devices/soc0/amba@0/80000000.elphel-ahci/load_module'")

class PC():
  def __init__(self):
    self.pattern = "ata-"
  
  def list_partitions(self):
    res = shout("ls /dev/disk/by-id/ -all | grep '"+self.pattern+"'")
    plist = []
    for line in res.splitlines():
      items = line.split(" ")
      for name in items:
        if name[0:len(self.pattern)]==self.pattern:
          item = items[-1].split("/")
          plist.append([name[len(self.pattern):],item[-1]])
    return plist

  # mounts partition (/dev/sd?1), reads camogm.disk file
  # returns the download size from raw partition ((/dev/sd?2))
  def read_camogm_disk_file(self,part):

    result = 0

    tmp_mount_point = tempfile.mkdtemp()
    print("mounting "+part+" to "+tmp_mount_point)
    shout("sudo mount "+part+" "+tmp_mount_point)

    try:
      with open (tmp_mount_point+"/camogm.disk", "r") as myfile:
        data=myfile.readlines()
        if len(data)==2:
          l2 = data[1]
          pointers = l2.split("\t")
          pntr1 = int(pointers[1])
          pntr2 = int(pointers[2])
          result = float(pntr2-pntr1)*512/1024/1024/1024
    except IOError:
      print(tmp_mount_point+"/camogm.disk NOT FOUND")

    shout("sudo umount "+tmp_mount_point)
    os.rmdir(tmp_mount_point)

    return result


  def is_raw(self,part):
    res = shout("sudo blkid | grep "+str(part))
    typ = " TYPE=" # added space, otherwise: /dev/sdd2: PTTYPE="atari" PARTUUID="e174570b-02"
    s0 = re.search(typ,res)
    if s0:
      fstype = ""
      pars = res.split(" ")
      for par in pars:
        if par[0:len(typ)]==typ:
          fstype = par[len(typ):]
          break
      
      print(part+" partition has a filesystem: "+fstype)
      
      raise Exception(bcolors.FAIL+"Partition has a filesystem ("+fstype+"): mount and copy files manually"+bcolors.ENDC)

  def download(self,dest,part,dl_bs=20,dl_bc=512,dl_skip=0,dl_n=0):

    self.is_raw(part)

    print("Getting raw partition data from "+part)
    
    if not os.path.isdir(dest):
      os.mkdir(dest)
    
    dirname = self.partname(part)
    
    if dirname!="":
      dirname = dest+"/"+dirname
      if not os.path.isdir(dirname):
        os.mkdir(dirname)
      for i in range(0,dl_n+dl_skip):
        fname = dirname+"/"+"file_"+str(i)+".img"
        skip = i*(dl_bc-1)
        if i>=dl_skip:
          shout("sudo dd if="+part+" "+" of="+fname+" bs="+str(dl_bs)+"M count="+str(dl_bc)+" skip="+str(skip))
      
    
  def partname(self,partition):
    cmd = "ls /dev/disk/by-id/ -all | grep '"+self.pattern+"' | grep '"+partition[-4:]+"'"
    res = shout(cmd)
    for name in res.split(" "):
      if name[0:len(self.pattern)]==self.pattern:
        return name[len(self.pattern):]
    return "" 

