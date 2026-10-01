#!/bin/sh
set -e

# Local docker-compose only: DB_ROOT_PASSWORD is passed by compose
# and is NOT set in ECS, so these blocks are skipped there.
LOCAL_SETUP=""
if [ -n "$DB_ROOT_PASSWORD" ]; then
  LOCAL_SETUP=1
fi

if [ -n "$LOCAL_SETUP" ]; then
  echo "Granting app user access to the test database..."
  php <<'PHP'
<?php
$dbHost = getenv('DB_HOST') ?: 'mysql';
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$rootPassword = getenv('DB_ROOT_PASSWORD');

$pdo = new PDO(sprintf('mysql:host=%s;port=3306', $dbHost), 'root', $rootPassword);
$pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s_test`', $dbName));
$pdo->exec(sprintf("GRANT ALL PRIVILEGES ON `%s_test`.* TO '%s'@'%%'", $dbName, $dbUser));
$pdo->exec('FLUSH PRIVILEGES');
PHP
fi

echo "Waiting for database and running migrations..."
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction

if [ -n "$LOCAL_SETUP" ]; then
  echo "Setting up test database..."
  php bin/console doctrine:database:create --if-not-exists --no-interaction --env=test
  php bin/console doctrine:migrations:migrate --no-interaction --env=test
fi

echo "Starting: $*"
exec "$@"
