#!/usr/bin/env bash
phpversion="$(php --version | head -n 1 | cut -d " " -f 2 | cut -c 1,3)";

if [[ ${phpversion} = '54' ]]; then
    echo "dependencies for PHP 5.4";
    sudo apt-get update && apt-get install -y libyaml-dev
    printf "\n" | pecl install yaml-1.3.2
    echo "extension=yaml.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
    exit 0;
fi;

if [[ ${phpversion} = '70' || ${phpversion} = '71' || ${phpversion} = '72' || ${phpversion} = '73' ]]; then
    echo "dependencies for PHP 7+";
    sudo apt-get update \
        && apt-get install -y libyaml-dev
    printf "\n" | pecl install yaml
    echo "extension=yaml.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
    exit 0;
fi;

echo "No dependencies defined"
exit 0;
