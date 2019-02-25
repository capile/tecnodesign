FROM php:5.4-cli
RUN apt-get update \
 && apt-get install -y apt-utils libyaml-dev git zip unzip

RUN pecl channel-update pecl.php.net
RUN pecl install xdebug-2.4.1 yaml-1.3.2
RUN docker-php-ext-install mbstring
# PHPStorm cannot find the PHP configuration if this file does not exists
RUN printf "# Default configuration file" > "/usr/local/etc/php/conf.d/php.ini"
RUN printf "zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20100525/xdebug.so\nxdebug.remote_enable=1\nxdebug.remote_connect_back=1\nxdebug.idekey=PHPSTORM\n" > "/usr/local/etc/php/conf.d/xdebug.ini"
RUN printf "extension=yaml.so" > "/usr/local/etc/php/conf.d/yaml.ini"

RUN curl -o /tmp/composer-setup.php https://getcomposer.org/installer \
    && curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig \
    # Make sure we're installing what we think we're installing!
    && php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" \
    && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer --snapshot \
    && rm -f /tmp/composer-setup.* \
    && chmod 777 /usr/local/bin/composer
