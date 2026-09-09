## Sales Analyzer — Admin Kit Deployment Report

### Environment

* OS: Ubuntu 24.04.4 LTS (Noble Numbat)
* PHP: 8.5.8 (CLI + PHP-FPM socket `/run/php/php8.5-fpm.sock`)
* Composer: 2.7.1
* Laravel: 13.31.0 (skeleton `laravel/laravel` 13.x)
* Node: v24.14.0
* npm: 11.9.0
* nginx: 1.24.0 (Ubuntu)
* MariaDB/MySQL: MySQL 8.0.46-0ubuntu0.24.04.4 (MariaDB is not installed; existing server uses MySQL)

Global PHP / Node / Composer / MySQL packages were not upgraded.

### Project

* path: `/var/www/sales`
* branch: `main`
* repository: `https://github.com/Owiiiii1/sales.git`

Laravel was created directly in `/var/www/sales` (no nested `/var/www/sales/sales`). Existing empty git remote `origin` was preserved.

### Database

* database name: `sales`
* database user: `sales`@`localhost`
* migrations status: all ran

| Migration | Batch | Status |
|---|---|---|
| `0001_01_01_000000_create_users_table` | 1 | Ran |
| `0001_01_01_000001_create_cache_table` | 1 | Ran |
| `0001_01_01_000002_create_jobs_table` | 1 | Ran |
| `2026_07_04_170000_create_ai_provider_settings_table` | 2 | Ran |
| `2026_07_05_100000_create_customers_table` | 2 | Ran |
| `2026_07_05_100100_create_services_table` | 2 | Ran |
| `2026_07_05_100200_create_staff_table` | 2 | Ran |
| `2026_07_05_100300_create_orders_table` | 2 | Ran |
| `2026_07_05_100400_create_order_staff_table` | 2 | Ran |
| `2026_07_21_150000_create_telegram_bot_settings_table` | 2 | Ran |

Verified tables present: `ai_provider_settings`, `telegram_bot_settings`, `customers`, `services`, `staff`, `orders`, `order_staff`.

DB password is stored only in `/var/www/sales/.env` (gitignored).

### Admin Kit

* installed version: `owlsolutions/custom-admin-kit` **v0.5.0** (Composer VCS: `https://github.com/Owiiiii1/custom-admin-kit.git`)
* Nutgram version: `nutgram/nutgram` **4.50.0**
* doctor result: **PASS** (no FAIL). Pre-install warnings were expected missing stubs/npm packages / optional seed env / optional Telegram token. Post-install doctor reports 71 publish-map “conflict” warnings because files already exist after install; this is expected and not a failure.
* install result: **PASS** — `php artisan owl-admin:install --preset=admin --backup --migrate --no-smoke` published 71/71 stubs and ran kit migrations.
* frontend setup result: **PASS** — `php artisan owl-admin:frontend-setup --preset=admin --backup --install-npm --run-build` merged host files, installed npm packages, Vite build succeeded.
* smoke result: **PASS** — `php artisan owl-admin:smoke --preset=admin` (core + frontend-setup checks all green).

Host merges required for a working Inertia admin (not Sales Analyzer domain work):

* registered `HandleInertiaRequests` in `bootstrap/app.php`
* shared `auth`, `locale`, and `owlAdmin.ai` / `owlAdmin.telegram` (kit snippet used `config('owl-admin.branding', default)`, which skipped AI/Telegram closures because branding already exists)

### Domain

* nginx config path: `/etc/nginx/sites-available/sales.owlsolutions.net` (enabled via `/etc/nginx/sites-enabled/sales.owlsolutions.net`)
* DNS result: `sales.owlsolutions.net` → `116.203.135.175` (A). `www` was not requested and was not added.
* SSL result: Let's Encrypt certificate issued for `sales.owlsolutions.net` only. Expires 2026-12-08. Path: `/etc/letsencrypt/live/sales.owlsolutions.net/`.
* HTTPS result: **PASS**. HTTP `/login` returns 301 to HTTPS. `sudo nginx -t` succeeded before reload.

Document root: `/var/www/sales/public`. PHP-FPM: `unix:/run/php/php8.5-fpm.sock` (same socket as `jarvis.owlsolutions.net`).

### Routes

Guest (no session):

| Route | Status |
|---|---|
| `https://sales.owlsolutions.net/` | 200 (`Auth/Login`) |
| `https://sales.owlsolutions.net/login` | 302 → `/` |
| `https://sales.owlsolutions.net/owl-admin/health` | 200 (`{"status":"ok","kit":"0.5.0","preset":"core"}`) |
| `https://sales.owlsolutions.net/dashboard` | 302 → `/` |
| `https://sales.owlsolutions.net/customers` | 302 → `/` |
| `https://sales.owlsolutions.net/orders` | 302 → `/` |
| `https://sales.owlsolutions.net/services` | 302 → `/` |
| `https://sales.owlsolutions.net/staff` | 302 → `/` |
| `https://sales.owlsolutions.net/calendar` | 302 → `/` |
| `https://sales.owlsolutions.net/settings` | 302 → `/` |
| `https://sales.owlsolutions.net/settings?tab=general` | 302 → `/` |
| `https://sales.owlsolutions.net/settings?tab=users` | 302 → `/` |
| `https://sales.owlsolutions.net/settings?tab=ai` | 302 → `/` |
| `https://sales.owlsolutions.net/settings?tab=app` | 302 → `/` |
| `https://sales.owlsolutions.net/settings?tab=telegram` | 302 → `/` |
| `https://sales.owlsolutions.net/profile` | 302 → `/` |
| `https://sales.owlsolutions.net/statistics/logs` | 302 → `/` |

Authenticated (admin session):

| Route | Status | Inertia component |
|---|---|---|
| `/` POST | 200 (lands on `/dashboard`) | `Dashboard` |
| `/` GET (authenticated) | 200 (redirects to `/dashboard`) | `Dashboard` |
| `/dashboard` | 200 | `Dashboard` |
| `/customers` | 200 | `Customers/Index` |
| `/orders` | 200 | `Orders/Index` |
| `/services` | 200 | `Services/Index` |
| `/staff` | 200 | `Staff/Index` |
| `/calendar` | 200 | `Calendar/Index` |
| `/settings` | 200 | `Settings/Index` tab=`general` |
| `/settings?tab=general` | 200 | `Settings/Index` tab=`general` |
| `/settings?tab=users` | 200 | `Settings/Index` tab=`users` |
| `/settings?tab=ai` | 200 | `Settings/Index` tab=`ai` |
| `/settings?tab=app` | 200 | `Settings/Index` tab=`app` |
| `/settings?tab=telegram` | 200 | `Settings/Index` tab=`telegram` |
| `/profile` | 200 | `Profile/Edit` |
| `/statistics/logs` | 200 | `Statistics/Logs` |
| `/logout` POST | 200 (lands on `/`) | `Auth/Login` |
| `/dashboard` after logout | 200 (redirected to `/`) | guest |

Vite manifest: `https://sales.owlsolutions.net/build/manifest.json` → 200.

### Authentication

* created admin: `admin@admin.com` (id 1) via `php artisan owl-admin:make-admin`
* login result: **PASS**
* logout result: **PASS**

Password not recorded.

Authenticated Inertia shared props include `auth.user`, `locale=en`, `owlAdmin.ai.status_label="AI: not connected"`, `owlAdmin.telegram.status_label="Bot: not connected"`. `AdminLayout.jsx` contains AI badge, Bot badge, language switcher, avatar menu, and “Powered by OwlSolutions”. AI and Telegram were not configured.

Cursor browser MCP tools were not invocable in this session (namespace present, empty tool list). UI checks were done via HTTPS session + Inertia page JSON + layout source.

### Frontend

* npm install result: **PASS** (`owl-admin:frontend-setup --install-npm`; 224 packages added, 0 vulnerabilities)
* build result: **PASS** (`vite build` via frontend-setup and a second `npm run build`; ~0.8s)
* Vite manifest result: `public/build/manifest.json` exists and is served over HTTPS 200

Warning during build: optional `fontaine` package missing for optimized font fallbacks (non-blocking).

### Isolation / Server Safety

Files/dirs created or changed **outside** `/var/www/sales`:

* created nginx config: `/etc/nginx/sites-available/sales.owlsolutions.net`
* created symlink: `/etc/nginx/sites-enabled/sales.owlsolutions.net`
* Let's Encrypt files for this domain only (`/etc/letsencrypt/live/sales.owlsolutions.net/`, archive, renewal conf)
* nginx logs: `/var/log/nginx/sales.owlsolutions.net.access.log`, `/var/log/nginx/sales.owlsolutions.net.error.log`

Database created:

* database `sales`
* user `sales`@`localhost` with privileges **only** on `sales.*`

System services:

* `sudo nginx -t` then `sudo systemctl reload nginx` (after creating the HTTP vhost)
* Certbot nginx installer deployed the certificate (reload, not restart)
* nginx / MariaDB / PHP-FPM were **not** stopped or restarted
* PHP, Node, Composer, MySQL were **not** globally upgraded

Confirmations:

* other nginx configs were **not** changed (md5 of all pre-existing `sites-available` / `sites-enabled` files unchanged; only the new `sales.owlsolutions.net` files appeared)
* other databases were **not** changed (`jarvis`, `ofs`, `shift_happens`, `trueman`, `vpn`, `wow_cleaning` untouched; only `sales` was created)
* other projects in `/var/www` were **not** changed (mtimes of `jarvis`, `ofs`, `shift-happens`, `trueman`, `vpn`, `wow-cleaning`, `html` unchanged)

### Git

* branch: `main`
* commit SHA: `128ae0fe67e3807363ab6a0ded4d884a470dba1d`
* commit message: Deploy Laravel 13 with Custom Admin Kit v0.5.0.
* push result: **PASS** — `git push -u origin main` created remote `main` at `https://github.com/Owiiiii1/sales.git`

### Security

* `.env` is gitignored and was not added
* DB password, APP_KEY, SSH/deploy password, GitHub token are not in the repository
* `.env.example` has empty `DB_PASSWORD` / `APP_KEY`
* no `auth.json` in the project
* `vendor/`, `node_modules/`, `public/build/` are gitignored

### Problems

* Composer 2.7.1 emits PHP 8.5 deprecation notices (`E_STRICT`, `curl_close`). Composer was not upgraded globally.
* Vite warns that optional `fontaine` is missing for optimized font fallbacks. Build still succeeds.
* `/owl-admin/health` returns `"preset":"core"` even though the installed preset is `admin`. Kit health payload; smoke still passed.
* `owl-admin:make-admin` warned about weak explicit credentials (`admin@admin.com` / `admin`) as required by this stage.
* Doctor after install reports 71 stub conflict warnings because files already exist. Expected; not FAILs.
* Kit frontend-setup merge of `HandleInertiaRequests` did not attach AI/Telegram badges or `auth`/`locale` until the host share() merge was corrected. Required for standard header UI.
* Database engine on this server is MySQL 8.0, not MariaDB. Connection works.
* Cursor browser MCP tools were unavailable; authenticated UI was verified via HTTPS + Inertia JSON rather than a headed browser.
* Laravel welcome page was initially served at `/`. Root now serves the admin login; `/login` redirects to `/`. Authenticated `/` goes to dashboard.

### Follow-up: admin at site root

* `routes/web.php` no longer renders `welcome`.
* `routes/owl-admin-auth.php`: `GET/POST /` is login (`route('login')`); `GET /login` redirects to `/`.
* Verified: guest `/` → 200 `Auth/Login`; guest `/login` and `/dashboard` → 302 `/`; login POST `/` → dashboard; authenticated `/` → dashboard.

### Final Status

`DEPLOYMENT PASSED`
