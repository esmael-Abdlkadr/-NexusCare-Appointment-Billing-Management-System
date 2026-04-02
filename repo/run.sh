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
echo "Demo credentials:"
echo "  Admin    : admin / Admin@NexusCare1"
echo "  Staff    : staff1 / Staff@NexusCare1"
echo "  Reviewer : reviewer1 / Reviewer@NexusCare1"
