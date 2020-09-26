# elphel-tools-x393

Scripts for working with 10393 camera systems from a host PC.
What they do:
* Download footage from camera's internal storage over eSATA cable. Camera needs to be turned on. See [ext_ssd_download.py](ext_ssd_download.py).
* Download footage from external SSD (connected to PC with eSATA docking box or just SATA). See [int_ssd_download.py](int_ssd_download.py).
* Split footage files (movs or imgs) into separate images - tiffs ([extract_images_tiff.php](extract_images_tiff.php)) or jp4s/jpegs ([extract_images.php](extract_images.php)).
* To convert jp4s to jpegs use [**python3-convert-jp4**](https://git.elphel.com/Elphel/python3-convert-jp4) (separate repo) scripts.
* Combine/filter separate images (with different 'channels' but similar timestamps) into groups for post-processing ([footage_filter_lwir_visible.py](footage_filter_lwir_visible.py) and [eyesis4pi393/*](eyesis4pi393)).
* Batch convert tiffs to jpegs. See [lwir/LWIR_tiff_to_jpeg.sh](lwir/LWIR_tiff_to_jpeg.sh) - mogrify is aprt of imagemagick.
* Annotate any jpegs with their file names which are usually timestamps ([batch_timestamp-annotate_jpegs.by](batch_timestamp-annotate_jpegs.py)).
* Join jpegs (annotated or not) into mov clips - good for previewing and selecting image sets for processing (see inside [lwir/VISIBLE_jpeg_to_mov.sh](lwir/LWIR_jpeg_to_mov.sh)).

## Requirements
* python 2 and 3
* php
* imagemagick
* ffmpeg

## Details

* **ext_ssd_download.py**

Copies data (using *dd*) from a raw partition on an SSD/HDD connected a host PC to a specified destination path

* **int_ssd_download.py**

Connects to a camera (much more convenient if the keys are copied: *ssh-copy-id root@192.168.0.9*)

Finds the first raw partition on the internal SSD

Switches VSC3304 to connect SSD to PC

Copy to specified destination path using *dd*

Switches VSC3304 back to connect SSD to x393

Supports multiple cameras. A preset option for Eyesis4Pi-393 cameras: **-m eyesis4pi**
