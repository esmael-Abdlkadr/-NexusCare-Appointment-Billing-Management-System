#!/bin/bash
set -e

# Get script directory to support execution from any location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Parse flags
ALLOW_SKIP_E2E=false
for arg in "$@"; do
    if [ "$arg" = "--allow-skip-e2e" ]; then
        ALLOW_SKIP_E2E=true
    fi
done

echo "============================================"
echo " NexusCare — Full Test Suite"
if [ "$ALLOW_SKIP_E2E" = "true" ]; then
    echo " Mode: non-strict (E2E skip permitted)"
fi
echo "============================================"
echo ""

echo "=== 0. Ensuring Docker stack is running ==="
docker compose up -d
echo "Waiting for backend to be ready..."
MAX_WAIT=120
WAITED=0
until docker compose exec -T backend php artisan --version > /dev/null 2>&1; do
    if [ "$WAITED" -ge "$MAX_WAIT" ]; then
        echo "ERROR: Backend did not become ready within ${MAX_WAIT}s"
        exit 1
    fi
    sleep 3
    WAITED=$((WAITED + 3))
done
# Extra wait for migrations/seeding to finish after artisan is available
sleep 10
echo "Docker stack is ready."
echo ""

echo "=== 1. Backend Unit + Feature Tests ==="
docker compose exec -T -u www-data backend php artisan test --stop-on-failure
echo "Backend tests: PASSED"
echo ""

# Ensure runtime app user can always write logs/cache before E2E.
docker compose exec -T backend sh -lc "chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache"

echo "=== 2. Frontend Unit Tests ==="
if command -v npm &> /dev/null; then
    cd "$SCRIPT_DIR/frontend"
    if [ ! -d "node_modules" ]; then
        echo "Frontend dependencies not found; installing with npm install..."
        npm install
    fi
    npm test
    cd "$SCRIPT_DIR"
    echo "Frontend unit tests: PASSED"
else
    echo "Frontend unit tests: SKIPPED (npm unavailable)"
fi
echo ""

echo "=== 3. E2E Tests (Playwright) ==="
E2E_RAN=false
if [ -d "$SCRIPT_DIR/e2e" ] && command -v npm &> /dev/null; then
    cd "$SCRIPT_DIR/e2e"

    # One-command test mode: provide deterministic defaults that match seeded demo users.
    export E2E_ADMIN_USER="${E2E_ADMIN_USER:-admin}"
    export E2E_ADMIN_PASS="${E2E_ADMIN_PASS:-Admin@NexusCare1}"
    export E2E_STAFF_USER="${E2E_STAFF_USER:-staff1}"
    export E2E_STAFF_PASS="${E2E_STAFF_PASS:-Staff@NexusCare1}"
    export E2E_REVIEWER_USER="${E2E_REVIEWER_USER:-reviewer1}"
    export E2E_REVIEWER_PASS="${E2E_REVIEWER_PASS:-Reviewer@NexusCare1}"
    export E2E_BANNED_USER="${E2E_BANNED_USER:-banned_user}"
    export E2E_BANNED_PASS="${E2E_BANNED_PASS:-Banned@NexusCare1}"
    export E2E_MUTED_USER="${E2E_MUTED_USER:-muted_user}"
    export E2E_MUTED_PASS="${E2E_MUTED_PASS:-Muted@NexusCare1}"
    export E2E_TEMP_PASS="${E2E_TEMP_PASS:-Temp@NexusCare123}"

    # Keep one-command behavior: bootstrap E2E deps when missing.
    if [ ! -d "node_modules" ]; then
        echo "E2E dependencies not found; installing with npm ci..."
        npm ci
    fi

    # Ensure Playwright browsers are available for local runners.
    if [ ! -d "$HOME/.cache/ms-playwright" ] && [ ! -d "$HOME/Library/Caches/ms-playwright" ]; then
        echo "Playwright browsers not found; installing..."
        npx playwright install chromium
    fi

    npm test -- --reporter=list --retries=1
    cd "$SCRIPT_DIR"
    echo "E2E tests: PASSED"
    E2E_RAN=true
else
    echo "E2E tests: SKIPPED (npm unavailable or e2e/ folder missing)"
    echo "To run E2E tests manually: cd e2e && npm ci && npx playwright install chromium && npm test"
    if [ "$ALLOW_SKIP_E2E" = "false" ]; then
        echo ""
        echo "============================================"
        echo " PARTIAL RUN — E2E suite was skipped."
        echo " Re-run with --allow-skip-e2e to suppress"
        echo " this failure, or install E2E dependencies."
        echo "============================================"
        exit 1
    else
        echo "WARNING: --allow-skip-e2e set — skipped E2E is not a failure in this run."
    fi
fi
echo ""

echo "============================================"
if [ "$E2E_RAN" = "true" ]; then
    echo " All tests passed! (backend + frontend + e2e)"
else
    echo " Partial run complete (backend + frontend only)."
    echo " E2E was skipped with --allow-skip-e2e."
fi
echo "============================================"
