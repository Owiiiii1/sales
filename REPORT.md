# Sales Analyzer — Phase 2.1 Public Analyzer Shell Report

## Baseline

* accepted baseline SHA (Phase 2 on `main`): `5b555cf6d69dcd9f9b2b6d31ff4efc039d333f73`
* HEAD before work: `5b555cf6d69dcd9f9b2b6d31ff4efc039d333f73`
* working tree before work: clean
* unpushed commits before work: none
* HEAD matched the last Technical Lead accepted Phase 2 state. No extra Git changes existed before this phase.

## Route Structure

| Method | Path | Who | Result |
|---|---|---|---|
| GET | `/` | guest | public Inertia `Public/Home` |
| GET | `/login` | guest | admin Inertia `Auth/Login` |
| POST | `/login` | guest | existing admin authentication |
| POST | `/logout` | auth | existing logout → `/login` |
| GET | `/dashboard` | guest | 302 `/login` |
| GET | `/companies`, `/employees`, `/calls`, `/settings` | guest | 302 `/login` |
| POST | `/analyze` | guest | public upload JSON (`201`) |
| GET | `/analysis/{public_token}` | guest | safe status + `report: null` |
| GET | `/analysis/{public_token}/status` | guest | safe status JSON |
| GET | `/calls/{id}/audio`, `/calls/{id}/download` | guest | 302 `/login` |
| admin CRUD | existing paths | auth | unchanged |

Guests are no longer sent to `/` for login. `bootstrap/app.php` uses `redirectGuestsTo('/login')` and `redirectUsersTo('/dashboard')`.

## Public UI

* Separate `PublicLayout`: brand `Sales Analyzer` left, unobtrusive `Admin` → `/login` right.
* `resources/js/Pages/Public/Home.jsx`: hero “Analyze your sales call”, drag-and-drop + file picker, no extra UI library.
* Upload states: `uploading`, `uploaded`, `processing`, `completed`, `failed`. Spinner during `uploading` / `processing`.
* After a successful save with no AI pipeline, the page shows the honest copy: `Call uploaded successfully. Analysis engine is not connected yet.`
* Polling starts only if status is `processing` (3s interval), stops on `completed` / `failed`, and is cleared on unmount.
* `AnalysisReport` already has the structured section shells. Empty/null values are not rendered as fake scores.

## Public Upload

* `POST /analyze` → `PublicAnalyzerController@store` + `PublicAnalyzeRequest`.
* Reuses Phase 2 `CallAudioStorage`, `AudioMetadataService`, `CallAudioRules`, and cleanup-on-DB-failure in `CallUploadService`.
* Public payload is forced server-side: `company_id` null, `employee_id` null, `uploaded_by` null, `source = public`, `status = uploaded`.
* Request body cannot inject `company_id`, `employee_id`, `uploaded_by`, or `source`.
* Audio path on the private disk: `public/{Y}/{m}/{uuid}.ext`.
* JSON `201` returns `public_token`, `status`, filename, `report: null`. It does not return id, `storage_path`, or `uploaded_by`.

## Public Token

* Column `calls.public_token` UUID, unique, indexed, generated in `Call::creating` if empty.
* Public routes match `[0-9a-fA-F-]{36}`. Sequential `/analysis/1` is 404.
* Admin routes still use numeric ids.

## Security

* CSRF via Laravel web middleware (meta token + cookie on the public fetch).
* Named throttles: `public-analyze` default 10/min/IP, `public-analysis-status` default 60/min/IP (`SALES_PUBLIC_UPLOAD_PER_MINUTE`, `SALES_PUBLIC_STATUS_PER_MINUTE`).
* Same MIME / extension / size validation as admin upload.
* No public audio stream or download.
* Failed analysis errors are replaced with a generic public message.
* CAPTCHA is not implemented; documented as a possible later control (DEC-020).
* `tests/bootstrap.php` forces `APP_ENV=testing` and `DB_DATABASE=sales_testing` before Laravel boots, because this host’s shell often has `APP_ENV=production`.

## Server Upload Limits

Measured on `sales.owlsolutions.net` after this phase:

| Layer | Effective value |
|---|---|
| Application (`SALES_AUDIO_MAX_MB`) | **200 MB** |
| PHP-FPM `upload_max_filesize` (`public/.user.ini`) | **200M** |
| PHP-FPM `post_max_size` (`public/.user.ini`) | **210M** |
| nginx `client_max_body_size` (`sales.owlsolutions.net` vhost only) | **64M** |
| Effective HTTP upload cap | **64M** |

`sudo nginx -t` / `sudo systemctl reload nginx` could not be run: passwordless sudo is not available, and no sudo password was written anywhere. Other vhosts were not edited. PHP-FPM pool globals were not changed.

## Database

* Pre-migration backup (outside git): `/home/deploy/backups/sales/sales-pre-phase21-20260909-144606.sql`
* Additive migration: `2026_09_09_150000_make_calls_company_nullable_and_add_public_token`
  * `calls.company_id` nullable, FK retained
  * `calls.public_token` UUID unique not null
* Existing admin rows (none at backup time besides schema) received generated tokens.
* **Incident:** a PHPUnit run without the testing bootstrap RefreshDatabase’d production `sales` while the shell had `APP_ENV=production`. Production was restored from the pre-phase21 dump, then the additive migration was re-applied. `users` restored to `admin@admin.com`. Isolation is now enforced by `tests/bootstrap.php`. After the final suite, production still has 1 user and 0 calls.

## Admin Changes

* Login moved back to `/login` (`routes/owl-admin-auth.php`). Root is no longer the login page.
* `AdminLayout` header and sidebar footer: `Back to Sales Analyzer` → `/`.
* Login screen also has `Back to Sales Analyzer`.
* Dashboard / Companies / Employees / Calls screens were not redesigned.

## Tests

Command: `php artisan test`

Result: **42 passed**, 0 failed, **247 assertions**.

Coverage added/updated:

* guest `/` → `Public/Home`; guest `/login` → `Auth/Login`
* protected admin routes redirect `/login`
* public upload creates Call with null company/employee/uploader, `source=public`, unique UUID token, `status=uploaded`
* invalid / oversized audio rejected
* cannot inject company/employee/uploader/source
* status payload is safe; invalid token 404; `/analysis/1` 404
* admin audio endpoints still require auth
* public upload rate limit returns 429 when the named limiter is 2/min
* Phase 1 + Phase 2 admin tests still pass

## Live Verification

Guest:

* `GET /` → 200, Inertia component `Public/Home`
* `GET /login` → 200, Inertia component `Auth/Login`
* `GET /dashboard` → 302 `https://sales.owlsolutions.net/login`
* `GET /companies|/employees|/calls|/settings` → 302 `/login`
* `GET /calls/1/audio` → 302 `/login`

Public upload (synthetic WAV `phase21-live.wav`):

* `POST /analyze` → 201
* `public_token` returned
* DB: `source=public`, `company_id` null, `employee_id` null, `uploaded_by` null, `status=uploaded`
* injecting `company_id=999` did not attach a company
* `GET /analysis/{token}/status` → `{status: uploaded, progress: null, error: null, report_available: false}`
* `GET /analysis/{token}` → `report: null`, no id / storage_path
* Test Call and audio were deleted after the check (`calls=0`, disk empty)

`npm run build` succeeded (Home chunk emitted). `php artisan optimize:clear` and `php artisan migrate --force` were run.

Browser MCP tools were not available in this session; live checks used HTTP + Inertia JSON + DB inspection.

## Documentation

Updated:

* `docs/PROJECT.md`
* `docs/PRODUCT.md`
* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/ROADMAP.md` — Phase 2.1 COMPLETED
* `docs/STATUS.md`
* `docs/DECISIONS.md` — DEC-017 … DEC-020
* `README.md`

## Changed Files

Command:

```
git diff --name-status 5b555cf6d69dcd9f9b2b6d31ff4efc039d333f73..HEAD
```

Result after the implementation commit (later REPORT-only commits only rewrite this file):

```
M	.env.example
M	README.md
M	REPORT.md
M	app/Http/Requests/StoreCallRequest.php
M	app/Models/Call.php
M	app/Providers/AppServiceProvider.php
M	app/Services/Calls/CallAudioStorage.php
M	app/Services/Calls/CallUploadService.php
A	app/Http/Controllers/PublicAnalyzerController.php
A	app/Http/Requests/PublicAnalyzeRequest.php
A	app/Support/CallAudioRules.php
M	bootstrap/app.php
M	config/sales-analyzer.php
A	database/migrations/2026_09_09_150000_make_calls_company_nullable_and_add_public_token.php
M	database/factories/CallFactory.php
M	docs/ARCHITECTURE.md
M	docs/DATA_MODEL.md
M	docs/DECISIONS.md
M	docs/PRODUCT.md
M	docs/PROJECT.md
M	docs/ROADMAP.md
M	docs/STATUS.md
M	phpunit.xml
A	resources/js/Components/Public/AnalysisReport.jsx
A	resources/js/Layouts/PublicLayout.jsx
M	resources/js/Layouts/AdminLayout.jsx
M	resources/js/Pages/Auth/Login.jsx
A	resources/js/Pages/Public/Home.jsx
M	routes/owl-admin-auth.php
A	routes/public.php
M	routes/web.php
M	tests/Feature/CallsTest.php
M	tests/Feature/CompaniesTest.php
M	tests/Feature/EmployeesTest.php
M	tests/Feature/NavigationRoutesTest.php
A	tests/Feature/PublicAnalyzerTest.php
A	tests/bootstrap.php
```

Nothing omitted. `.env` is not in the list. `public/build` is gitignored. The SQL backup and live audio files are not in the list.

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: *(recorded after commit)*
* push result: *(recorded after push)*

## Problems / Warnings

* nginx `client_max_body_size` remains **64M** on this vhost. PHP-FPM honors 200M/210M via `public/.user.ini`. Application limit is 200 MB. Large HTTP uploads will still fail at nginx until an operator with sudo raises only this vhost and reloads nginx (`nginx -t` then `systemctl reload nginx`, not restart).
* Production `sales` was briefly emptied by PHPUnit `RefreshDatabase` when `APP_ENV=production` was already in the environment. Restored from `/home/deploy/backups/sales/sales-pre-phase21-20260909-144606.sql`. `tests/bootstrap.php` now forces the testing database before Laravel boots.
* CAPTCHA not added.
* ffprobe/ffmpeg still not installed; duration often null.
* Vite optional `fontaine` warning (pre-existing).
* `/owl-admin/health` still reports preset `core` (pre-existing).
* www-data can still create private audio directories that deploy cannot list until an authenticated cleanup; `CallAudioStorage` now `chmod 0775`s parent dirs after store. No `777`.
* No fake AI report is shown.

## Final Status

`PHASE 2.1 PASSED`
