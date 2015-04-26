#!/bin/bash
cd vendor/composer/composer
git checkout -q master
git fetch -q origin
git rebase -q origin/master
cd ../../..
