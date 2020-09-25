#!/bin/sh

rm visible.mov

cat visible_jpeg_annotated/*.jpeg | ffmpeg -framerate 20 -f image2pipe -i - visible.mov
