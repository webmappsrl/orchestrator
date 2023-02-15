#!/bin/bash

docker compose up -d

echo "Do you want to install and activate xdebug? y/n"
read xdebug

if [[ $xdebug = y ]]
then
    bash docker/configs/phpfpm/init-xdebug.sh
fi
