#!/usr/bin/env python3

import os
import sys
import numpy as np

# returns timestamp in microseconds from 1970
def helper_ts(s):
  tmp = s.split('.')
  tmp = tmp[0]
  tmp = tmp.split('_')
  return 1000000*int(tmp[0])+int(tmp[1])

def helper_update_name(s,b=0):
  tmp = s.split('.')
  ext = tmp[1]
  tmp = tmp[0]
  tmp2 = tmp.split('_')
  tmp2[2] = str(int(tmp2[2])+b)

  return '_'.join(tmp2)+"."+ext


# source files dirs
srcs = ['lwir/results','visible/results']
# number of channels in complete set
srcs_chn_n           = [4, 4]
# correction if files carry wrong indices
srcs_chn_offsets     = [0, 4]
# positive or negative
srcs_latencies_us    = [0, -228000]
# allowed discrepancy in timestamps within a set
srcs_ts_precision_us = [10000,10000]

dst = 'sets'



srcs_complete_set_n = sum(srcs_chn_n)



print("Step 1: filtering")

# scan all
file_lists = [os.listdir(x) for x in srcs]
for a in file_lists:
  a.sort()

# take ts list from the file_lists[0], based in '_0.' pattern
base_ts_list = [helper_ts(x) for x in file_lists[0] if '_0.' in x]

results_list = [[] for x in base_ts_list]

for i,j in enumerate(base_ts_list):

  for k,l in enumerate(file_lists):

    for m in l:

      delta = helper_ts(m) - srcs_latencies_us[k] - j
      #delta = helper_ts(m) - j
      if abs(delta)<abs(srcs_ts_precision_us[k]):
        results_list[i].append(m)

      #if (k==1):
      #  print(str(helper_ts(m))+" "+str(srcs_latencies_us[k])+" "+str(j)+" "+str(delta))

      if (delta>srcs_ts_precision_us[k]):
        break

  #print(i)
  #print(results_list[i])

  #if i==100:
  #  break



print("Step 2: copying")



os.makedirs(dst, exist_ok=True)

for i,j in enumerate(base_ts_list):

  if len(results_list[i])==srcs_complete_set_n:

    s = str(j)
    dirname = s[0:10]+"_"+s[10:len(s)]

    print("Copying "+dirname)

    dst_path = os.path.join(dst,dirname)
    os.makedirs(dst_path, exist_ok=True)

    # now need to iterate
    tmp_i = 0

    for k in range(len(srcs)):
      for l in range(srcs_chn_n[k]):
        # tmp_i+l
        src_fname = results_list[i][tmp_i+l]
        dst_fname = helper_update_name(src_fname,srcs_chn_offsets[k])

        sp = os.path.join(srcs[k],src_fname)
        dp = os.path.join(dst_path,dst_fname)

        os.system("cp "+sp+" "+dp)

      tmp_i += srcs_chn_n[k]





























