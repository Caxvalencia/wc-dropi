#!/bin/sh
set -eu

SITE_URL="${WP_HOME:-http://localhost:8080}"
SITE_TITLE="${WP_SITE_TITLE:-Dropify Local}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-admin123!}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

docker compose up -d db wordpress adminer

printf '%s\n' 'Waiting for database and WordPress volumes...'
until docker compose run --rm wpcli db check --path=/var/www/html --allow-root >/dev/null 2>&1; do
  sleep 3
done

if ! docker compose run --rm wpcli core is-installed --path=/var/www/html --allow-root >/dev/null 2>&1; then
  docker compose run --rm wpcli core install \
    --path=/var/www/html \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

if ! docker compose run --rm wpcli plugin is-installed woocommerce --path=/var/www/html --allow-root >/dev/null 2>&1; then
  docker compose run --rm wpcli plugin install woocommerce --activate --path=/var/www/html --allow-root
else
  docker compose run --rm wpcli plugin activate woocommerce --path=/var/www/html --allow-root || true
fi

docker compose run --rm wpcli plugin activate wc-dropi-integration --path=/var/www/html --allow-root
docker compose run --rm wpcli option update permalink_structure '/%postname%/' --path=/var/www/html --allow-root
docker compose run --rm wpcli rewrite flush --hard --path=/var/www/html --allow-root

printf 'WordPress ready at %s\n' "$SITE_URL"
printf 'Admin user: %s\n' "$ADMIN_USER"
