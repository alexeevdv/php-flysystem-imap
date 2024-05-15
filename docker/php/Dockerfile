FROM php:8.3-cli-alpine

ENV XDEBUG_MODE=coverage

WORKDIR /app

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.2.14/install-php-extensions /usr/local/bin/

COPY --from=composer:2.7.6 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions imap openssl xdebug
