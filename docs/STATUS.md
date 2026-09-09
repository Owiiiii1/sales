# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Phase 1 — Adapt Admin Foundation:** COMPLETED
* **Phase 2 — Audio upload foundation:** COMPLETED
* **Phase 2.1 — Public Analyzer Shell:** COMPLETED
* **Next planned work:** Phase 3 transcription (provider still Open)

## Product vs running app

Guests open `/` and can upload a sales-call recording. The UI is ready for processing/report states. **No STT/LLM is connected**; after upload the honest message is that the analysis engine is not connected yet.

Admins sign in at `/login` and use the existing Companies / Employees / Calls admin.

## Infrastructure

| Item | Value |
|---|---|
| Server | `116.203.135.175` (shared host; this project isolated) |
| Path | `/var/www/sales` |
| Domain | `https://sales.owlsolutions.net` |
| GitHub | `https://github.com/Owiiiii1/sales.git` |
| Branch | `main` |
| PHP | 8.5.8 FPM |
| Laravel | 13.31.0 |
| Database | MySQL 8 — `sales` |
| Audio disk | `calls` → `storage/app/private/calls` |

## Routes

| URL | Who | Result |
|---|---|---|
| `/` | guest | public analyzer |
| `/login` | guest | admin login |
| `/dashboard` | guest | redirect `/login` |
| `/analyze` | guest | public audio upload |
| `/analysis/{token}/status` | guest | safe status JSON |
| admin CRUD | auth | unchanged |

## Known issues (non-critical)

* nginx `client_max_body_size` for this vhost is still **64M**. PHP-FPM via `public/.user.ini` is **upload_max_filesize=200M**, **post_max_size=210M**. Effective HTTP cap is therefore **64M** until nginx is raised with sudo. Application config remains 200 MB.
* ffprobe/ffmpeg not installed; duration often null.
* Vite optional `fontaine` warning.
* `/owl-admin/health` reports preset `core`.
* CAPTCHA is not implemented; public upload is rate-limited instead.
* PHPUnit uses `tests/bootstrap.php` so feature tests always target `sales_testing`, even when the shell has `APP_ENV=production`.

## What is explicitly not done

* no STT / LLM
* no fake analysis scores
* no public audio streaming
* no CAPTCHA
* no deletion of kit CRM modules
