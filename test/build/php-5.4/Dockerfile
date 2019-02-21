FROM php:5.4-cli
RUN apt-get update \
 && apt-get install -y libyaml-dev

RUN pecl channel-update pecl.php.net
RUN pecl install xdebug-2.4.1 yaml-1.3.2
RUN printf "# Default configuration file" > "/usr/local/etc/php/conf.d/php.ini"
RUN printf "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20100525/xdebug.so\nxdebug.remote_enable=1\nxdebug.remote_connect_back=1\nxdebug.idekey=PHPSTORM\n" > "/usr/local/etc/php/conf.d/xdebug.ini"
RUN printf "extension=yaml.so" > "/usr/local/etc/php/conf.d/yaml.ini"
