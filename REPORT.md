# Sales Analyzer — Phase 3 ElevenLabs Transcription Report

## Baseline

* accepted baseline SHA (Phase 2.2 on `main`): `81b5b92506208317d09685e783a7517f3734fc46`
* HEAD before work: `81b5b92506208317d09685e783a7517f3734fc46`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none

Production database backup (outside Git): `/home/deploy/backups/sales/sales-pre-phase3-20260909-141308.sql`

## Provider

* ElevenLabs
* Scribe v2 (`model_id=scribe_v2`)
* endpoint: `POST https://api.elevenlabs.io/v1/speech-to-text`
* type: batch / prerecorded (multipart file upload)
* request options: `diarize=true`, `timestamps_granularity=word`
* auth header: `xi-api-key` from `ELEVENLABS_API_KEY` (never committed)

Language handling:

* ElevenLabs has no language allowlist parameter; `language_code` is only a single-language hint.
* This phase does **not** send a language hint so the provider auto-detects.
* After the response, `language_code` is normalized (`eng`→`en`, `rus`→`ru`, `ukr`→`uk`) and must be `en`, `ru`, or `uk`.
* Any other language is a permanent failure. Public copy: `This language is not supported yet.`
* HTTP details stay in `ElevenLabsTranscriptionClient`. Jobs and controllers consume `TranscriptionResult` only.

## Data Model

Additive migration `2026_09_09_160000_create_transcripts_tables` (batch 5 on production). No `migrate:fresh` / reset.

`transcripts`:

* `call_id` unique FK cascade
* `provider`, `model`, `language`
* `raw_text`, `duration_seconds`, `confidence`
* `provider_request_id`
* `provider_metadata` JSON (compact only: request id, detected language, model, speaker count, duration, language probability)
* `started_at`, `completed_at`

`transcript_segments`:

* `transcript_id` FK cascade
* `speaker` unsigned int (0-based)
* `start_seconds`, `end_seconds`, `text`, `confidence`, `sequence`
* indexes: `transcript_id`, `sequence`, `speaker`

Call hasOne Transcript. Transcript hasMany segments ordered by `sequence`. Retranscribe deletes the old transcript **only after** a successful provider result, inside a transaction.

## Queue

* `QUEUE_CONNECTION=database` (unchanged)
* database `retry_after` default raised to **360** seconds so it exceeds the job timeout (180s)
* Job: `App\Jobs\TranscribeCall`
  * 3 attempts
  * backoff `60 / 180 / 600` seconds
  * timeout 180 seconds
  * `ShouldBeUniqueUntilProcessing` per call id
* systemd unit (not in Git): `/etc/systemd/system/sales-worker.service`
  * User `www-data`
  * WorkingDirectory `/var/www/sales`
  * `php8.5 artisan queue:work database --sleep=3 --tries=3 --timeout=300 --max-time=3600`
  * enabled and **active**
* Other projects’ workers were not changed.

## Pipeline

1. Public or admin upload stores private audio and sets status `uploaded`.
2. `CallProcessingPipeline` dispatches `TranscribeCall` (STT is not called inside the HTTP request).
3. Job verifies the audio file, sets `processing` + `processing_started_at`.
4. `TranscriptionProvider` returns a normalized `TranscriptionResult`.
5. `TranscriptWriter::replace()` transactionally writes transcript + segments.
6. Call duration is updated from the provider when present.
7. Status `transcribed`, `processing_completed_at = now`.

## Status Lifecycle

```
uploaded → processing → transcribed
```

`completed` remains reserved for future AI analysis. Failures use `failed`.

Public messages:

* uploaded: `Your call is queued for transcription.`
* processing: `Transcribing your call…`
* transcribed: `Transcription complete.`

AI report payload stays `null`. Speakers in UI: `Speaker 1`, `Speaker 2`, … Manager/Client labeling is not implemented.

## ElevenLabs Client

`ElevenLabsTranscriptionClient` implements `TranscriptionProvider`.

* Sends the private audio file; does not log bytes, API key, or full transcript.
* Groups consecutive words by `speaker_id` into turn segments.
* Normalizes speaker ids to 0-based integers (`speaker_0` / `speaker_1` → 0 / 1).
* Does **not** send `detect_speaker_roles` (that would label agent/customer; out of scope).
* Compact `provider_metadata` only; public JSON never includes it.

## Language Support

Product languages: English, Russian, Ukrainian (`en`, `ru`, `uk`). Config: `config/sales-analyzer.php` → `transcription.supported_languages`. Unsupported detected language fails the call without storing a transcript.

## Speaker Diarization

Enabled in the same STT request (`diarize=true`). Stored as integer `speaker` on `transcript_segments`. Frontend label is `Speaker N` (N = speaker + 1). No Manager/Client mapping.

## Public UI

* Polls while status is `uploaded` or `processing`.
* Stops on `transcribed`, `failed`, or `completed`.
* `GET /analysis/{public_token}` after `transcribed` returns status, language, duration, full text, and segments (human-readable timestamps).
* Does not return Call id, Transcript id, `storage_path`, `provider_metadata`, raw provider JSON, or API info.
* AI analysis sections remain empty / future state.

## Admin UI

Call detail shows provider (ElevenLabs), model (Scribe v2), detected language, duration, full transcript, speaker segments, and timestamps.

* `processing`: spinner
* `Retry transcription` for `uploaded`, `failed`, and `transcribed`
* Action: `POST /calls/{call}/transcribe` dispatches the job; does not call the provider in the controller
* Retry while `processing` is rejected

## Failure & Retry

Transient (rethrown, Laravel retries): network timeout, HTTP 429, HTTP 5xx.

Permanent (mark `failed`, no extra attempts): missing key, missing audio, HTTP 4xx, invalid payload, unsupported language.

On failure:

* Call `status=failed`, `processing_completed_at=now`
* `error_message` is a sanitized public string
* audio is retained
* an existing successful transcript is **not** deleted if a later provider call fails

Logs include technical cause, HTTP status, and provider error code when present. They do not include the API key, audio bytes, or full transcript.

## Tests

`php artisan test`: **61 passed, 0 failed, 337 assertions**. No live ElevenLabs HTTP.

Coverage in `tests/Feature/TranscriptionTest.php` (Http::fake / sequence):

* public upload dispatches job
* admin upload dispatches job
* job sets processing then transcribed
* Transcript + segments + language + duration stored
* unsupported language fails
* provider 5xx is retryable (status stays `processing`)
* provider permanent 4xx fails and keeps audio
* retry preserves old successful transcript on later provider failure
* public transcript response is safe (no ids / storage_path / provider_metadata)
* admin detail includes transcript
* admin retry route dispatches job
* missing audio is a permanent failure

`Queue::fake()` in `tests/TestCase` so the suite never runs STT on `QUEUE_CONNECTION=sync`. Phase 2.2 production DB guard remains green (`TestingDatabaseIsolationTest`).

`npm run build` succeeded (optional Vite `fontaine` warning only).

## Real ElevenLabs Verification

* key present: **no** (`ELEVENLABS_API_KEY` is not set in this app’s `.env`; other projects were not copied)
* success/failure: **not run**
* detected language: n/a
* speaker count: n/a
* segment count: n/a
* duration: n/a
* cleanup: n/a

`LIVE PROVIDER VERIFICATION BLOCKED — ELEVENLABS_API_KEY missing`

Code is complete against mocked tests. Live STT will stay failed until this app’s environment has a real key.

## Production Sentinel

Recorded on production connection `database=sales` immediately before tests and after tests + `php artisan migrate --force` (nothing pending).

| Metric | Before | After |
|---|---|---|
| users | 1 | 1 |
| companies | 0 | 0 |
| employees | 0 | 0 |
| calls | 0 | 0 |
| transcripts | 0 | 0 |
| transcript_segments | 0 | 0 |
| jobs | 0 | 0 |
| admin | id `1`, `admin@admin.com` | id `1`, `admin@admin.com` |

No production row changes during the suite.

## Documentation

Updated:

* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/AI_ANALYSIS.md`
* `docs/PRODUCT.md`
* `docs/ROADMAP.md`
* `docs/STATUS.md`
* `docs/DECISIONS.md`

Accepted:

* DEC-024 — ElevenLabs is initial STT provider
* DEC-025 — Scribe v2 is initial transcription model
* DEC-026 — Supported languages are EN/RU/UK
* DEC-027 — Diarized transcript stored in normalized segments
* DEC-028 — Transcription runs asynchronously
* DEC-029 — `transcribed` is separate from full AI completion

DEC-007 (STT provider) is **Superseded**.

## Changed Files

`git diff --stat 81b5b92506208317d09685e783a7517f3734fc46..f29786dc09f699783da302f90d4cde1023def4a5`

```
 .env.example                                       |   3 +
 README.md                                          |   4 +-
 REPORT.md                                          | 337 ++++++++++++++-------
 .../PermanentTranscriptionException.php            |  19 ++
 .../Transcription/TranscriptionException.php       |  13 +
 .../TransientTranscriptionException.php            |   7 +
 app/Http/Controllers/CallsController.php           |  19 +-
 app/Http/Controllers/PublicAnalyzerController.php  |  28 +-
 app/Jobs/TranscribeCall.php                        | 113 +++++++
 app/Models/Call.php                                |   7 +
 app/Models/Transcript.php                          |  48 +++
 app/Models/TranscriptSegment.php                   |  43 +++
 app/Providers/AppServiceProvider.php               |   4 +-
 app/Services/Calls/CallProcessingPipeline.php      |  11 +-
 .../Transcription/DTO/TranscriptionResult.php      |  22 ++
 .../Transcription/DTO/TranscriptionSegment.php     |  14 +
 .../ElevenLabsTranscriptionClient.php              | 288 ++++++++++++++++++
 app/Services/Transcription/TranscriptWriter.php    |  54 ++++
 .../Transcription/TranscriptionProvider.php        |  11 +
 app/Support/LanguageCode.php                       |  41 +++
 app/Support/TranscriptPresenter.php                |  96 ++++++
 config/queue.php                                   |   2 +-
 config/sales-analyzer.php                          |  11 +
 ...2026_09_09_160000_create_transcripts_tables.php |  49 +++
 docs/AI_ANALYSIS.md                                |  14 +-
 docs/ARCHITECTURE.md                               |  33 +-
 docs/DATA_MODEL.md                                 |  58 ++--
 docs/DECISIONS.md                                  |  94 +++++-
 docs/PRODUCT.md                                    |   2 +-
 docs/ROADMAP.md                                    |  15 +-
 docs/STATUS.md                                     |  24 +-
 resources/js/Components/Public/AnalysisReport.jsx  |  19 +-
 resources/js/Components/Public/CallTranscript.jsx  |  44 +++
 resources/js/Pages/Calls/Index.jsx                 |   2 +-
 resources/js/Pages/Calls/Show.jsx                  |  49 ++-
 resources/js/Pages/Public/Home.jsx                 |  25 +-
 routes/owl-admin-pages.php                         |   1 +
 tests/Feature/CallsTest.php                        |   3 +
 tests/Feature/PublicAnalyzerTest.php               |   5 +
 tests/Feature/TranscriptionTest.php                | 269 ++++++++++++++++
 tests/TestCase.php                                 |   1 +
 tests/Unit/LanguageCodeTest.php                    |  24 ++
 42 files changed, 1711 insertions(+), 215 deletions(-)
```

`git diff --name-status` vs the same baseline:

```
M	.env.example
M	README.md
M	REPORT.md
A	app/Exceptions/Transcription/PermanentTranscriptionException.php
A	app/Exceptions/Transcription/TranscriptionException.php
A	app/Exceptions/Transcription/TransientTranscriptionException.php
M	app/Http/Controllers/CallsController.php
M	app/Http/Controllers/PublicAnalyzerController.php
A	app/Jobs/TranscribeCall.php
M	app/Models/Call.php
A	app/Models/Transcript.php
A	app/Models/TranscriptSegment.php
M	app/Providers/AppServiceProvider.php
M	app/Services/Calls/CallProcessingPipeline.php
A	app/Services/Transcription/DTO/TranscriptionResult.php
A	app/Services/Transcription/DTO/TranscriptionSegment.php
A	app/Services/Transcription/ElevenLabsTranscriptionClient.php
A	app/Services/Transcription/TranscriptWriter.php
A	app/Services/Transcription/TranscriptionProvider.php
A	app/Support/LanguageCode.php
A	app/Support/TranscriptPresenter.php
M	config/queue.php
M	config/sales-analyzer.php
A	database/migrations/2026_09_09_160000_create_transcripts_tables.php
M	docs/AI_ANALYSIS.md
M	docs/ARCHITECTURE.md
M	docs/DATA_MODEL.md
M	docs/DECISIONS.md
M	docs/PRODUCT.md
M	docs/ROADMAP.md
M	docs/STATUS.md
M	resources/js/Components/Public/AnalysisReport.jsx
A	resources/js/Components/Public/CallTranscript.jsx
M	resources/js/Pages/Calls/Index.jsx
M	resources/js/Pages/Calls/Show.jsx
M	resources/js/Pages/Public/Home.jsx
M	routes/owl-admin-pages.php
M	tests/Feature/CallsTest.php
M	tests/Feature/PublicAnalyzerTest.php
A	tests/Feature/TranscriptionTest.php
M	tests/TestCase.php
A	tests/Unit/LanguageCodeTest.php
```

Secret scan: no API key values in Git paths. `.env` and `.env.testing` are not staged. `.env.example` contains empty `ELEVENLABS_API_KEY=`.

## Git

* branch: `main`
* implementation commit: `f29786dc09f699783da302f90d4cde1023def4a5`
* message: `Add async ElevenLabs Scribe v2 transcription with diarized segments.`
* push: pending

## Problems / Warnings

* `ELEVENLABS_API_KEY` is missing on this app. Uploaded calls will queue, then fail permanently with a generic public error until a key is added to `/var/www/sales/.env` and the worker is restarted if config is cached.
* Live provider smoke was not run (blocked by missing key).
* Vite optional `fontaine` warning on `npm run build`.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged from Phase 2.2). Tests use `sales_testing` only.
* ffprobe/ffmpeg still not installed; duration is filled from the STT provider after success.

## Final Status

`PHASE 3 CODE PASSED — LIVE PROVIDER VERIFICATION BLOCKED`
