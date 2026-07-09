# Peonify 🌸 — PHP / MySQL / XAMPP version

The complete Peonify floral e-commerce system as a classic **PHP + MySQL +
HTML + vanilla JavaScript + CSS** project. No frameworks, no build step, no
command line — made to run on **Windows XAMPP** out of the box.

## Run it on Windows (3 steps)

1. Install [XAMPP](https://www.apachefriends.org/) and open the **XAMPP
   Control Panel**. Press **Start** next to **Apache** and **MySQL**.
2. Copy this whole `peonify-php` folder into `C:\xampp\htdocs\` and rename it
   to `peonify` (so you have `C:\xampp\htdocs\peonify\index.php`).
3. Open **http://localhost/peonify/** in your browser. Done.

On the first visit the app automatically creates the `peonify` database, all
11 tables, and the demo catalogue (10 products, categories, collections,
bouquet-builder options) — you never touch phpMyAdmin or run SQL.

## Accounts

- **Admin:** `admin@peonify.com` / `peonify-admin` (seeded on first run —
  change it in `config.php` *before* first run, or via Profile after).
  The admin dashboard is at **http://localhost/peonify/admin/**.
- **Customers:** register at `register.php`. Checkout requires an account.

## Payments (Paystack)

Demo mode is on by default: orders are marked **paid** instantly.
To test real payments, paste your Paystack **test secret key**
(`sk_test_…`) into `config.php` → `PAYSTACK_SECRET_KEY`. Checkout then
redirects to Paystack's sandbox page — pay with card
`4084 0840 8408 4081`, any future expiry, any CVV. The callback page
verifies the transaction server-side and marks the order paid.

## What's inside

| Area | Files |
|---|---|
| Storefront | `index.php`, `shop.php`, `product.php`, `builder.php`, `cart.php`, `checkout.php`, `payment_callback.php` |
| Accounts | `login.php`, `register.php`, `logout.php`, `account.php` (orders, notifications, support, profile) |
| Static pages | `about.php`, `contact.php`, `terms.php`, `privacy.php` |
| Admin | `admin/` — dashboard with charts, orders (deliver), products (CRUD + photo upload + discounts), catalog, feedback, support inbox, notifications, activity, profile |
| Core | `config.php`, `includes/` (database auto-migration + seed, auth, helpers, layout), `assets/` (CSS, JS, images), `uploads/` |

## How it works (same behaviour as the original)

- **Order flow:** paid at checkout → admin presses **Deliver** (or the
  customer presses **I received it**) → delivered. Every step is logged and
  both sides get notifications.
- **Reminders:** customers are notified 24 h and 5 h before their delivery
  window (checked automatically on page loads, at most every 10 minutes).
- **Security:** bcrypt password hashing, PHP sessions with HttpOnly cookies,
  CSRF tokens on every form, prepared statements everywhere, image-only
  validated uploads (max 5 MB), server-side price recomputation at checkout.
- **Cart:** stored in the browser (`localStorage`), so it survives login.

## Notes

- The product photos are openly licensed placeholders from Wikimedia Commons
  (see `assets/images/CREDITS.md`) — replace them with your own photography
  before launching.
- PHP 8.1+ recommended (any current XAMPP qualifies). No PHP extensions
  beyond XAMPP's defaults (pdo_mysql, curl, fileinfo) are needed.
