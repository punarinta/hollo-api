#!/bin/bash

echo "Copying from live to local."
ssh root@178.62.247.169 '/apps/db_scripts/live_to_local.sh'

# Copy here
scp root@178.62.247.169:/root/local.sql .

# Drop old database
mysqladmin -u root -p'password' -f drop hollo

# Create database
mysqladmin -u root -p'password' create hollo

# Import
mysql -uroot -p'password' -D hollo < local.sql

# Remove remote
ssh root@178.62.247.169 'rm /root/local.sql'

# Remove local
rm local.sql
