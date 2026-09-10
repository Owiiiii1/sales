# Sales Analyzer — Roadmap

This roadmap is **working**. Phases, order, and contents will change.

It is not a contract and not a sprint plan.

## Phase 0 — Infrastructure

**Status: COMPLETED**

* Laravel 13
* MySQL database `sales`
* OwlSolutions Custom Admin Kit v0.5.0
* nginx vhost + HTTPS
* GitHub `main`
* admin authentication
* admin login at site root (`/`)

No Sales Analyzer product features in this phase.

## Phase 1 — Adapt Admin Foundation

**Status: COMPLETED**

* navigation: Dashboard, Companies, Employees, Calls, Settings, Statistics/Logs
* kit CRM items hidden from primary nav (Customers, Orders, Services, Staff, Calendar)
* Telegram tab hidden from Settings navigation; route still works
* domain tables `companies`, `employees`, `calls`
* admin CRUD without audio upload or AI
* Dashboard cards from real DB counts
* Feature tests on isolated `sales_testing` MySQL database

## Phase 2 — Core Domain

**Status: COMPLETED** (admin audio upload foundation)

* private `calls` disk (`storage/app/private/calls`)
* Upload Call form (company, optional employee, audio, optional recorded_at)
* authenticated audio stream + download
* Call detail page with player and transcript/AI stubs
* status after upload: `uploaded` (no STT/AI)
* file deleted with the Call

Remaining later: object storage, processing UI beyond stubs, public analysis engine.

## Phase 2.1 — Public Analyzer Shell

**Status: COMPLETED**

* `/` is the public analyzer homepage (no auth)
* `/login` is admin login; guests on admin routes redirect to `/login`
* public `POST /analyze` reuses private audio storage
* `calls.company_id` nullable for public uploads
* opaque `public_token` for anonymous status/report
* polling UI prepared; no fake AI results
* no public audio streaming

## Phase 2.2 — Test isolation & upload limits

**Status: COMPLETED**

* dedicated MySQL user `sales_testing` (privileges only on `sales_testing.*`)
* hard-fail if tests would use production database `sales`
* nginx `client_max_body_size 210M` on `sales.owlsolutions.net` only

## Phase 3 — Transcription

**Status: COMPLETED**

* ElevenLabs Scribe v2 (`POST /v1/speech-to-text`)
* diarization + timestamps
* `transcripts` / `transcript_segments`
* async `TranscribeCall` job + `sales-worker.service`
* public and admin transcript UI
* languages `en` / `ru` / `uk`

Live provider smoke is blocked until `ELEVENLABS_API_KEY` is set in this app’s `.env`.

## Phase 4 — AI Sales Analysis

**Status: COMPLETED**

* generic sales analysis (no company RAG)
* `sales_analyses` table, schema version 1
* `AnalyzeCall` chained after `TranscribeCall`
* `analysis_pending` when Settings → AI has no active provider/key/model
* public structured report + admin analysis section
* live external provider verification deferred by Project Manager (DEC-035)

## Phase 5 — Company Context & Scorecards

**Status: COMPLETED**

* `company_profiles` (1:1)
* offerings, objections, sales scripts
* configurable company scorecards + criteria
* `AnalysisContextBuilder` + 24,000-character budget
* schema version 2 with `company_specific`
* immutable analysis context / scorecard snapshots
* public calls remain generic (no extra form)
* live external provider verification deferred by Project Manager (DEC-035)

## Phase 6 — Sales Analytics Dashboard

**Status: COMPLETED**

Scorecard builder already shipped with Phase 5. This phase adds management analytics:

* `/dashboard` KPI cards, period / company / employee filters
* `GET /employees/{employee}` analytics
* Company detail **Analytics** tab
* generic vs company scorecard kept separate
* section / outcome / scorecard snapshot aggregation
* no materialized aggregate tables
* live provider verification deferred (DEC-035)

## Phase 7 — Deep Call Analysis

**Status: COMPLETED**

* schema version 3 (v1/v2 remain presentable)
* executive summary, timeline, signals, objections, coaching priorities
* application-side conversation metrics
* one structured LLM pass (not two)
* public + admin share `AnalysisReport`
* live provider verification deferred (DEC-035)

## Phase 8 — User Product

**Status: Planned** (public upload shell shipped in Phase 2.1)

* accounts;
* personal cabinet;
* history.

The anonymous upload/status/report shell is live. Remaining work is persistence of a user’s own analyses behind an account.

## Phase 9 — Deeper coaching analytics

**Status: Planned**

Core management dashboard shipped in Phase 6. Remaining later:

* scheduled email / Slack / Telegram reports
* CSV/PDF export
* gamification / leaderboards
* predictive forecasting
* automatic coaching plans
* live call monitoring

## Phase 10 — Integrations

**Status: Planned**

* CRM;
* telephony;
* automatic ingestion.

Vendor list in PRODUCT.md is illustrative, not committed.

## Possible inserts / reorder

* Legal/privacy (consent, retention) may need an explicit phase. **TBD.**
* Billing. **TBD.**
* Human review of analyses. **TBD.**

## How to use this document

When a phase finishes, update **Status** here and in [STATUS.md](STATUS.md). Record new decisions in [DECISIONS.md](DECISIONS.md). Do not silently rewrite history of completed phases.
