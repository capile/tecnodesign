## tecnodesign/studio:v1.1
#
# docker build -f Dockerfile  . -t tecnodesign/studio:v1.1
# docker push tecnodesign/studio:v1.1
FROM php:fpm

COPY --from=node:lts /usr/local/bin/node /usr/local/bin/node
COPY --from=node:lts /usr/local/bin/npm /usr/local/bin/npm
COPY --from=node:lts /usr/local/bin/npx /usr/local/bin/npx
COPY --from=node:lts /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get install -y libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev zlib1g-dev libzip-dev libonig-dev libxml2-dev zip git
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install mbstring zip gd simplexml dom fileinfo ctype pdo pdo_mysql

RUN mkdir -p /var/www/app
WORKDIR /var/www/app

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
        -e 's/^user = apache/user = nobody/' \
        -e 's/;catch_workers_output.*/catch_workers_output = yes/' \
        -e 's/^group = apache/group = nobody/' \
        -e 's/^php_admin_value\[error_log\]/;php_admin_value[error_log]/' \
        -e 's/^;?php_admin_value[memory_limit] = .*/php_admin_value[memory_limit] = 32M/' \
        -i /usr/local/etc/php-fpm.d/www.conf
RUN sed -e 's/^error_log.*/error_log = \/dev\/stderr/' \
        -i /usr/local/etc/php-fpm.conf

## foxy compatibility issues with composer 2.1 (open)
## RUN composer self-update 2.0.14

#USER www-data
#COPY . /var/www/app
#RUN composer install --no-dev