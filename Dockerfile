FROM supabase/postgres:15.1.0.96 as supabase-db
COPY ./tests/Migration/resources/supabase/1_globals.sql /docker-entrypoint-initdb.d/1_globals.sql
COPY ./tests/Migration/resources/supabase/2_main.sql /docker-entrypoint-initdb.d/2_main.sql
RUN rm -rf /docker-entrypoint-initdb.d/migrate.sh

FROM postgres:alpine3.18 as nhost-db
COPY ./tests/Migration/resources/nhost/1_globals.sql /docker-entrypoint-initdb.d/1_globals.sql
COPY ./tests/Migration/resources/nhost/2_main.sql /docker-entrypoint-initdb.d/2_main.sql

# Use my fork of mockoon while waiting for range headers to be merged
FROM node:20.4-alpine3.17 as mock-api
WORKDIR /app
RUN apk add --no-cache git
RUN git clone https://github.com/PineappleIOnic/mockoon.git
WORKDIR /app/mockoon
RUN git checkout origin/feat-implement-range
RUN apk add python3 make gcc g++
RUN npm run bootstrap
RUN npm run build:libs
RUN npm run build:cli
RUN cd packages/cli && npm install -g .
RUN adduser --shell /bin/sh --disabled-password --gecos "" mockoon
USER mockoon
CMD mockoon-cli start --data /mockoon/api.json --port 80 --disable-log-to-file && tail -f /dev/null

FROM composer:2.0 as composer
WORKDIR /usr/local/src/
COPY composer.json /usr/local/src/
RUN composer install --ignore-platform-reqs

FROM php:8.1.21-fpm-alpine3.18 as tests
RUN set -ex && apk --no-cache add postgresql-dev
RUN docker-php-ext-install pdo pdo_pgsql
COPY ./src /app/src
COPY ./tests /app/src/tests
COPY --from=composer /usr/local/src/vendor /app/vendor
CMD tail -f /dev/null