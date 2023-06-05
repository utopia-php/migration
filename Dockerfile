FROM postgres:alpine3.18 as supabase-db
COPY ./tests/resources/supabase/backup.tar /docker-entrypoint-initdb.d/backup.tar
COPY ./tests/resources/restore.sh /docker-entrypoint-initdb.d/restore.sh

FROM postgres:alpine3.18 as nhost-db
COPY ./tests/resources/nhost/backup.tar /docker-entrypoint-initdb.d/backup.tar
COPY ./tests/resources/restore.sh /docker-entrypoint-initdb.d/restore.sh

# Use my fork of mockoon while waiting for range headers to be merged
FROM node:14-alpine3.14 as mock-api
WORKDIR /app
RUN git clone https://github.com/PineappleIOnic/mockoon.git . 
RUN npm run bootstrap
RUN npm run build:libs
RUN npm run build:cli
RUN mv ./packages/cli/dist/run /usr/local/bin/mockoon

FROM php:8.0-fpm-alpine3.14 as tests