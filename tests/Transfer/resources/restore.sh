#!/bin/bash
set -e
pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" -F t /docker-entrypoint-initdb.d/backup.tar