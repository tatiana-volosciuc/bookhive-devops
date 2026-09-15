#!/bin/sh
set -e

echo "Waiting for database and running migrations..."
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting php-fpm..."
exec "$@"
