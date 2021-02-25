#!/bin/bash

cd vendor/composer/composer
git checkout -q 2.0
git fetch -q origin
git rebase -q origin/2.0 > /dev/null
cd ../../..
