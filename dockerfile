FROM php:7.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    unzip

# Install GeoIP PHP extension.
RUN apt-get update \
    && apt-get install -y  libgeoip-dev wget \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install geoip-1.1.1 \
    && docker-php-ext-enable geoip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-configure gd --with-gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/
RUN docker-php-ext-install gd pdo pdo_mysql 

COPY php.ini $PHP_INI_DIR/php.ini

# Expose port 9000 and start php-fpm server
#EXPOSE 9000
#CMD ["php-fpm"]

#CMD php -s 0.0.0.0:8000 -t public