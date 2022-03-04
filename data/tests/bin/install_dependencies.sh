#!/usr/bin/env bash
phpversion="$(php --version | head -n 1 | cut -d " " -f 2 | cut -c 1,3)";

if [[ ${phpversion} = '72' ]]; then
    echo "dependencies for PHP 7+ (${phpversion})";
    sudo apt-get update \
        && sudo apt-get install -y libyaml-dev wget grep procps
    printf "\n" | pecl install yaml
    exit 0;
fi;

if [[ ${phpversion} = '73' || ${phpversion} = '74' || ${phpversion} = '80' || ${phpversion} = '81' ]]; then
    echo "dependencies for PHP 7.3 (${phpversion})";
    sudo apt-get update \
        && sudo apt-get install -y libyaml-dev wget grep procps
    printf "\n" | pecl install yaml-2.0.4
    exit 0;
fi;

echo "No dependencies defined for ${phpversion}"
exit 0;
