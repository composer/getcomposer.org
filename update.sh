#!/bin/bash

# config
root=`pwd`
build="composer-src"
buildscript="bin/compile"
buildphar="composer.phar"
target="web"
repo="https://github.com/composer/composer.git"
composer="composer"

# init
if [ ! -d "$root/$build" ]
then
    cd $root
    git clone $repo $build
fi

cd "$root/$build"

# update master
git fetch -q origin && \
git fetch --tags -q origin && \
git checkout master -q && \
git rebase origin/master -q && \
$composer install -q --no-dev && \
php -d phar.readonly=0 $buildscript && \
mv $buildphar "$root/$target/$buildphar" && \
git log --pretty="%H" -n1 HEAD > "$root/$target/version"

# create tagged releases
for version in `git tag`; do
    if [ ! -f "$root/$target/download/$version/$buildphar" ]
    then
        mkdir -p "$root/$target/download/$version/"
        git checkout $version -q && \
        $composer install -q --no-dev && \
        php -d phar.readonly=0 $buildscript && \
        touch --date="`git log -n1 --pretty=%ci $version`" $buildphar && \
        mv $buildphar "$root/$target/download/$version/$buildphar"
        echo "$target/download/$version/$buildphar was just built and should be downloaded/committed to the repo"
    else
        touch --date="`git log -n1 --pretty=%ci $version`" "$root/$target/download/$version/$buildphar"
    fi
done
