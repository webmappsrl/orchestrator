FROM php:8.1-fpm

USER root

ADD .bashrc /var/www/.bashrc

ADD www.conf /usr/local/etc/php-fpm.d/www.conf
ADD zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
ADD php.ini /usr/local/etc/php/php.ini

ENV TZ 'Europe/Rome'

RUN chown -R www-data:www-data /var/www \
    && chown  www-data:www-data /usr/local/etc/php-fpm.d/www.conf \
    && chown -R www-data:www-data /usr/local/etc/php-fpm.d/zz-docker.conf \
    && apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
        zlib1g-dev \
        libicu-dev \
        g++ \
        imagemagick \
        libmagickwand-dev \
        libmagickcore-dev \
        git \
	    unzip \
        vim \
        libzip-dev \
	    libwebp-dev \
        libpq-dev 
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
	gd \
	intl \
	zip \
   	pdo \
    pdo_mysql \
    opcache \
    pgsql \
    pdo_pgsql \
    exif \
    # && pecl install xdebug \
    && pecl install imagick \
    # && docker-php-ext-enable xdebug \
    && pecl install apcu \
    #&& pecl install mcrypt-1.0.2 \
    #&& docker-php-ext-enable mcrypt \
    && docker-php-ext-enable apcu \
    && docker-php-ext-enable exif \
    && docker-php-ext-enable imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && ln -s /usr/local/bin/composer /usr/bin/composer


ENV HOME /var/www
WORKDIR /var/www

USER www-data
