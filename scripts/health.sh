#!/bin/bash

FREE_MEM=$(free | grep Mem | awk '{print $7/$2}')
FREE_DISK=$(df | grep /$ | awk '{print $4/$2}')
CPU_5_MIN=$(uptime | awk '{print $11}')

JSON='{"cpuLoad":'${CPU_5_MIN}'"freeMem":'${FREE_MEM}',"freeDisk":'${FREE_DISK}'}'

echo $JSON
exit

curl -i \
-H "Accept: application/json" \
-H "Content-Type:application/json" \
-X POST --data $JSON "http://notify.hollo.email:81"
