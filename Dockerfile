FROM supabase/postgres:15.1.0.96 as supabase-db
COPY tests/Migration/resources/supabase/1_globals.sql /docker-entrypoint-initdb.d/1_globals.sql
COPY tests/Migration/resources/supabase/2_main.sql /docker-entrypoint-initdb.d/2_main.sql
RUN rm -rf /docker-entrypoint-initdb.d/migrate.sh

FROM postgres:alpine3.18 as nhost-db
COPY tests/Migration/resources/nhost/1_globals.sql /docker-entrypoint-initdb.d/1_globals.sql
COPY tests/Migration/resources/nhost/2_main.sql /docker-entrypoint-initdb.d/2_main.sql

FROM composer:2.0 as composer
WORKDIR /usr/local/src/
COPY composer.json /usr/local/src/
RUN composer install --ignore-platform-reqs

FROM php:8.1.21-fpm-alpine3.18 as tests
# Postgres
RUN set -ex \
    && apk --no-cache add postgresql-libs postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apk del postgresql-dev
COPY ./src /app/src
COPY ./tests /app/src/tests
COPY --from=composer /usr/local/src/vendor /app/vendor
CMD tail -f /dev/null
