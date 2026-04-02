#!/bin/bash
set -e

# Generate APP_KEY if not already set or is the default placeholder
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGEME" ]; then
  export APP_KEY=$(php artisan key:generate --show --no-interaction 2>/dev/null | tail -1)
fi

# Generate JWT_SECRET if not set
if [ -z "$JWT_SECRET" ]; then
  export JWT_SECRET=$(php artisan jwt:secret --show --no-interaction 2>/dev/null | tail -1 || openssl rand -base64 32)
fi

# Write them into Laravel's .env so artisan commands work
php artisan config:clear

# Run migrations automatically — idempotent, safe on every container restart
echo "[entrypoint] Running database migrations..."
php artisan migrate --force

php artisan cache:clear

# Seed reference/demo data only on a fresh database (no users seeded yet)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 || echo "0")
if [ "$USER_COUNT" = "0" ]; then
  echo "[entrypoint] Seeding database..."
  php artisan db:seed --class=AdminUserSeeder --force
  php artisan db:seed --class=IterationThreeTestSeeder --force
  php artisan db:seed --class=IterationFourTestSeeder --force
  php artisan db:seed --class=IterationFiveTestSeeder --force
  php artisan db:seed --class=DemoSeeder --force
  echo "[entrypoint] Seeding complete."
else
  echo "[entrypoint] Database already seeded (${USER_COUNT} users found), skipping seeders."
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
