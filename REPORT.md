# Sales Analyzer — Phase 1 Adapt Admin Foundation Report

## Baseline

* baseline accepted commit: `65d74ba52c980cada46f685b9698358f0d7c7c66`
* HEAD before work: `65d74ba52c980cada46f685b9698358f0d7c7c66`
* working tree before work: clean
* unpushed commits before work: none
* HEAD matched the last Technical Lead accepted state. No extra Git changes existed before this phase.

## Implemented

Adapted the stock OwlSolutions Custom Admin Kit into a Sales Analyzer admin foundation. No LLM, STT, transcription, embeddings, vector DB, audio processing, or public upload.

* Primary navigation: Dashboard, Companies, Employees, Calls, Settings, Statistics/Logs
* Hidden from primary nav (code and routes kept): Customers, Orders, Services, Staff, Calendar, Telegram
* Domain tables and Eloquent models: `Company`, `Employee`, `Call`
* Admin CRUD for companies, employees, and calls (manual records only; no file field)
* Company/employee activate-deactivate via `toggle`
* Delete safety: company blocked if employees or calls exist; employee blocked if calls exist; FKs `restrictOnDelete`
* Call employee must belong to the selected company (backend `CallRequest` + UI filter)
* Dashboard cards from live DB counts; Recent Calls (last 10) with empty state
* Settings hub tabs: General, Users, AI, App. Telegram still renders at `/settings?tab=telegram`
* Factories and `SalesAnalyzerDemoSeeder` (not run on production)
* Feature tests on MySQL `sales_testing`
* phpunit forced to MySQL `sales_testing` because this server has no SQLite PDO driver

## Database

### Backup

Performed before Phase 1 migrations.

* path (outside Git): `/home/deploy/backups/sales/sales-pre-phase1-20260909-140123.sql`
* tool: `mysqldump --no-tablespaces` of database `sales`
* not committed

### Migrations added

* `database/migrations/2026_09_09_120000_create_companies_table.php`
* `database/migrations/2026_09_09_120100_create_employees_table.php`
* `database/migrations/2026_09_09_120200_create_calls_table.php`

Batch **3**. Kit CRM migrations were not rolled back, dropped, or refreshed.

`php artisan migrate --force` after implementation: **Nothing to migrate.**

No `migrate:fresh`, reset, truncate, or drop of existing kit tables.

### Tables

`companies`

* id, name, legal_name, website, industry, description, country, city, phone, email, is_active (default true), timestamps
* no slug

`employees`

* id, company_id, first_name, last_name, email, phone, position, external_id, is_active (default true), timestamps

`calls`

* id, company_id, employee_id nullable, source default `manual`, external_id, original_filename, storage_path, mime_type, file_size, duration_seconds, status string default `pending`, recorded_at, uploaded_by, processing_started_at, processing_completed_at, error_message, timestamps
* status values in application code: `pending`, `uploaded`, `processing`, `completed`, `failed` (not a DB enum)

Production row counts at report time: companies 0, employees 0, calls 0.

A company named `HTTP Check Co` was created during live HTTP POST verification and then deleted. Production is empty again.

### Foreign keys

* `employees.company_id` → `companies.id` ON DELETE RESTRICT
* `calls.company_id` → `companies.id` ON DELETE RESTRICT
* `calls.employee_id` → `employees.id` ON DELETE RESTRICT
* `calls.uploaded_by` → `users.id` ON DELETE SET NULL

### Indexes

`companies`

* primary `id`
* `companies_is_active_index` (`is_active`)

`employees`

* primary `id`
* `employees_company_id_foreign` (`company_id`)
* `employees_is_active_index` (`is_active`)
* `employees_external_id_index` (`external_id`)

`calls`

* primary `id`
* `calls_company_id_foreign` (`company_id`)
* `calls_employee_id_foreign` (`employee_id`)
* `calls_uploaded_by_foreign` (`uploaded_by`)
* `calls_status_index` (`status`)
* `calls_recorded_at_index` (`recorded_at`)
* `calls_source_index` (`source`)
* `calls_external_id_index` (`external_id`)

No extra composite indexes.

## Routes

Added in `routes/owl-admin-pages.php` (auth middleware group already wrapping kit pages):

| Method | Path | Name |
|---|---|---|
| GET | `/dashboard` | `dashboard` (now `DashboardController`) |
| GET | `/companies` | `companies.index` |
| POST | `/companies` | `companies.store` |
| PATCH | `/companies/{company}` | `companies.update` |
| PATCH | `/companies/{company}/toggle` | `companies.toggle` |
| DELETE | `/companies/{company}` | `companies.destroy` |
| GET | `/employees` | `employees.index` |
| POST | `/employees` | `employees.store` |
| PATCH | `/employees/{employee}` | `employees.update` |
| PATCH | `/employees/{employee}/toggle` | `employees.toggle` |
| DELETE | `/employees/{employee}` | `employees.destroy` |
| GET | `/calls` | `calls.index` |
| POST | `/calls` | `calls.store` |
| PATCH | `/calls/{call}` | `calls.update` |
| DELETE | `/calls/{call}` | `calls.destroy` |

Employees list accepts optional `?company_id=` filter.

Legacy kit routes (`/customers`, `/orders`, `/services`, `/staff`, calendar, settings telegram) remain registered.

## UI

* `resources/js/Layouts/AdminLayout.jsx` — `primaryNavItems` is Dashboard / Companies / Employees / Calls. Statistics/Logs and Settings remain. English labels for the new product items.
* `resources/js/Pages/Dashboard.jsx` — six real-count cards + Recent Calls empty state. No charts.
* `resources/js/Pages/Companies/Index.jsx` — kit-style list, create, edit modal, toggle, delete
* `resources/js/Pages/Employees/Index.jsx` — list with company name, company filter, create/edit, toggle, delete
* `resources/js/Pages/Calls/Index.jsx` — list columns ID, company, employee, source, filename, status, duration, recorded_at, created_at; manual create/edit form without file upload; employee dropdown filtered by company
* `resources/js/Pages/Settings/Index.jsx` — Telegram removed from tab bar; panel still mounts when `tab=telegram`

Header still shows the kit Telegram **status badge**. That is not a sidebar/nav item.

## Legacy modules

Physically retained (DEC-012):

* tables: `customers`, `services`, `staff`, `orders`, `order_staff`
* kit models/controllers/Inertia pages/routes
* Calendar route
* Telegram settings code and `?tab=telegram`

Hidden from primary navigation and Settings tabs.

New domain code does not use kit CRM models.

## Tests

Command:

```
php artisan test
```

Result: **22 passed**, **0 failed**, 99 assertions, ~2.0s.

Environment: PHPUnit `APP_ENV=testing`, `DB_CONNECTION=mysql` `force=true`, `DB_DATABASE=sales_testing` `force=true`. Credentials still come from `.env`; password is not in `phpunit.xml`. `TestCase` calls `withoutVite()`. Inertia `component(..., false)` because kit pages live in `resources/js/Pages` (capital P).

Important cases:

* guest redirected from `/dashboard`, `/companies`, `/employees`, `/calls`, `/settings`
* authenticated 200 for those product routes
* company create / update / name+website+email validation
* employee create with company, company required, update, list
* call create, company required, employee from another company rejected, invalid status and negative duration rejected
* legacy `/customers` and `/orders` still 200 when authenticated

`SalesAnalyzerDemoSeeder` was **not** run on production `sales`.

## Build

* `npm run build` — succeeded (Vite 8.2.2, 2723 modules). Optional `fontaine` warning remains.
* `public/build` is gitignored; the production document root uses the local build output.
* `php artisan optimize:clear` — succeeded
* `php artisan migrate --force` — nothing pending
* nginx / SSL / PHP-FPM not changed

## HTTP verification

Live host `https://sales.owlsolutions.net`.

Guest (no follow):

| URL | Status | Location |
|---|---|---|
| `/companies` | 302 | `https://sales.owlsolutions.net/` |
| `/employees` | 302 | `https://sales.owlsolutions.net/` |
| `/calls` | 302 | `https://sales.owlsolutions.net/` |
| `/dashboard` | 302 | `https://sales.owlsolutions.net` |
| `/settings` | 302 | `https://sales.owlsolutions.net` |

Authenticated (admin session, HTML GET, Inertia `data-page` JSON):

| URL | Status | Component |
|---|---|---|
| `/dashboard` | 200 | `Dashboard` — stats all 0, recentCalls 0 |
| `/companies` | 200 | `Companies/Index` |
| `/employees` | 200 | `Employees/Index` |
| `/calls` | 200 | `Calls/Index` statuses pending/uploaded/processing/completed/failed |
| `/settings` | 200 | `Settings/Index` tab=general |
| `/settings?tab=telegram` | 200 | `Settings/Index` tab=telegram |

Built `AdminLayout-*.js` contains `companies.index`, `employees.index`, `calls.index`. It does **not** contain `customers.index`, `orders.index`, `services.index`, `staff.index`, or calendar nav routes.

No dedicated browser-driver session was available. UI checks used live HTTP + Inertia page JSON + source/built navigation. Feature tests cover create/update/validation.

## Documentation

Updated:

* `docs/DATA_MODEL.md` — implemented `companies` / `employees` / `calls`
* `docs/ARCHITECTURE.md` — admin foundation now present; still no AI pipeline
* `docs/ROADMAP.md` — Phase 1 COMPLETED; Phase 2 remaining = upload/storage
* `docs/STATUS.md` — current nav, tables, known issues
* `docs/DECISIONS.md` — DEC-010, DEC-011, DEC-012 Accepted
* `docs/PROJECT.md` — current vs intended
* `docs/PRODUCT.md` — live admin is no longer “stock kit only”
* `README.md` — current status line

New decisions:

* **DEC-010** Separate Company and Employee domain — Accepted
* **DEC-011** Calls status stored as string — Accepted
* **DEC-012** Legacy Admin Kit CRM tables retained temporarily — Accepted

## Changed files

Command:

```
git diff --name-status 65d74ba52c980cada46f685b9698358f0d7c7c66..HEAD
```

Result after the implementation commit `8316abc0c91d22623e6a09dc8b47d8f8094473d8` (REPORT.md later commits only rewrite this file):

```
M	README.md
M	REPORT.md
A	app/Http/Controllers/CallsController.php
A	app/Http/Controllers/CompaniesController.php
A	app/Http/Controllers/DashboardController.php
A	app/Http/Controllers/EmployeesController.php
A	app/Http/Requests/CallRequest.php
A	app/Http/Requests/CompanyRequest.php
A	app/Http/Requests/EmployeeRequest.php
A	app/Models/Call.php
A	app/Models/Company.php
A	app/Models/Employee.php
M	app/Models/User.php
A	database/factories/CallFactory.php
A	database/factories/CompanyFactory.php
A	database/factories/EmployeeFactory.php
A	database/migrations/2026_09_09_120000_create_companies_table.php
A	database/migrations/2026_09_09_120100_create_employees_table.php
A	database/migrations/2026_09_09_120200_create_calls_table.php
A	database/seeders/SalesAnalyzerDemoSeeder.php
M	docs/ARCHITECTURE.md
M	docs/DATA_MODEL.md
M	docs/DECISIONS.md
M	docs/PRODUCT.md
M	docs/PROJECT.md
M	docs/ROADMAP.md
M	docs/STATUS.md
M	phpunit.xml
M	resources/js/Layouts/AdminLayout.jsx
A	resources/js/Pages/Calls/Index.jsx
A	resources/js/Pages/Companies/Index.jsx
M	resources/js/Pages/Dashboard.jsx
A	resources/js/Pages/Employees/Index.jsx
M	resources/js/Pages/Settings/Index.jsx
M	routes/owl-admin-pages.php
A	tests/Feature/CallsTest.php
A	tests/Feature/CompaniesTest.php
A	tests/Feature/EmployeesTest.php
A	tests/Feature/NavigationRoutesTest.php
M	tests/TestCase.php
```

Nothing omitted. `public/build` is gitignored and is not in this list. `.env` is not in this list. The SQL backup is not in this list.

## Security

* no secrets added to Git
* `.env` not committed
* DB password not in `phpunit.xml`
* SQL backup not committed (outside repo under `/home/deploy/backups/sales/`)
* no AI provider keys connected

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `8316abc0c91d22623e6a09dc8b47d8f8094473d8`
* REPORT SHA/files commit: `ea5dc741ceb0c932caa8f0e9853a3fbb2bbda67d`
* first successful push: `65d74ba..ea5dc74  main -> main`
* commit messages:
  * `Adapt the admin foundation for companies, employees, and calls.`
  * `Record Phase 1 commit SHA and changed files in REPORT.md.`
  * `Record Phase 1 GitHub push result in REPORT.md.`
* push result: **PASS** — `To https://github.com/Owiiiii1/sales.git` `65d74ba..ea5dc74  main -> main`

A further REPORT-only commit may sit on top of `ea5dc74` to record this push paragraph. Application code is in `8316abc`.

Changed-files listing vs baseline is unchanged by REPORT-only commits.

## Problems / Warnings

* Optional Vite `fontaine` package warning during `npm run build` (unchanged kit warning).
* `/owl-admin/health` still reports `"preset":"core"` while the installed preset is `admin` (pre-existing).
* Composer 2.7.1 PHP 8.5 deprecation notices remain in the environment (pre-existing).
* PHPUnit cannot use SQLite here (no PDO driver). Tests use MySQL database `sales_testing`.
* Inertia test component file-existence check is disabled (`false`) because pages are under `resources/js/Pages`.
* Kit CRM routes still exist and still appear in Ziggy if generated; they are not in `primaryNavItems`.
* Header Telegram connection badge remains. Telegram is not in sidebar or Settings tabs.
* No headless browser; sidebar labels were verified from `AdminLayout.jsx` and the built AdminLayout chunk, not by clicking a rendered menu.
* One accidental production company (`HTTP Check Co`) was created during HTTP verification and deleted before finish.

## Final status

`PHASE 1 PASSED`
