#!/bin/bash

FREE_MEM=$(free | grep Mem | awk '{print $4/$2}')
FREE_DISK=$(df | grep /dev/sda1  | awk '{print $4/$2}')
CPU_1_MIN=$(uptime | awk '{print $8}')

JSON='{"cpuLoad":'${CPU_1_MIN}'"freeMem":'${FREE_MEM}',"freeDisk":'${FREE_DISK}'}'

echo $JSON

curl -i \
-H "Accept: application/json" \
-H "Content-Type:application/json" \
-X POST --data $JSON "http://requestb.in/1lrimbo1"
