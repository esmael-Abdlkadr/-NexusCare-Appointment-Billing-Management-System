#!/bin/bash
set -e

# Generate and persist runtime secrets so `docker compose up` works without host .env.
# If the file already exists, reuse the same values for stable sessions/JWT behavior.
SECRETS_DIR="/var/www/storage/framework"
SECRETS_FILE="${SECRETS_DIR}/.runtime-secrets.env"
mkdir -p "$SECRETS_DIR"

if [ -f "$SECRETS_FILE" ]; then
  # shellcheck disable=SC1090
  set -a
  . "$SECRETS_FILE"
  set +a
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGEME" ]; then
  APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

if [ -z "$JWT_SECRET" ] || [ "$JWT_SECRET" = "jwt-secret-change-me" ]; then
  JWT_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"
fi

cat > "$SECRETS_FILE" <<EOF
APP_KEY=${APP_KEY}
JWT_SECRET=${JWT_SECRET}
EOF
chmod 600 "$SECRETS_FILE"
export APP_KEY JWT_SECRET

# Keep runtime directories writable by php-fpm user.
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Write runtime .env file so artisan commands and tests work.
# APP_ENV defaults to local for predictable dev behavior.
# APP_DEBUG defaults to false for safer default deployment posture.
cat > /var/www/.env <<EOF
APP_NAME=NexusCare
APP_ENV=${APP_ENV:-local}
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
