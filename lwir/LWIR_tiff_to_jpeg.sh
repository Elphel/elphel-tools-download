#!/usr/bin/sh

#convert 1562389844_064093_0.tiff -crop 160x120+0+0 -auto-level test.jpeg
# cuts off telemetry lines in the bottom (160x122 original images)
mogrify -crop 160x120+0+0 -auto-level -format jpeg -path ./lwir_jpeg lwir/*.tiff
