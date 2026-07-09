#!/usr/bin/env bash
# Test Peonify-PHP on Linux without installing or changing anything.
# Uses PHP's built-in dev server + the peonify-mysql Docker container.
# (config.php keeps its XAMPP defaults for Windows — these env vars only
#  apply to this test run.)
cd "$(dirname "$0")"

# Make sure the MySQL container is up
docker start peonify-mysql >/dev/null 2>&1

# Read the Paystack test key from the Node backend's .env (if present)
PAYSTACK_KEY=$(grep -oP '^PAYSTACK_SECRET_KEY=\K.*' ../backend/.env 2>/dev/null)

echo "Peonify PHP  →  http://localhost:8080"
echo "Admin        →  http://localhost:8080/admin/  (admin@peonify.com / peonify-admin)"
[ -n "$PAYSTACK_KEY" ] && echo "Paystack     →  TEST MODE ACTIVE (key loaded from backend/.env)" \
                       || echo "Paystack     →  demo mode (no key found in backend/.env)"
echo "Stop with Ctrl+C. Nothing is installed; delete the folder to remove all traces."
echo

PEONIFY_DB_HOST=127.0.0.1 \
PEONIFY_DB_PORT=3306 \
PEONIFY_DB_USER=root \
PEONIFY_DB_PASS=root \
PEONIFY_PAYSTACK_KEY="$PAYSTACK_KEY" \
php -S localhost:8080
