#!/usr/bin/env bash
set -eo pipefail

entrypoint="$(cd "$(dirname "$0")/.." && pwd)/docker/php/entrypoint.sh"

php() {
  if [ "$1" = "-r" ]; then
    printf 'encoded-password'
  else
    printf 'PHP %s URL=%s\n' "$*" "${DATABASE_URL:-unset}"
  fi
}

unset ECS_CONTAINER_METADATA_URI_V4
production=$(
  APP_ENV=prod DB_HOST=rds.internal DB_NAME=bookhive DB_USER=admin DB_PASSWORD=example \
    source "$entrypoint" /bin/true
)
[[ "$production" == *"URL=mysql://admin:encoded-password@rds.internal:3306/bookhive"* ]]
[[ "$production" != *"Granting app user"* ]]

development=$(
  APP_ENV=dev DB_HOST=mysql DB_NAME=bookhive DB_USER=bookhive DB_PASSWORD=example \
    source "$entrypoint" /bin/true
)
[[ "$development" == *"Granting app user"* ]]
[[ "$development" == *"Setting up test database"* ]]

echo "Entrypoint production and development checks passed."
