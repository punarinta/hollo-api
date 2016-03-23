#!/bin/bash
find /apps/files -inum $1 -exec rm {} \;