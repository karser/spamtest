ARG PHP_VERSION=8.1

FROM php:${PHP_VERSION}-cli-alpine AS app_php_base

COPY ./docker/cron-entrypoint.sh /usr/local/bin/cron-entrypoint
RUN chmod +x /usr/local/bin/cron-entrypoint

# persistent / runtime deps
RUN apk add --no-cache \
        nano \
    ;

ARG APCU_VERSION=5.1.18
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/bin/
RUN chmod +x /usr/bin/install-php-extensions
RUN install-php-extensions \
    apcu \
    opcache

COPY --from=composer /usr/bin/composer /usr/bin/composer
# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /var/app

# "php prod" stage
FROM app_php_base AS app_php

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.* ./
RUN set -eux; \
    composer install --prefer-dist --no-dev --no-scripts --no-progress --no-suggest; \
    composer clear-cache

COPY bin bin/
COPY src src/
COPY templates templates/


RUN set -eux; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    chmod +x bin/console; sync
