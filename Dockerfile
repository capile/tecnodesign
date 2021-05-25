FROM php:fpm
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash -
RUN apt-get install -y nodejs libpng-dev zlib1g-dev libzip-dev libonig-dev libxml2-dev
RUN docker-php-ext-install mbstring zip gd simplexml dom fileinfo ctype pdo

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/www/app
WORKDIR /var/www/app

#USER www-data
#COPY . /var/www/app
#RUN composer install --no-dev