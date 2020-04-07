#!/bin/bash

cd vendor/composer/composer
git checkout -q 1.10
git fetch -q origin
git rebase -q origin/1.10
cd ../../..
