<?php
/**
 * Peonify — configuration.
 *
 * XAMPP on Windows works out of the box with these defaults
 * (MySQL user "root" with an empty password). The database and all
 * tables are created automatically on first visit.
 *
 * Environment variables (optional) override every value, which is how
 * the project is tested on Linux without touching this file.
 */

define('DB_HOST', getenv('PEONIFY_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('PEONIFY_DB_PORT') ?: '3306');
define('DB_NAME', getenv('PEONIFY_DB_NAME') ?: 'peonify');
define('DB_USER', getenv('PEONIFY_DB_USER') ?: 'root');
define('DB_PASS', getenv('PEONIFY_DB_PASS') !== false ? getenv('PEONIFY_DB_PASS') : '');

// Seeded administrator account (change the password after first login!)
define('ADMIN_EMAIL', getenv('PEONIFY_ADMIN_EMAIL') ?: 'admin@peonify.com');
define('ADMIN_PASSWORD', getenv('PEONIFY_ADMIN_PASSWORD') ?: 'peonify-admin');

// Paystack: leave empty to run in demo mode (orders are marked paid instantly).
// Paste your TEST secret key (sk_test_...) to charge through Paystack's sandbox.
// Test card: 4084 0840 8408 4081, any future expiry, any CVV.
define('PAYSTACK_SECRET_KEY', getenv('PEONIFY_PAYSTACK_KEY') ?: '');
// Optional currency override (NGN, KES, ZAR ...). Empty = your account default.
define('PAYSTACK_CURRENCY', getenv('PEONIFY_PAYSTACK_CURRENCY') ?: '');

date_default_timezone_set('Africa/Kigali');
