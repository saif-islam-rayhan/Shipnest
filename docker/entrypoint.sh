#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

PORT="${PORT:-10000}"
sed -i "s/listen [0-9]*;/listen ${PORT};/" /etc/nginx/sites-available/default

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/public \
  bootstrap/cache

rm -f public/storage
php artisan storage:link --force >/dev/null 2>&1 || php artisan storage:link

# Wait for MySQL when DB_HOST is set (skip for pure artisan worker cold-start race)
if [[ -n "${DB_HOST:-}" ]]; then
  echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
  for i in $(seq 1 60); do
    if php -r "
      try {
        new PDO(
          'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . getenv('DB_DATABASE'),
          getenv('DB_USERNAME'),
          getenv('DB_PASSWORD') ?: ''
        );
        exit(0);
      } catch (Throwable \$e) {
        exit(1);
      }
    "; then
      echo "Database is ready."
      break
    fi
    if [[ "$i" -eq 60 ]]; then
      echo "Database not reachable after 60s; continuing anyway."
    fi
    sleep 1
  done
fi

# Only cache/migrate when we have an app key (normal Render runtime)
if [[ -n "${APP_KEY:-}" ]]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache

  # Workers should not race migrate; web service owns schema updates
  if [[ "${RUN_MIGRATIONS:-true}" == "true" ]]; then
    php artisan migrate --force
  fi
fi

chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
