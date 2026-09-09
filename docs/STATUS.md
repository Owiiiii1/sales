# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Documentation bootstrap:** COMPLETED
* **Phase 1 — Adapt Admin Foundation:** COMPLETED
* **Phase 2 — Audio upload foundation:** COMPLETED
* **Next planned work:** Phase 3 transcription (provider still Open)

## Product vs running app

Admins can upload call audio, open a Call card, play/download privately, and delete the record with its file.

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
| Audio disk | `calls` → `storage/app/private/calls` (private, not web-accessible) |

Admin login: guest `GET /` → login. Protected product routes redirect guests to `/`.

## Current UI

Primary navigation:

* Dashboard
* Companies
* Employees
* Calls
* Settings
* Statistics / Logs

Calls:

* list with filters (company, employee, status)
* Upload Call
* detail with HTML audio player, download, metadata, transcript/AI stubs
* edit company / employee / recorded at
* no audio replace (delete + re-upload)

## Database

Phase 1 tables unchanged: `companies`, `employees`, `calls`. No Phase 2 migration.

## Audio limits

Application config: `SALES_AUDIO_MAX_MB=200` (`config/sales-analyzer.php`).

Effective HTTP cap on this host is lower until nginx/PHP-FPM site limits are raised (see REPORT). Tiny admin uploads still work.

## Known issues (non-critical)

* `/owl-admin/health` JSON reports `"preset":"core"` while the installed preset is `admin`.
* Composer 2.7.1 PHP 8.5 deprecation notices.
* Vite optional `fontaine` warning.
* Weak install-stage admin credentials (`admin@admin.com`).
* PHPUnit uses MySQL `sales_testing` because the server has no SQLite PDO driver.
* Inertia `assertInertia()->component()` file finder defaults to `resources/js/pages` (lowercase); tests pass `shouldExist = false`.
* ffprobe/ffmpeg are **not** installed; duration is nullable except best-effort WAV header parse.
* Site nginx `client_max_body_size` is 64M; PHP-FPM defaults remain `upload_max_filesize=2M` / `post_max_size=8M` unless `public/.user.ini` is honored. Could not change nginx without sudo.

## What is explicitly not done

* no STT / LLM / embeddings
* no public Upload Call
* no automatic processing job
* no audio replace
* no scorecards in DB
* no deletion of kit CRM modules

## Next planned work

Transcription / diarization (Phase 3). Do not choose providers until DEC-006 / DEC-007 close.
