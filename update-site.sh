#!/bin/bash

cd "$(dirname "$0")" || exit 1

git fetch -q origin || exit 0
before=$(git rev-parse HEAD)

if [ "$before" == "$(git rev-parse origin/main)" ]
then
    exit 0
fi

git pull -q origin main || (git clean -qfd && git pull -q origin main) || exit 1

if ! git diff --quiet "$before" HEAD -- composer.json composer.lock
then
    composer install -q --no-interaction --no-dev -o || exit 1
    # install resets vendor/composer/composer to the locked ref, bring the docs back to main
    ./update-docs.sh
fi

rm -rf ./var/cache/

if sudo -n cachetool opcache:reset > /dev/null 2>&1
then
    # requests between the rm and the reset may have rebuilt the cache from stale bytecode
    rm -rf ./var/cache/
else
    echo "Could not reset opcache, run 'cachetool opcache:reset' as root" >&2
fi
