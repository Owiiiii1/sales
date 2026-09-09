# Sales Analyzer — Phase 2 Audio Upload Foundation Report

## Baseline

* accepted baseline SHA (Phase 1 on `main`): `84947b926c4cc223b241582ffbf6246b331c91cc`
* HEAD before work: `84947b926c4cc223b241582ffbf6246b331c91cc`
* working tree before work: clean
* unpushed commits before work: none
* HEAD matched the last Technical Lead accepted Phase 1 state. No extra Git changes existed before this phase.

## Implemented

Production-safe **admin** audio upload for Calls. No LLM, STT, transcription, diarization, embeddings, or public upload.

* Private Laravel disk `calls` → `storage/app/private/calls` (not `public/`, not `/storage` symlink)
* Upload Call flow: company required, employee optional, audio required, recorded_at optional, source default `manual`
* Generated physical path `{company_id}/{year}/{month}/{uuid}.{ext}`
* Original filename stored only in the database
* Successful upload sets `status = uploaded` (not `completed` / `processing`)
* Call detail page with metadata, HTML player, download, transcript/AI stubs
* Authenticated stream and download endpoints
* HTTP Range supported (live `206` with `Content-Range`)
* Edit limited to company, employee, recorded_at (mismatch employee rejected; UI clears employee when company changes)
* Delete Call row first, then delete its audio file; empty parent dirs pruned
* If DB create fails after a file write, the file is deleted
* `CallProcessingPipeline` hook is invoked but does not start processing (`shouldDispatchAfterUpload()` is false)
* Config: `config/sales-analyzer.php` + `.env.example` keys `SALES_AUDIO_MAX_MB`, `SALES_AUDIO_DISK`
* No new DB migration (Phase 1 `calls` columns were sufficient)
* No notes column
* No audio replace

## Upload Flow

1. `StoreCallRequest` validates company, optional employee-belongs-to-company, audio extension, MIME types, and max size from config.
2. `CallAudioStorage` writes the file to the private `calls` disk with a UUID name.
3. `CallUploadService` then inserts the Call row (`status=uploaded`, `uploaded_by`, metadata).
4. If the DB write throws, the stored file is deleted and the user sees a generic storage error. The exception is logged.
5. Redirect to `calls.show`.

User cannot submit `storage_path`, MIME, file size, processing timestamps, or error_message through the upload form.

## Storage

* disk name: `calls` (`SALES_AUDIO_DISK`, default `calls`)
* root: `storage/app/private/calls`
* visibility: private; `serve` is false
* path: `{company_id}/{year}/{month}/{uuid}.{ext}`
* max size (app): `200` MB via `SALES_AUDIO_MAX_MB` / `config('sales-analyzer.max_audio_size_mb')` / `max_audio_size_kb`
* allowed extensions: mp3, wav, m4a, mp4, ogg, webm
* allowed MIME types: listed in `config/sales-analyzer.php` (includes `audio/mpeg`, wav variants, `audio/mp4`, `audio/ogg`, `audio/webm`, `video/mp4`, `video/webm` for audio containers)
* directory ACL: `www-data` rwx on the disk root (not `777`)

## Metadata

Saved on upload:

* original_filename
* storage_path
* mime_type
* file_size
* source (`manual` unless provided)
* uploaded_by
* recorded_at (optional)
* company_id
* employee_id (optional)
* status = `uploaded`

The Inertia detail payload does **not** include `storage_path`. Frontend gets `audio_url` / `download_url` / `has_audio`.

## Duration Detection

* ffprobe / ffmpeg: **not installed** (`/usr/bin/ffprobe` and `/usr/local/bin/ffprobe` missing)
* not installed globally (per task)
* `AudioMetadataService` uses ffprobe only if an executable is present; otherwise attempts a WAV header parse; otherwise `duration_seconds` stays null
* live WAV sample duration was not required for pass/fail; nullable is expected for most formats until ffprobe exists

## Routes

| Method | Path | Name |
|---|---|---|
| GET | `/calls` | `calls.index` |
| GET | `/calls/create` | `calls.create` |
| POST | `/calls` | `calls.store` |
| GET | `/calls/{call}` | `calls.show` |
| PATCH | `/calls/{call}` | `calls.update` |
| DELETE | `/calls/{call}` | `calls.destroy` |
| GET | `/calls/{call}/audio` | `calls.audio` |
| GET | `/calls/{call}/download` | `calls.download` |

Legacy kit routes unchanged.

## UI

* Calls list: Upload Call button, status badge, filename, company, employee, duration, file size, recorded at, created at, View, Delete
* filters: company, employee, status
* empty list: `No calls uploaded yet`
* Upload page: company, employee (filtered by company), file input, recorded at; friendly validation messages
* Detail: ID, company, employee, status, source, original filename, MIME, human file size, duration, timestamps, uploaded by, processing timestamps, error if failed
* HTML `<audio>` pointing at `calls.audio` (cookie auth, same origin)
* Download uses original filename
* Transcript stub: `Not available yet` / `Transcript will appear here after transcription.`
* AI stub: `Not available yet` / `AI analysis will appear here after processing.`
* Dashboard recent calls link to the detail page

## Security

* audio not in `public/`
* stream/download require admin auth (same middleware group as the rest of the admin)
* physical path not exposed in HTML/JSON
* Content-Type from stored MIME; `X-Content-Type-Options: nosniff`
* download `Content-Disposition` uses sanitized original filename
* missing file → 404
* validation: extension + MIME + size; employee must belong to company
* storage failures logged; user sees a generic message
* `.env` not committed; no secrets added

## Database

* migrations added: **none**
* backup: not taken (no schema change)
* `php artisan migrate --force`: Nothing to migrate
* tables unchanged: `companies`, `employees`, `calls`

## Tests

```
php artisan test
```

Result: **32 passed**, **0 failed**, 177 assertions.

Important cases:

* guest cannot open list/create/show/audio/download or upload
* valid upload creates Call `uploaded`, stores original filename, generated path, `uploaded_by`, file on fake disk
* company required
* invalid extension rejected
* invalid MIME rejected
* oversized file rejected (config lowered in the test)
* employee from another company rejected
* file removed when Call deleted
* detail protected; authenticated detail hides `storage_path`
* stream/download work; missing file 404; download uses original filename
* edit: incompatible employee rejected; clearing employee when changing company succeeds

`Storage::fake()` used. No production audio written by tests. No binary telephone recordings committed.

## Live Verification

Host `https://sales.owlsolutions.net`.

Guest (`curl -sI`, no follow):

| URL | Status | Location |
|---|---|---|
| `/calls` | 302 | `https://sales.owlsolutions.net` |
| `/calls/create` | 302 | `https://sales.owlsolutions.net` |
| `/calls/1` | 302 | `https://sales.owlsolutions.net` |
| `/calls/1/audio` | 302 | `https://sales.owlsolutions.net` |
| `/calls/1/download` | 302 | `https://sales.owlsolutions.net` |

Authenticated (synthetic silent WAV, no personal data):

* created company `Phase2 Probe Co`
* POST `/calls` multipart `phase2-sample.wav` → Call `#1`, `status=uploaded`, `original_filename=phase2-sample.wav`, `has_audio=true`, `storage_path` absent from Inertia props
* `GET /calls/1` → 200 `Calls/Show`
* `GET /calls/1/audio` → 200, `Content-Type: audio/x-wav`, `Accept-Ranges: bytes`, 1644 bytes
* `Range: bytes=0-10` → **206**, `Content-Range: bytes 0-10/1644`
* `GET /calls/1/download` → 200, `attachment; filename=phase2-sample.wav`
* DELETE call then DELETE company
* production counts after cleanup: companies **0**, calls **0**
* `Storage::disk('calls')` files/dirs empty

## Build

* `npm run build` — succeeded (Vite fontaine optional warning remains)
* `php artisan optimize:clear` — succeeded
* `php artisan migrate --force` — nothing pending
* nginx / SSL / PHP-FPM pool files **not** changed (sudo password required)

## Documentation

Updated:

* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/PRODUCT.md`
* `docs/ROADMAP.md` — Phase 2 COMPLETED
* `docs/STATUS.md`
* `docs/DECISIONS.md` — DEC-013 … DEC-016
* `docs/PROJECT.md`
* `README.md`

## Changed Files

Command:

```
git diff --name-status 84947b926c4cc223b241582ffbf6246b331c91cc..HEAD
```

Result after implementation commit `0fce8a17d3b12a9466372dad2a350eab44051cf0` (later REPORT-only commits only rewrite this file):

```
M	.env.example
M	README.md
M	REPORT.md
M	app/Http/Controllers/CallsController.php
M	app/Http/Controllers/DashboardController.php
D	app/Http/Requests/CallRequest.php
A	app/Http/Requests/Concerns/ValidatesEmployeeCompany.php
A	app/Http/Requests/StoreCallRequest.php
A	app/Http/Requests/UpdateCallRequest.php
M	app/Models/Call.php
A	app/Services/Calls/AudioMetadataService.php
A	app/Services/Calls/CallAudioStorage.php
A	app/Services/Calls/CallAudioStreamer.php
A	app/Services/Calls/CallProcessingPipeline.php
A	app/Services/Calls/CallUploadService.php
M	config/filesystems.php
A	config/sales-analyzer.php
M	docs/ARCHITECTURE.md
M	docs/DATA_MODEL.md
M	docs/DECISIONS.md
M	docs/PRODUCT.md
M	docs/PROJECT.md
M	docs/ROADMAP.md
M	docs/STATUS.md
A	public/.user.ini
A	resources/js/Pages/Calls/Create.jsx
M	resources/js/Pages/Calls/Index.jsx
A	resources/js/Pages/Calls/Show.jsx
M	resources/js/Pages/Dashboard.jsx
M	routes/owl-admin-pages.php
M	tests/Feature/CallsTest.php
M	tests/Feature/NavigationRoutesTest.php
```

Nothing omitted. `.env` is not in the list. `public/build` is gitignored. No production audio and no DB dump are in the list.

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `0fce8a17d3b12a9466372dad2a350eab44051cf0`
* commit messages:
  * `Add private call audio upload with authenticated playback.`
  * `Record Phase 2 commit SHA and changed files in REPORT.md.`
* push result: pending

## Problems / Warnings

* ffprobe/ffmpeg are not on the server. Duration is nullable except best-effort WAV header parsing.
* Could not raise nginx `client_max_body_size` (still **64M**) or PHP-FPM pool limits (`upload_max_filesize=2M`, `post_max_size=8M`) without sudo. App config allows 200 MB. `public/.user.ini` was added so PHP-FPM may honor per-site limits if `user_ini` is enabled. Until nginx/PHP actually apply 200M, large HTTP uploads can fail at the web/PHP layer. Small admin uploads work (live 1644-byte WAV succeeded).
* Vite optional `fontaine` warning (pre-existing).
* `/owl-admin/health` still reports preset `core` (pre-existing).
* Composer PHP 8.5 deprecation notices (pre-existing).
* During live upload, www-data created a company directory deploy could not list until an authenticated cleanup removed empty leftovers. Default POSIX ACLs were set on `storage/app/private/calls` so future dirs stay manageable. No `777`.
* Laravel `Rule::extensions` / `Rule::mimetypes` are not available on this framework build; validation uses `File::types()`, string `mimetypes:…`, and an explicit original-extension check.

## Final Status

`PHASE 2 PASSED`
