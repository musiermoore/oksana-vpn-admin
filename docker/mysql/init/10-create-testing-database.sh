#!/bin/bash
set -e

mysql --user=root --password="${MYSQL_ROOT_PASSWORD}" <<-SQL
    CREATE DATABASE IF NOT EXISTS testing;
    GRANT ALL PRIVILEGES ON testing.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
SQL
