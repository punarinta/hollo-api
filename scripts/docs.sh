#!/bin/bash

cd "$(dirname "$0")"

cd ../tools/doc-gen
php main.php
cd ../../scripts

case "$1" in
    'open')
        if [ $(command -v see) ]
        then
            see ../public/docs/index.html
        else
            open https://api.mailless.dev/docs/index.html
        fi
        ;;
    *)  echo "Hint: run 'docs.sh open' to see docs in your browser."
        echo ""
       ;;
esac
