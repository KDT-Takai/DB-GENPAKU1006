FROM php:8.2-apache
COPY ./php.ini /usr/local/etc/php/
RUN apt-get update \
  && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libonig-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install pdo_mysql mysqli mbstring gd iconv

RUN apt-get update && apt-get install -y locales
RUN sed -i 's/# ja_JP.UTF-8 UTF-8/ja_JP.UTF-8 UTF-8/' /etc/locale.gen && locale-gen
ENV LANG ja_JP.UTF-8
ENV LC_ALL ja_JP.UTF-8
ENV TZ Asia/Tokyo

RUN apt-get install -y vim

RUN apt-get update && apt-get install -y default-mysql-client

COPY ./php_apache2.conf /etc/apache2/mods-enabled
RUN apache2ctl restart