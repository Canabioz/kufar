FROM php:7-fpm

RUN apt-get update && \
    apt-get install -y  git \
            wget \
            zip \
            openjdk-7-jre \
            libpq-dev \
            libicu-dev \
            npm \
            libfreetype6-dev \
            libmcrypt-dev \
            libmemcached-dev \
            libjpeg62-turbo-dev \
            libpng12-dev

### composer
RUN cd / && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-install \
    intl \
    exif \
    zip \
    opcache \
    mbstring \
    -j$(nproc) iconv mcrypt \
    pdo \
    pdo_mysql

# Install Imagick.
RUN apt-get update && apt-get install -y \
    libmagickwand-dev
RUN pecl install imagick
RUN docker-php-ext-enable imagick

# Download and install wkhtmltopdf
RUN apt-get update && apt-get install -y build-essential gdebi libssl-dev libxrender-dev xvfb
RUN wget http://download.gna.org/wkhtmltopdf/0.12/0.12.2.1/wkhtmltox-0.12.2.1_linux-jessie-amd64.deb
RUN gdebi --n wkhtmltox-0.12.2.1_linux-jessie-amd64.deb

# Download and install PHPUnit
RUN wget https://phar.phpunit.de/phpunit.phar
RUN chmod +x phpunit.phar && mv phpunit.phar /usr/local/bin/phpunit

# Install xdebug
RUN pecl install xdebug

### copy php config files
COPY ./config/ /usr/local/etc/

RUN ln -s /usr/bin/nodejs /usr/bin/node

WORKDIR /var/www