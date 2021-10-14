#!/bin/bash

git fetch -q origin || true
diffs=$(git diff origin/main | wc -l)

if [ "0" != "$diffs" ]
then
    git pull -q origin main || (git clean -qfd && git pull -q origin main)
    composer install -q
fi
