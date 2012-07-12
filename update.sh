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
/usr/bin/git fetch -q origin && \
/usr/bin/git fetch --tags -q origin && \
/usr/bin/git checkout master -q && \
/usr/bin/git rebase origin/master -q && \
$composer install -q && \
/usr/local/bin/php -d phar.readonly=0 $buildscript && \
mv $buildphar "$root/$target/$buildphar" && \
/usr/bin/git log --pretty="%h" -n1 HEAD > "$root/$target/version"

# create tagged releases
for version in `git tag`; do
    if [ ! -f "$root/$target/download/$version/$buildphar" ]
    then
        mkdir -p "$root/$target/download/$version/"
        /usr/bin/git checkout $version -q && \
        $composer install -q && \
        /usr/local/bin/php -d phar.readonly=0 $buildscript && \
        touch --date="`git log -n1 --pretty=%ci $version`" $buildphar && \
        mv $buildphar "$root/$target/download/$version/$buildphar"
    fi
done
