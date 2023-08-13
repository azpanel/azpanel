#!/bin/bash

version=$(git log --format="%ct" | wc -l)
big_v='1'
medium_v='2'
small_v=$(expr ${version} - 211)
hash=$(git log -1 --format="%h")

if [[ -e "/usr/local/php8.1/bin/php" ]];then
    /usr/local/php8.1/bin/php think tools --action setVersion --newVersion "${big_v}.${medium_v}.${small_v} ${hash}"
else
    php think tools --action setVersion --newVersion "${big_v}.${medium_v}.${small_v} ${hash}"
fi
