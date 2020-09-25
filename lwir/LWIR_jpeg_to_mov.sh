#!/bin/sh

rm lwir.mov

cat lwir_jpeg/*.jpeg | ffmpeg -framerate 20 -f image2pipe -i - lwir.mov
