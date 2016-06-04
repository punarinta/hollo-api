#!/bin/bash

echo "Copying from live to local."
ssh root@178.62.247.169 '/apps/db_scripts/live_to_local.sh'

# Copy here
scp root@178.62.247.169:/root/local.sql .

# Drop old database
mysqladmin -u root -f drop hollo

# Create database
mysqladmin -u root create hollo

# Import
mysql -uroot -D hollo < local.sql

# Remove remote
ssh root@178.62.247.169 'rm /root/local.sql'

# Remove local
rm local.sql
