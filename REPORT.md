# Sales Analyzer — Phase 2.2 Test Isolation & Upload Limits Report

## Baseline

* accepted baseline SHA (Phase 2.1 on `main`): `779f3a7769a20b905d00db4cefc6e4e388977147`
* HEAD before work: `779f3a7769a20b905d00db4cefc6e4e388977147`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none
* HEAD matched the last Technical Lead accepted Phase 2.1 state.

## Test DB Isolation

* database name: `sales_testing`
* testing DB user: `sales_testing`@`localhost`
* production DB user `sales`@`localhost`: **unchanged**

Grants summary (`SHOW GRANTS`):

* `sales_testing`@`localhost`: `USAGE` on `*.*`; `ALL PRIVILEGES` on `sales_testing.*` only
* `sales`@`localhost` (unchanged): `USAGE` on `*.*`; `ALL PRIVILEGES` on `sales.*`; `ALL PRIVILEGES` on `sales_testing.*`

Confirmation: PDO as `sales_testing` opens `sales_testing` and is **denied** opening production `sales`. Feature test `test_testing_user_can_use_sales_testing_but_not_production_sales` covers this using runtime config (password is not in the test source).

Credentials live only in gitignored `.env.testing` (mode `600`). Repository has `.env.testing.example` with an empty password.

## Guards

Implementation:

* `App\Support\TestingDatabaseGuard` — throws `RuntimeException`, not a warning
* `tests/bootstrap.php` — loads `.env.testing`, forces `APP_ENV=testing`, `DB_DATABASE=sales_testing`, `DB_USERNAME=sales_testing`, then runs the guard (missing `.env.testing` exits 1)
* `tests/TestCase::setUp` — guard from environment **before** `parent::setUp()`, then from Laravel config after boot
* `AppServiceProvider::boot` — guard when `runningUnitTests()` so RefreshDatabase cannot start on production

If `DB_DATABASE=sales`:

```
Refusing to run tests: DB_DATABASE is the production database "sales".
```

Also rejects `APP_ENV !== testing` and `DB_USERNAME=sales`.

Unit tests in `tests/Unit/TestingDatabaseGuardTest.php` assert those cases without touching MySQL.

## Production Sentinel

Recorded with production connection `database=sales` immediately before and after `php artisan test`.

| Metric | Before | After |
|---|---|---|
| users | 1 | 1 |
| companies | 0 | 0 |
| employees | 0 | 0 |
| calls | 0 | 0 |
| admin | id `1`, `admin@admin.com` | id `1`, `admin@admin.com` |

No production writes during the suite.

## Upload Limits

| Layer | Effective value |
|---|---|
| Laravel `SALES_AUDIO_MAX_MB` / `config('sales-analyzer.max_audio_size_mb')` | **200 MB** |
| PHP-FPM `upload_max_filesize` (`public/.user.ini`) | **200M** |
| PHP-FPM `post_max_size` (`public/.user.ini`) | **210M** |
| nginx `client_max_body_size` (`sales.owlsolutions.net` only) | **210M** |
| Effective product max | **200 MB** (app validation; HTTP layers allow 210M) |

Live probes:

* small WAV `POST /analyze` → `201`
* synthetic **70 MiB** multipart `POST /analyze` → Laravel **422** (invalid audio), **not** nginx `413` (would have been the old 64M cap)
* both leftovers removed (`calls=0`, private disk empty)

## Nginx Changes

* vhost file: `/etc/nginx/sites-available/sales.owlsolutions.net`
* backup: `/home/deploy/backups/sales/sales.owlsolutions.net.nginx-pre-phase22-20260909-151547` (outside Git)
* diff vs backup: only `client_max_body_size 64M;` → `client_max_body_size 210M;`
* `sudo nginx -t`: syntax ok, test successful
* `sudo systemctl reload nginx` (not restart): success
* MD5 of every other file in `/etc/nginx/sites-available/` unchanged (`jarvis`, `wow-cleaning`, `shifthappens`, etc.)
* global `nginx.conf` not edited

## Tests

Command: `php artisan test`

* tests: **48**
* passed: **48**
* failed: **0**
* assertions: **262**

Includes existing Phase 1 / 2 / 2.1 tests plus:

* guard unit tests (production DB name, non-testing env, production username)
* isolation feature tests (config points at `sales_testing` / user `sales_testing`; PDO denied on `sales`)

## Security

Not in Git:

* sudo password
* MySQL testing password
* production DB password
* `.env.testing` (gitignored; confirmed `git check-ignore`)
* DB dumps / nginx backup (under `/home/deploy/backups/sales`)

`git status` before commit showed `.env.testing` as ignored. Project tree has no sudo password string.

## Documentation

Updated:

* `docs/ARCHITECTURE.md` — testing isolation; upload limits closed
* `docs/STATUS.md` — Phase 2.2 COMPLETED; effective 200 MB
* `docs/DECISIONS.md` — DEC-021, DEC-022, DEC-023
* `docs/WORKFLOW.md` — tests vs production database
* `docs/ROADMAP.md` — Phase 2.2 COMPLETED

No public/admin UI product files changed.

## Changed Files

Command:

```
git diff --name-status 779f3a7769a20b905d00db4cefc6e4e388977147..HEAD
```

Result after implementation commit `c4a7ea707f7e523f8decdd17e75424328266d631` (later REPORT-only commits only rewrite this file):

```
A	.env.testing.example
M	.gitignore
M	REPORT.md
M	app/Providers/AppServiceProvider.php
A	app/Support/TestingDatabaseGuard.php
M	docs/ARCHITECTURE.md
M	docs/DECISIONS.md
M	docs/ROADMAP.md
M	docs/STATUS.md
M	docs/WORKFLOW.md
M	phpunit.xml
M	public/.user.ini
A	tests/Feature/TestingDatabaseIsolationTest.php
M	tests/TestCase.php
A	tests/Unit/TestingDatabaseGuardTest.php
M	tests/bootstrap.php
```

Nothing omitted. `.env`, `.env.testing`, SQL backups, and nginx backups are not in the list. `public/build` was not rebuilt (no frontend product changes).

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `c4a7ea707f7e523f8decdd17e75424328266d631`
* REPORT SHA/files commit: *(this commit)*
* push result: *(recorded after push)*

## Problems / Warnings

* Production MySQL user `sales` still has `ALL PRIVILEGES` on `sales_testing.*`. That user was not modified (task constraint). Tests no longer use it.
* ffprobe/ffmpeg still not installed.
* Vite optional `fontaine` warning (pre-existing).
* No 200 MB binary was uploaded; 70 MiB nginx acceptance plus config alignment is the proof.

## Final Status

`PHASE 2.2 PASSED`
