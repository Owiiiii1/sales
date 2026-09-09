# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Phase 1 — Adapt Admin Foundation:** COMPLETED
* **Phase 2 — Audio upload foundation:** COMPLETED
* **Phase 2.1 — Public Analyzer Shell:** COMPLETED
* **Phase 2.2 — Test isolation & upload limits:** COMPLETED
* **Phase 3 — ElevenLabs transcription:** COMPLETED (code + mocked tests). Live provider verification **blocked**: `ELEVENLABS_API_KEY` is not set in `/var/www/sales/.env`.
* **Next planned work:** Phase 4 AI sales analysis (LLM provider still Open)

## Product vs running app

Guests open `/`, upload a recording, and poll until transcription finishes. Speakers are shown as `Speaker 1`, `Speaker 2`. **No sales scoring / LLM analysis.**

Admins sign in at `/login`. Call detail shows transcript metadata and a Retry transcription action.

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
| Queue | `database` + systemd `sales-worker.service` |
| STT | ElevenLabs Scribe v2 (key not configured on this app yet) |

## Routes

| URL | Who | Result |
|---|---|---|
| `/` | guest | public analyzer |
| `/login` | guest | admin login |
| `/dashboard` | guest | redirect `/login` |
| `/analyze` | guest | public audio upload (queues STT) |
| `/analysis/{token}/status` | guest | safe status JSON |
| `/analysis/{token}` | guest | safe status + transcript when transcribed |
| `POST /calls/{call}/transcribe` | auth | retry STT job |
| admin CRUD | auth | unchanged plus transcript on Call detail |

## Known issues (non-critical)

* `ELEVENLABS_API_KEY` missing on this project; live STT cannot run until it is set.
* ffprobe/ffmpeg not installed; duration often comes from the STT provider after success.
* Vite optional `fontaine` warning.
* `/owl-admin/health` reports preset `core`.
* CAPTCHA is not implemented; public upload is rate-limited instead.
* Production MySQL user `sales` still has grants on `sales_testing.*`. Tests do not use that user.

## Upload limits (effective)

| Layer | Value |
|---|---|
| Laravel `SALES_AUDIO_MAX_MB` | 200 MB |
| PHP-FPM `upload_max_filesize` | 200M |
| PHP-FPM `post_max_size` | 210M |
| nginx `client_max_body_size` (`sales.owlsolutions.net` only) | 210M |
| Effective HTTP cap | **200 MB** (application validation) |

## What is explicitly not done

* no LLM sales analysis / scores / recommendations
* no Manager vs Client speaker mapping
* no public audio streaming
* no CAPTCHA
* no deletion of kit CRM modules
