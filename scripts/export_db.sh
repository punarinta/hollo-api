#!/bin/bash

cd "$(dirname "$0")/.."

echo 'Dumping the database and packing the result...'
ssh root@db01 'mongodump --host=95.85.26.144 --db=hollo && tar -zcvf dump.tar.gz dump/ && rm -rf dump'

echo 'Copying to this machine...'
scp root@db01:/root/dump.tar.gz data/
tar -xvzf data/dump.tar.gz -C data

echo 'Importing...'
mongorestore --drop --dir=data/dump

echo 'Cleaning up...'
ssh root@db01 'rm /root/dump.tar.gz'
rm data/dump.tar.gz
rm -rf data/dump

echo 'Done.'
