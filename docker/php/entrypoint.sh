#!/bin/sh
set -e

# Local docker-compose vs ECS detection.
# ECS Fargate injects ECS_CONTAINER_METADATA_URI_V4 into every container;
# docker-compose never sets it. (DB_PASSWORD can't be used for this check:
# ECS injects it too, via `secrets`.)
LOCAL_SETUP=""
if [ -z "$ECS_CONTAINER_METADATA_URI_V4" ]; then
  LOCAL_SETUP=1
fi

if [ -n "$LOCAL_SETUP" ]; then
  echo "Granting app user access to the test database..."
  php <<'PHP'
<?php
$dbHost = getenv('DB_HOST') ?: 'mysql';
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$rootPassword = getenv('DB_PASSWORD');

$pdo = new PDO(sprintf('mysql:host=%s;port=3306', $dbHost), 'root', $rootPassword);
$pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s_test`', $dbName));
$pdo->exec(sprintf("GRANT ALL PRIVILEGES ON `%s_test`.* TO '%s'@'%%'", $dbName, $dbUser));
$pdo->exec('FLUSH PRIVILEGES');
PHP
fi

echo "Waiting for database and running migrations..."

# ECS: the task gets DB_HOST/DB_USER/DB_NAME as plain env vars and DB_PASSWORD
# from Secrets Manager, but Doctrine reads DATABASE_URL. Build it here.
# The password is URL-encoded because RDS passwords can contain special characters.
# Locally (docker-compose) DATABASE_URL is provided by compose and left alone.
if [ -z "$LOCAL_SETUP" ] && [ -n "$DB_HOST" ]; then
  DB_PASSWORD_ENCODED=$(php -r 'echo rawurlencode((string) getenv("DB_PASSWORD"));')
  export DATABASE_URL="mysql://${DB_USER}:${DB_PASSWORD_ENCODED}@${DB_HOST}:3306/${DB_NAME}?serverVersion=8.0&charset=utf8mb4"
fi

php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction

if [ -n "$LOCAL_SETUP" ]; then
  echo "Setting up test database..."
  php bin/console doctrine:database:create --if-not-exists --no-interaction --env=test
  php bin/console doctrine:migrations:migrate --no-interaction --env=test
fi

echo "Starting: $*"
exec "$@"
