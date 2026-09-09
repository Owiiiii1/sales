# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Documentation bootstrap:** COMPLETED after this documentation stage
* **Next planned work:** Phase 1 — Adapt Admin Foundation (see [ROADMAP.md](ROADMAP.md))

## Product vs running app

Sales Analyzer as a **product** is specified in `docs/`.

The **running application** is still the stock Custom Admin Kit. There is no Upload Call flow, no transcription, no analysis reports.

## Infrastructure

| Item | Value |
|---|---|
| Server | `116.203.135.175` (shared host; this project isolated) |
| Path | `/var/www/sales` |
| Domain | `https://sales.owlsolutions.net` |
| GitHub | `https://github.com/Owiiiii1/sales.git` |
| Branch | `main` |
| OS | Ubuntu 24.04.4 LTS |
| PHP | 8.5.8 (FPM `unix:/run/php/php8.5-fpm.sock`) |
| Laravel | 13.31.0 |
| Composer | 2.7.1 |
| Node / npm | v24.14.0 / 11.9.0 |
| nginx | 1.24.0 — `/etc/nginx/sites-available/sales.owlsolutions.net` |
| SSL | Let’s Encrypt for `sales.owlsolutions.net` only (no `www`) |
| Database | MySQL 8.0.46 — DB `sales`, user `sales`@`localhost` |
| Admin kit | `owlsolutions/custom-admin-kit` **v0.5.0** |
| Nutgram | 4.50.0 (installed; Telegram **not** configured) |

Admin login: guest `GET /` → login. `GET /login` redirects to `/`. Authenticated `/` redirects to dashboard.

## Current UI

Standard admin kit screens:

* Dashboard
* Customers
* Orders
* Services
* Staff
* Calendar
* Settings (General, Users, AI, App, Telegram)
* Profile
* Statistics / Logs

Header includes AI status badge, Bot status badge, language switcher, avatar menu, “Powered by OwlSolutions”.

AI and Telegram are **not** configured (`AI: not connected`, `Bot: not connected`).

**These CRM modules are stock Custom Admin Kit modules. They do not reflect the final Sales Analyzer architecture.**

## Database (kit)

Ran migrations include Laravel users/cache/jobs plus kit:

* `ai_provider_settings`
* `telegram_bot_settings`
* `customers`, `services`, `staff`, `orders`, `order_staff`

No Sales Analyzer domain migrations.

## Known issues (non-critical)

From deploy:

* `/owl-admin/health` JSON reports `"preset":"core"` while the installed preset is `admin` (kit health payload). Smoke still passed.
* Composer 2.7.1 prints PHP 8.5 deprecation notices (`E_STRICT`, `curl_close`). Composer was not upgraded globally.
* Vite build warns that optional `fontaine` is missing for optimized font fallbacks. Build succeeds.
* Weak admin credentials were created on purpose for the install stage (`admin@admin.com`). Treat as a known security hygiene item for later.
* Database engine is MySQL, not MariaDB.

## What is explicitly not done

* no product navigation
* no Call / Company / Employee product modules
* no STT / LLM integration
* no public Upload Call
* no scorecards in DB

## Next planned work

**Adapt Admin Foundation** (Phase 1): inventory kit modules, decide reuse vs remove, adapt navigation, still without AI/transcription.
