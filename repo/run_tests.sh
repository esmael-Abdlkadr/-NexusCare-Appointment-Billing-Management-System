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
docker compose exec -T backend php artisan test --stop-on-failure
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
