#!/bin/sh
set -e

echo "Granting app user access to the test database..."
php <<'PHP'
<?php
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$rootPassword = getenv('DB_ROOT_PASSWORD');

$pdo = new PDO('mysql:host=mysql;port=3306', 'root', $rootPassword);
$pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s_test`', $dbName));
$pdo->exec(sprintf("GRANT ALL PRIVILEGES ON `%s_test`.* TO '%s'@'%%'", $dbName, $dbUser));
$pdo->exec('FLUSH PRIVILEGES');
PHP

echo "Waiting for database and running migrations..."
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction

echo "Setting up test database..."
php bin/console doctrine:database:create --if-not-exists --no-interaction --env=test
php bin/console doctrine:migrations:migrate --no-interaction --env=test

echo "Starting php-fpm..."
exec "$@"
