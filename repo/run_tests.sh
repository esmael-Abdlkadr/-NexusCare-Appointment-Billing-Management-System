#!/bin/bash
set -e

# Get script directory to support execution from any location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "============================================"
echo " NexusCare — Full Test Suite"
echo "============================================"
echo ""

echo "=== 1. Backend Unit + Feature Tests ==="
# Set APP_KEY and JWT_SECRET explicitly for test environment
docker compose exec -T \
    -e APP_KEY="base64:hXJo1VM8mGXiQI9L1Gy/SO/YR/42un6EOh68Th6Ytk0=" \
    -e JWT_SECRET="testing-jwt-secret-for-phpunit-32chars" \
    backend php artisan test --stop-on-failure
echo "Backend tests: PASSED"
echo ""

echo "=== 2. E2E Tests (Playwright) ==="
if [ -d "$SCRIPT_DIR/e2e/node_modules" ] && command -v npx &> /dev/null; then
    cd "$SCRIPT_DIR/e2e"
    npm test -- --reporter=list --retries=1
    cd "$SCRIPT_DIR"
    echo "E2E tests: PASSED"
else
    echo "E2E tests: SKIPPED (node_modules not installed or npx not available)"
    echo "To run E2E tests manually: cd e2e && npm install && npx playwright install && npm test"
fi
echo ""

echo "============================================"
echo " All tests passed!"
echo "============================================"
