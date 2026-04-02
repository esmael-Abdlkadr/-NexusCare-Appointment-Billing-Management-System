#!/bin/bash
set -e
echo "============================================"
echo " NexusCare — Full Test Suite"
echo "============================================"
echo ""
echo "=== 1. Backend Unit + Feature Tests ==="
BACKEND_APP_KEY=$(docker compose exec -T backend bash -c "grep APP_KEY /var/www/.env | cut -d= -f2-" 2>/dev/null || echo "")
BACKEND_JWT_SECRET=$(docker compose exec -T backend bash -c "grep JWT_SECRET /var/www/.env | cut -d= -f2-" 2>/dev/null || echo "")
docker compose exec -T -e APP_KEY="$BACKEND_APP_KEY" -e JWT_SECRET="$BACKEND_JWT_SECRET" backend php artisan test --stop-on-failure
echo "Backend tests: PASSED"
echo ""
echo "=== 2. E2E Tests (Playwright) ==="
cd e2e
npm test -- --reporter=list --retries=1
cd ..
echo "E2E tests: PASSED"
echo ""
echo "============================================"
echo " All tests passed!"
echo "============================================"
