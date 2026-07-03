#!/bin/sh
set -eu

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:create-super-admin "$SUPER_ADMIN_EMAIL" "$SUPER_ADMIN_PASSWORD" "$SUPER_ADMIN_NAME" --no-interaction

exec php -S 0.0.0.0:8000 -t public public/dev-router.php
