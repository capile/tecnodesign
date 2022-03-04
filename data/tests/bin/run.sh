#!/usr/bin/env bash
phpVersion="$(php --version | head -n 1 | cut -d " " -f 2 | cut -c 1,3)";
phpMajor="$(php --version | head -n 1 | cut -d " " -f 2 | cut -c 1)";

if [[ ${phpMajor} = '7' ]]; then
    echo "Codeception phar for PHP 7.x";
    codecept=./codecept7.phar
    [[ ! -f ${codecept} ]] && wget https://codeception.com/codecept.phar -O $codecept
    [[ -f codecept ]] && rm -f codecept
    ln -s ${codecept} codecept
fi;

chmod +x ${codecept}
if ! ps aux | grep -v grep | grep -qs '0.0.0.0:9999';
then
  ./app-server
fi

${codecept} run -v

exit 0;
