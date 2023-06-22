FROM postgres:alpine3.18 as supabase-db
COPY ./tests/Transfer/resources/supabase/backup.tar /docker-entrypoint-initdb.d/backup.tar
COPY ./tests/Transfer/resources/restore.sh /docker-entrypoint-initdb.d/restore.sh

FROM postgres:alpine3.18 as nhost-db
COPY ./tests/Transfer/resources/nhost/backup.tar /docker-entrypoint-initdb.d/backup.tar
COPY ./tests/Transfer/resources/restore.sh /docker-entrypoint-initdb.d/restore.sh

# Use my fork of mockoon while waiting for range headers to be merged
FROM node:14-alpine3.14 as mock-api
WORKDIR /app
RUN git clone https://github.com/PineappleIOnic/mockoon.git . 
RUN npm run bootstrap
RUN npm run build:libs
RUN npm run build:cli
RUN mv ./packages/cli/dist/run /usr/local/bin/mockoon

FROM composer:2.0 as composer

WORKDIR /usr/local/src/

COPY composer.lock /usr/local/src/
COPY composer.json /usr/local/src/

RUN composer install --ignore-platform-reqs

FROM php:8.0-fpm-alpine3.14 as tests
RUN set -ex && apk --no-cache add postgresql-dev
RUN docker-php-ext-install pdo pdo_pgsql
COPY ./src /usr/local/src
COPY ./tests /usr/local/src/tests
COPY --from=composer /usr/local/src/vendor /usr/local/src/vendor
CMD php ./vendor/bin/phpunit