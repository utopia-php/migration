--
-- PostgreSQL database cluster dump
--

SET default_transaction_read_only = off;

SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;



--
-- Drop roles
--

DROP ROLE IF EXISTS nhost_admin;
DROP ROLE IF EXISTS nhost_auth_admin;
DROP ROLE IF EXISTS nhost_hasura;
DROP ROLE IF EXISTS nhost_storage_admin;
DROP ROLE IF EXISTS pgbouncer;


--
-- Roles
--

CREATE ROLE nhost_admin;
ALTER ROLE nhost_admin WITH SUPERUSER INHERIT CREATEROLE CREATEDB LOGIN REPLICATION BYPASSRLS;
CREATE ROLE nhost_auth_admin;
ALTER ROLE nhost_auth_admin WITH NOSUPERUSER NOINHERIT CREATEROLE NOCREATEDB LOGIN NOREPLICATION NOBYPASSRLS;
CREATE ROLE nhost_hasura;
ALTER ROLE nhost_hasura WITH NOSUPERUSER INHERIT NOCREATEROLE NOCREATEDB LOGIN NOREPLICATION NOBYPASSRLS;
CREATE ROLE nhost_storage_admin;
ALTER ROLE nhost_storage_admin WITH NOSUPERUSER NOINHERIT CREATEROLE NOCREATEDB LOGIN NOREPLICATION NOBYPASSRLS;
CREATE ROLE pgbouncer;
ALTER ROLE pgbouncer WITH NOSUPERUSER INHERIT NOCREATEROLE NOCREATEDB LOGIN NOREPLICATION NOBYPASSRLS;
--
-- User Configurations
--

--
-- User Config "nhost_auth_admin"
--

ALTER ROLE nhost_auth_admin SET search_path TO 'auth';

--
-- User Config "nhost_storage_admin"
--

ALTER ROLE nhost_storage_admin SET search_path TO 'storage';






--
-- PostgreSQL database cluster dump complete
--

