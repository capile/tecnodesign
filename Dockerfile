## tecnodesign/studio:v1.1
#
# docker build -f Dockerfile  . -t tecnodesign/studio:v1.1
# docker push tecnodesign/studio:v1.1
FROM php:fpm

RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev zlib1g-dev libzip-dev libonig-dev libxml2-dev zip git gnupg
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp
RUN apt-get install libldap2-dev -y && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/
RUN docker-php-ext-install mbstring zip gd simplexml dom fileinfo ctype pdo pdo_mysql ldap soap
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN sed -e 's/expose_php = On/expose_php = Off/' \
        -e 's/max_execution_time = 30/max_execution_time = 10/' \
        -e 's/max_input_time = 60/max_input_time = 5/' \
        -e '/catch_workers_output/s/^;//'  \
        -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -e 's/^;error_log.*/error_log = \/dev\/stderr/' \
        -e 's/post_max_size = 8M/post_max_size = 4M/' \
        -e 's/;default_charset = "UTF-8"/default_charset = "UTF-8"/' \
        -e 's/;max_input_vars = 1000/max_input_vars = 10000/' \
        -e 's/;date.timezone =/date.timezone = UTC/' \
        -i /usr/local/etc/php/php.ini
RUN echo 'max_input_vars = 10000' > /usr/local/etc/php/conf.d/x-config.ini
RUN sed -e 's/^listen = .*/listen = 9000/' \
        -e 's/^listen\.allowed_clients/;listen.allowed_clients/' \
        -e 's/^user = apache/user = www-data/' \
        -e 's/;catch_workers_output.*/catch_workers_output = yes/' \
        -e 's/^group = apache/group = www-data/' \
        -e 's/^php_admin_value\[error_log\]/;php_admin_value[error_log]/' \
        -e 's/^;?php_admin_value[memory_limit] = .*/php_admin_value[memory_limit] = 32M/' \
        -i /usr/local/etc/php-fpm.d/www.conf
RUN sed -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -i /usr/local/etc/php-fpm.conf

COPY --from=node:lts /usr/local/bin/node /usr/local/bin/node
COPY --from=node:lts /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/asar/bin/asar.js         /usr/local/bin/asar      && \
    ln -s ../lib/node_modules/node-gyp/bin/node-gyp.js /usr/local/bin/node-gyp  && \
    ln -s ../lib/node_modules/nopt/bin/nopt.js         /usr/local/bin/nopt      && \
    ln -s ../lib/node_modules/npm/bin/npm-cli.js       /usr/local/bin/npm       && \
    ln -s ../lib/node_modules/npm/bin/npx-cli.js       /usr/local/bin/npx       && \
    ln -s ../lib/node_modules/semver/bin/semver.js     /usr/local/bin/semver    && \
    ln -s ../lib/node_modules/yarn/bin/yarn.js         /usr/local/bin/yarn      && \
    ln -s ../lib/node_modules/yarn/bin/yarn.js         /usr/local/bin/yarnpkg

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
## foxy compatibility issues with composer 2.1 (open)
RUN composer self-update 2.0.14

## nodejs and puppeteer support
RUN mkdir -p /app /var/www/.composer /var/www/.npm && \
    chown www-data:www-data /app /var/www/.composer /var/www/.npm
RUN apt-get install -y \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-dev \
    libcups2 \
    libdrm-dev \
    libxkbcommon-dev \
    libxcomposite-dev \
    libxdamage-dev \
    libxrandr-dev \
    libgbm-dev \
    libpango-1.0 \
    libcairo-dev \
    libasound-dev \
    libxshmfence-dev
USER www-data
WORKDIR /app

#COPY . /var/www/app
#RUN composer install --no-dev