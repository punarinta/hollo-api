#!/bin/bash

cd "$(dirname "$0")"
cd ../data/files/avatars

scp root@deathstar.s.coursio.com:/apps/mls-api/files/avatars/* .
