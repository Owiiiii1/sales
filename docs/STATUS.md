# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Phase 1 — Adapt Admin Foundation:** COMPLETED
* **Phase 2 — Audio upload foundation:** COMPLETED
* **Phase 2.1 — Public Analyzer Shell:** COMPLETED
* **Phase 2.2 — Test isolation & upload limits:** COMPLETED
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
| Database | MySQL 8 — production `sales`; tests `sales_testing` (user `sales_testing`) |
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

* ffprobe/ffmpeg not installed; duration often null.
* Vite optional `fontaine` warning.
* `/owl-admin/health` reports preset `core`.
* CAPTCHA is not implemented; public upload is rate-limited instead.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged by this phase). Tests do not use that user.

## Upload limits (effective)

| Layer | Value |
|---|---|
| Laravel `SALES_AUDIO_MAX_MB` | 200 MB |
| PHP-FPM `upload_max_filesize` | 200M |
| PHP-FPM `post_max_size` | 210M |
| nginx `client_max_body_size` (`sales.owlsolutions.net` only) | 210M |
| Effective HTTP cap | **200 MB** (application validation) |

## What is explicitly not done

* no STT / LLM
* no fake analysis scores
* no public audio streaming
* no CAPTCHA
* no deletion of kit CRM modules
