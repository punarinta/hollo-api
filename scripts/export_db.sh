#!/bin/bash

# Check 2 arguments are given
if [ $# -lt 1 ]
then
        echo "Usage : $0 source target"
        echo "Example : $0 live local"
        echo "Source : live, stage, reset-stage"
        echo "Target : stage, local"
        exit
fi

case "$1" in
    # Run from live
    'live') echo "Running from live!"
        case "$2" in
            'stage')
                echo "Copying from live to stage."
                # run script to move live to stage
                ssh root@1-lom.s.coursio.com '/apps/db_scripts/live_to_stage.sh'
            ;;
            'local')
                echo "Copying from live to local."
                ssh root@1-lom.s.coursio.com '/apps/db_scripts/live_to_local.sh'

                # Copy here
                scp root@1-lom.s.coursio.com:/root/local.sql .

                # Drop old database
                mysqladmin -u root -f drop coursio

                # Create database
                mysqladmin -u root create coursio

                # Import
                mysql -uroot -D coursio < local.sql

                # Remove remote
                ssh root@1-lom.s.coursio.com 'rm /root/local.sql'

                # Remove local
                rm local.sql
            ;;
            'local-all')
                echo "Copying from live to local."
                ssh root@1-lom.s.coursio.com '/apps/db_scripts/live_to_local_all.sh'

                # Copy here
                scp root@1-lom.s.coursio.com:/root/local.sql .

                # Drop old database
                mysqladmin -u root -f drop coursio

                # Create database
                mysqladmin -u root create coursio

                # Import
                mysql -uroot -D coursio < local.sql

                # Remove remote
                ssh root@1-lom.s.coursio.com 'rm /root/local.sql'

                # Remove local
                rm local.sql
            ;;
            *) echo "Invalid target"
               ;;
        esac
        ;;
    # Run from stage
    'stage') echo "Running from stage:"
        case "$2" in
            'local')
                echo "Copying from stage to local."
                ssh root@1-lom.s.coursio.com '/apps/db_scripts/stage_to_local.sh'

                # Copy here
                scp root@1-lom.s.coursio.com:/root/local.sql .

                # Drop old database
                mysqladmin -u root -f drop coursio

                # Create database
                mysqladmin -u root create coursio

                # Import
                mysql -uroot -D coursio < local.sql

                # Remove remote
                ssh root@1-lom.s.coursio.com 'rm /root/local.sql'

                # Remove local
                rm local.sql
            ;;
            *) echo "Invalid target"
               ;;
        esac
        ;;
    'reset-stage')
        echo "Resetting stage from local dump."
        # run script to copy local dump to db-server
        scp ~/src/app/data/db/dumps/`ls -l ~/src/app/data/db/dumps | awk '/coursio/ { f=$NF };END{ print f }'` root@1-lom.s.coursio.com:/root/stage_import.sql
        # run script to import
        ssh root@1-lom.s.coursio.com '/apps/db_scripts/stage_from_local.sh'
    ;;
    *) echo "Invalid option"
       ;;
esac
