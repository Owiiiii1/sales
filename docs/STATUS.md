# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Documentation bootstrap:** COMPLETED
* **Phase 1 — Adapt Admin Foundation:** COMPLETED
* **Next planned work:** Phase 2 remaining items — audio upload, storage, processing UI (see [ROADMAP.md](ROADMAP.md))

## Product vs running app

The live admin is now a **Sales Analyzer foundation**: Companies, Employees, Calls, Dashboard stats.

There is still **no** public Upload Call, transcription, or AI scoring.

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
| Database | MySQL 8.0.46 — DB `sales`, user `sales`@`localhost` |
| Test DB | MySQL `sales_testing` (phpunit only; not production data) |
| Admin kit | `owlsolutions/custom-admin-kit` **v0.5.0** |

Admin login: guest `GET /` → login. Protected product routes redirect guests to `/`.

## Current UI

Primary navigation:

* Dashboard
* Companies
* Employees
* Calls
* Settings
* Statistics / Logs

Settings tabs in the hub: General, Users, AI, App. Telegram remains available at `/settings?tab=telegram` but is **not** in the tab bar.

**Hidden from nav (legacy kit, still routed):** Customers, Orders, Services, Staff, Calendar.

Dashboard cards (live counts): Companies, Active Employees, Total Calls, Calls Completed, Calls Processing, Calls Failed, plus Recent Calls empty state.

## Database

Phase 1 tables: `companies`, `employees`, `calls`.

Legacy kit tables retained: `customers`, `services`, `staff`, `orders`, `order_staff`.

## Known issues (non-critical)

* `/owl-admin/health` JSON reports `"preset":"core"` while the installed preset is `admin`.
* Composer 2.7.1 PHP 8.5 deprecation notices.
* Vite optional `fontaine` warning.
* Weak install-stage admin credentials (`admin@admin.com`).
* PHPUnit uses MySQL `sales_testing` because the server has no SQLite PDO driver.
* Inertia `assertInertia()->component()` file finder defaults to `resources/js/pages` (lowercase); tests pass `shouldExist = false` and still assert the component name.

## What is explicitly not done

* no STT / LLM / embeddings
* no public Upload Call
* no audio storage pipeline
* no scorecards in DB
* no deletion of kit CRM modules

## Next planned work

Audio upload and call processing (Phase 2 remainder / Phase 3), still without choosing providers until DEC-006 / DEC-007 close.
