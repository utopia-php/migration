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

FROM php:8.1.21 as tests

# Install Appwrite Toolkit
RUN set -ex \
    && mkdir -p /app/toolkit \
    && cd /app/toolkit \
    && curl -fsSL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh \
    && bash nodesource_setup.sh \
    && apt-get install nodejs git -y \
    && git clone https://github.com/Meldiron/appwrite-toolkit.git \
    && cd appwrite-toolkit \
    && git checkout feat-refactor-cli \
    && npm install \
    && npm link

# Postgres
RUN set -ex \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get remove -y libpq-dev

COPY ./src /app/src
COPY ./tests /app/src/tests
COPY --from=composer /usr/local/src/vendor /app/vendor
CMD tail -f /dev/null
