# Sales Analyzer — Current Status

Factual state of the running project. Update this file when reality changes.

## Current phase

* **Phase 0 — Infrastructure:** COMPLETED
* **Phase 1 — Adapt Admin Foundation:** COMPLETED
* **Phase 2 — Audio upload foundation:** COMPLETED
* **Phase 2.1 — Public Analyzer Shell:** COMPLETED
* **Phase 2.2 — Test isolation & upload limits:** COMPLETED
* **Phase 3 — ElevenLabs transcription:** COMPLETED (code + mocked tests). Live ElevenLabs verification deferred by Project Manager.
* **Phase 4 — Structured AI sales analysis:** COMPLETED (application code + mocked tests). Live LLM verification deferred by Project Manager.
* **Phase 5 — Company knowledge & scorecards:** COMPLETED (application code + mocked tests). Live LLM verification deferred by Project Manager.
* **Phase 6 — Sales analytics dashboard:** COMPLETED (application code + mocked tests). Live LLM verification deferred by Project Manager.
* **Phase 7 — Deep call analysis (schema v3):** COMPLETED (application code + mocked tests). Live LLM verification deferred by Project Manager.
* **Phase 7.1 — Provider settings completion:** COMPLETED (application code + mocked tests). Live ElevenLabs / LLM verification deferred by Project Manager.
* **Phase 7.2 — Main analyzer company selection:** COMPLETED (application code + mocked tests).
* **Next planned work:** Phase 8 user product (accounts / personal cabinet), unless the roadmap is reordered.

## Product vs running app

Guests open `/`, optionally select a Company and Employee, upload a recording, and poll through transcription and analysis. Default is generic analysis (no company). If a Company is selected, existing company knowledge and the default scorecard are used (DEC-057). If transcription is not configured, the analyzer UI shows `Audio analysis is temporarily unavailable.` Speakers remain `Speaker 1`, `Speaker 2` on the transcript. Seller/customer mapping is analysis metadata.

Admin company pages hold Sales Knowledge, offerings, objections, scripts, and scorecards. Admin-uploaded calls with a Company use that knowledge automatically (default active scorecard). Generic Overall Sales Score and Company Scorecard scores stay separate.

Settings → Transcription stores the ElevenLabs API key (encrypted) and Scribe v2 model. Settings → AI still holds OpenAI / Anthropic / Gemini. Analysis Behavior stores max output tokens and same-as-call report language. Pipeline Status shows whether both layers are ready.

If Settings → AI has no active provider/key/model, calls stay `analysis_pending` after transcription. Analyzer copy: `Transcription completed, but AI analysis is temporarily unavailable.`

Admins sign in at `/login`. `/dashboard` is the sales analytics view (Last 30 days by default). Company detail includes an Analytics tab. Employees have a detail/analytics page. Call detail shows analysis context metadata. Re-run analysis is disabled until an AI provider is ready; the job still checks configuration.

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
| STT | ElevenLabs Scribe v2 (Settings → Transcription; `.env` fallback) |
| LLM | Settings → AI (OpenAI / Anthropic / Gemini) |

## Routes

| URL | Who | Result |
|---|---|---|
| `/` | guest | main analyzer workspace |
| `/login` | guest | admin login |
| `/analyze` | guest | analyzer audio upload (optional company/employee) |
| `/analysis/{token}/status` | guest | safe status JSON |
| `/analysis/{token}` | guest | transcript + report when ready |
| `/dashboard` | auth | sales analytics |
| `/companies/{company}` | auth | company knowledge + analytics tabs |
| `/employees/{employee}` | auth | employee analytics |
| `POST /calls/{call}/transcribe` | auth | retry STT job |
| `POST /calls/{call}/analyze` | auth | run / re-run analysis job (rejected if AI is not configured) |
| `/settings?tab=transcription` | auth | ElevenLabs STT settings |
| `/settings?tab=ai` | auth | LLM providers + analysis behavior |

## Known issues (non-critical)

* Live ElevenLabs and live LLM verification deferred by Project Manager.
* Without an active AI provider, analysis stays `analysis_pending`.
* Analyzer upload requires a ready transcription provider (DB key + connection check, or env fallback).
* ffprobe/ffmpeg not installed; duration often comes from STT.
* Vite optional `fontaine` warning.
* CAPTCHA is not implemented; analyzer upload is rate-limited instead.
* Production MySQL user `sales` still has grants on `sales_testing.*`. Tests do not use that user.

## What is explicitly not done

* no company document RAG / embeddings / vector store
* no scheduled email/Slack reports or CSV/PDF export
* no CRM integration
* no public audio streaming
* no CAPTCHA
* no deletion of kit CRM modules
