#!/bin/bash

# args: privkey, privkey pwd, tags or dev

# config
root=`pwd`
build="composer-src"
buildscript="bin/compile"
buildphar="composer.phar"
target="web"
repo="https://github.com/composer/composer.git"
composer="composer"
privkeypath="$2"
privkeypwd="$3"

if [ "" == "$1" ]
then
    echo "Missing arg1: target (dev or tags)"
    exit 1
fi

if [ "" == "$2" ]
then
    echo "Missing arg2: path to private key"
    exit 1
fi

if [ "" == "$3" ]
then
    if [ "tags" == "$1" ]
    then
        echo -n 'Password: '
        stty -echo
        read -r privkeypwd
        stty echo
        echo ''
        echo 'Building versions'
    else
        echo "Missing arg3: password for private key"
        exit 1
    fi
fi

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
git checkout master -q --force && \
git rebase origin/master -q

version=`git log --pretty="%H" -n1 HEAD`

# create latest dev build
if [ "dev" == "$1" ]
then
    if [ ! -f "$root/$target/$version" -o "$version" != "`cat \"$root/$target/version\"`" ]
    then
        $composer install -q --no-dev && \
        php -d phar.readonly=0 $buildscript && \
        touch --date="`git log -n1 --pretty=%ci HEAD`" "$buildphar" && \
        php "$root/bin/sign.php" "$buildphar" "$root/$privkeypath" "$privkeypwd" && \
        git reset --hard -q $version && \
        mv "$buildphar.sig" "$root/$target/$buildphar.sig" && \
        mv "$buildphar" "$root/$target/$buildphar" && \
        echo $version > "$root/$target/version_new" && \
        mv "$root/$target/version_new" "$root/$target/version"
    fi
fi

# create tagged releases
for version in `git tag`; do
    if [ ! -f "$root/$target/download/$version/$buildphar" ]
    then
        if [ "tags" != "$1" ]
        then
            echo "$version was found but not built, build should be ran manually to get the correct signature"
        else
            mkdir -p "$root/$target/download/$version/"
            git checkout $version -q && \
            $composer install -q --no-dev && \
            php -d phar.readonly=0 $buildscript && \
            touch --date="`git log -n1 --pretty=%ci $version`" "$buildphar" && \
            php "$root/bin/sign.php" "$buildphar" "$root/$privkeypath" "$privkeypwd" && \
            git reset --hard -q $version && \
            mv "$buildphar.sig" "$root/$target/download/$version/$buildphar.sig" && \
            mv "$buildphar" "$root/$target/download/$version/$buildphar"
            echo "$target/download/$version/$buildphar (and .sig) was just built and should be committed to the repo"
        fi
    else
        touch --date="`git log -n1 --pretty=%ci $version`" "$root/$target/download/$version/$buildphar"
    fi
done

# TODO once we have stable releases this should ignore -alphas and so on
lastVersion=$(ls "$root/$target/download" | xargs -I@ git log --format=format:"%ai @%n" -1 @ | sort -r | head -1 | awk '{print $4}')
lastSnapshot=$(head -c40 "$root/$target/version")
read -r -d '' versions << EOM
{
    "stable": [{"path": "/download/$lastVersion/composer.phar", "version": "$lastVersion", "min-php": 50300}],
    "preview": [{"path": "/download/$lastVersion/composer.phar", "version": "$lastVersion", "min-php": 50300}],
    "snapshot": [{"path": "/composer.phar", "version": "$lastSnapshot", "min-php": 50300}]
}
EOM
echo "$versions" > "$root/$target/versions_new" && mv "$root/$target/versions_new" "$root/$target/versions"
rm -f "$root/$target/stable"
