# elphel-tools-x393

Scripts for working with 10393 camera systems from a host PC

* **ext_ssd_download.py**
Copies data (using *dd*) from a raw partition on an SSD/HDD connected a host PC to a specified destination path

* **int_ssd_download.py**
Connects to a camera
Finds the first raw partition on the internal SSD
Switches VSC3304 to connect SSD to PC
Copy to specified destination path using *dd*
Switches VSC3304 back to connect SSD to x393

Supports multiple cameras. A preset option for Eyesis4Pi-393 cameras: **-m eyesis4pi**
