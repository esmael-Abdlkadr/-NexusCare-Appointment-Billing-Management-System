#!/bin/bash
set -e

# Generate APP_KEY if not already set or is the default placeholder.
# Use a PHP fallback so key generation never silently results in empty value.
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGEME" ]; then
  GENERATED_APP_KEY="$(php artisan key:generate --show --no-interaction 2>/dev/null || true)"
  GENERATED_APP_KEY="$(echo "$GENERATED_APP_KEY" | tr -d '\r' | sed -n '$p')"
  if [ -z "$GENERATED_APP_KEY" ]; then
    GENERATED_APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
  fi
  export APP_KEY="$GENERATED_APP_KEY"
fi

# Generate JWT_SECRET if not set.
if [ -z "$JWT_SECRET" ]; then
  GENERATED_JWT_SECRET="$(php artisan jwt:secret --show --no-interaction 2>/dev/null || true)"
  GENERATED_JWT_SECRET="$(echo "$GENERATED_JWT_SECRET" | tr -d '\r' | sed -n '$p')"
  if [ -z "$GENERATED_JWT_SECRET" ]; then
    GENERATED_JWT_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
  fi
  export JWT_SECRET="$GENERATED_JWT_SECRET"
fi

# Write runtime .env file so artisan commands and tests work
cat > /var/www/.env <<EOF
APP_NAME=NexusCare
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost:8000}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-nexuscare}
DB_USERNAME=${DB_USERNAME:-nexuscare}
DB_PASSWORD=${DB_PASSWORD:-nexuscare_secret}

JWT_SECRET=${JWT_SECRET}
JWT_TTL=${JWT_TTL:-720}
JWT_ALGO=HS256

FRONTEND_URL=${FRONTEND_URL:-http://localhost}
EOF

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
