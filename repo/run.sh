#!/usr/bin/env bash
set -e

# NexusCare — Appointment & Billing Management System
# One-command startup — works from any directory

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Starting NexusCare..."
docker compose -f "$SCRIPT_DIR/docker-compose.yml" up -d --build

echo ""
echo "Waiting for services to be ready..."
sleep 10

echo ""
echo "Services:"
echo "  Frontend : http://localhost:80"
echo "  Backend  : http://localhost:8000/api"
echo "  Health   : http://localhost:8000/api/health"
echo ""
echo "Demo users seeded by the database seeder:"
echo "  Admin    : admin"
echo "  Staff    : staff1, staff2, staff3"
echo "  Reviewer : reviewer1, reviewer2"
echo ""
echo "Retrieve passwords from the seeder source or run:"
echo "  docker compose exec backend php artisan tinker --execute=\"echo App\Models\User::where('identifier','admin')->value('identifier');\""
echo "  (passwords are set by the seeder — see backend/database/seeders/)"
