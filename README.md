# elphel-tools-x393

Scripts for working with 10393 camera systems from a host PC

* **ext_ssd_download.py**
1. Copies data (using *dd*) from a raw partition on an SSD/HDD connected a host PC to a specified destination path

* **int_ssd_download.py**
1. Connects to a camera
2. Finds the first raw partition on the internal SSD
3. Switches VSC3304 to connect SSD to PC
4. Copy to specified destination path using *dd*
5. Switches VSC3304 back to connect SSD to x393

Supports multiple cameras. A preset option for Eyesis4Pi-393 cameras: **-m eyesis4pi**
