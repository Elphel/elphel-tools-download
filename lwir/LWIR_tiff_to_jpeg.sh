#!/usr/bin/sh

#convert 1562389844_064093_0.tiff -crop 160x120+0+0 -auto-level test.jpeg

mogrify -crop 160x120+0+0 -auto-level -format jpeg -path ./lwir_jpeg lwir/*.tiff
