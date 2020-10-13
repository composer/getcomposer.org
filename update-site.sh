#!/bin/bash

git fetch -q origin || true
diffs=$(git diff origin/master | wc -l)

if [ "0" != "$diffs" ]
then
    git pull -q origin master || (git clean -qfd && git pull -q origin master)
    composer install -q
fi
