#!/usr/bin/env python3

import sys
import os
import subprocess

try:
  src = sys.argv[1]
except IndexError:
  src = "."

try:
  dst = sys.argv[2]
except IndexError:
  dst = "."

lst = os.listdir(src)
lst.sort()

for f in lst:

  path = os.path.join(src,f)
  
  if os.path.isfile(path):
    print("Annotating "+f)

    border  = f"-border 15 -bordercolor '#00000080'"
    label   = f"-pointsize 100 -background none -fill white label:'{f}'"
    gravity = f"-gravity southwest -geometry +30+30"

    cmd = f"convert {border} {label} miff:- | composite {gravity} - {src}/{f} {dst}/{f}"
    
    subprocess.check_output(cmd,shell=True)
    #convert 1562389949_821613_0.jpeg -pointsize 100 -fill black -undercolor white -gravity southwest -annotate +10+10 "filename" test.jpeg

