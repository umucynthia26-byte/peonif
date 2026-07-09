# Peonify 🌸

> **Windows XAMPP version:** a complete PHP + MySQL + vanilla JS re-implementation lives in [`peonify-php/`](peonify-php/README-XAMPP.md) — copy it into `C:\xampp\htdocs\` and it runs with zero setup.

A premium floral e-commerce platform built for a flower entrepreneur — no
technical skills needed to run it. Curated storefront with real photography,
delivery scheduling, a bouquet builder with live preview, customer accounts
with shipment notifications, product feedback, and a sidebar admin dashboard
with revenue charts.

**Stack:** React + Vite + TypeScript + Tailwind CSS v4 + shadcn/ui + Recharts ·
Node.js + Express + TypeScript · PostgreSQL · bcrypt + JWT (httpOnly cookies)

## Accounts & roles

- **Customers** sign up at `/signup`. Their account (`/account`) shows paginated
  orders with delivery status, notifications with per-item and mark-all-read,
  and a profile (photo, password, phone, saved delivery location that pre-fills
  checkout). Guest checkout also works. Admins are redirected to their own
  workspace and cannot use the customer dashboard.
- **Admin** signs in at `/login` (default `admin@peonify.com` /
  `peonify-admin` — change them in `backend/.env`!). The `/admin` workspace is
  fully standalone: its own top bar (Storefront link, notification bell with
  mark-all-read, avatar menu with logout) and a sidebar with Dashboard (revenue
  chart, paid-vs-delivered chart, top sellers), Orders (search, status filter,
  pagination), Products (search + pagination), Catalog, Feedback, Inbox,
  Activity, and a plain-English Help section.
- **The order flow is one button:** every order is *paid* at checkout; when the
  flowers reach the customer, the admin presses **Deliver**. The customer is
  notified automatically. The admin is notified of new orders, new contact
  messages, and new product feedback.
- Customers can leave star ratings + feedback on any product page.
- Site pages: About Us, Contact Us (messages land in the admin inbox), Terms &
  Conditions, Privacy Policy, cookie consent banner — and the landing page
  explains the whole journey in a "How Peonify Works" section.
- Sessions are JWTs (7-day expiry) in httpOnly cookies; passwords are
  bcrypt-hashed in the `users` table with a `role` column
  (`customer` | `admin`).

## Setup on Windows (no Docker needed)

### 1. Install prerequisites

- [Node.js LTS](https://nodejs.org) (v20+)
- [PostgreSQL](https://www.postgresql.org/download/windows/) — remember the
  password you set for the `postgres` user during install

### 2. Create the database

Open **SQL Shell (psql)** from the Start menu (or pgAdmin) and run:

```sql
CREATE DATABASE peonify;
```

Tables are created and seeded automatically the first time the API starts.

### 3. Configure the backend

Copy `backend\.env.example` to `backend\.env` and set your Postgres password:

```
DATABASE_URL=postgres://postgres:YOUR_PASSWORD@localhost:5432/peonify
PORT=5000
STRIPE_SECRET_KEY=
```

### 4. Install and run

From the project root:

```
npm install
npm run setup
npm run dev
```

- Storefront: http://localhost:5173
- API: http://localhost:5000/api/health

`npm run dev` starts both the API and the web app in one terminal.

## How orders work (kept simple)

1. The customer pays at checkout → the order is **paid** and the admin gets a
   notification with the delivery address, date, and window.
2. The admin prepares the flowers, delivers them, and presses **Deliver** —
   the order becomes **delivered** and the customer is notified.
3. Every step is stored as a timestamped `order_events` row; the admin
   Activity section is the audit trail.

## Payments — Paystack (easy test mode)

Payments run in **mock mode** so everything works without keys. The gateway is
`backend/src/payments/gateway.ts` and uses **Paystack**, which is very easy to
test:

1. Create a free account at https://paystack.com and copy the **test secret
   key** (`sk_test_…`) from Settings → API Keys & Webhooks
2. Set `PAYSTACK_SECRET_KEY` in `backend/.env` and restart the API
3. Orders now initialize real sandbox transactions
   (`POST /transaction/initialize` → `authorization_url`), verified with
   `GET /transaction/verify/:reference`
4. Pay with the test card `4084 0840 8408 4081` (any future expiry, any CVV) —
   no real money moves in test mode

## Project structure

```
backend/
  src/index.ts            Express app + startup
  src/db.ts               Pool, schema, seed data
  src/auth.ts             bcrypt + JWT auth, role middleware
  src/routes/             auth, me (profile/orders/notifications), products
                          (+ reviews), builder, orders, admin, meta (contact)
  src/payments/stripe.ts  Stripe integration point (mock mode)
frontend/
  src/pages/              Home, Shop, ProductDetail (+feedback), Builder,
                          Cart, Checkout, Account, auth/, admin/, static/
  src/components/         Navbar, Footer, StatusTimeline, CookieConsent, ui/
  src/context/            CartContext, AuthContext
  src/lib/                api client, types, utils
```

## Production build

```
npm run build     # compiles backend to backend/dist and frontend to frontend/dist
npm run start     # runs the compiled API
```

Serve `frontend/dist` with any static host (or IIS on Windows) and point it
at the API. During development the Vite dev server proxies `/api` to
`localhost:5000` automatically.
