# Sales Analyzer — Architecture

## Intent

Keep the system understandable.

On day one, prefer **one Laravel application** that orchestrates upload, queues, storage, and AI calls.

Add a separate Python/AI service only if Laravel cannot reasonably host a required library or performance becomes a real problem. That split is **not** the default.

## Current stack (running)

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel 13.31 | Installed in `/var/www/sales` |
| Language | PHP 8.5.8 | PHP-FPM socket `/run/php/php8.5-fpm.sock` |
| Database | MySQL 8.0.46 | Database `sales`, user `sales`@`localhost` |
| Frontend | Inertia + React + Vite | Kit frontend-setup completed |
| Admin | OwlSolutions Custom Admin Kit v0.5.0 | Stock `admin` preset |
| Telegram SDK | Nutgram 4.50.0 | Installed with the kit; bot **not** configured |
| Web server | nginx 1.24.0 | Site config only for this domain |
| OS | Ubuntu 24.04.4 LTS | Shared server; this project is isolated |
| Domain | `https://sales.owlsolutions.net` | HTTPS via Let’s Encrypt |
| Git | `https://github.com/Owiiiii1/sales.git` | Branch `main` |

See [STATUS.md](STATUS.md) for operational detail.

## Current application shape

Laravel 13 + Custom Admin Kit, with a main analyzer workspace and an admin foundation:

* guest `/` → main Sales Analyzer workspace (optional Company / Employee, upload, transcript, structured report);
* `/login` → admin login;
* authenticated `/dashboard` → sales analytics (period / company / employee filters);
* product admin routes: `/companies`, `/companies/{company}` (knowledge + analytics tabs), `/employees`, `/employees/{employee}`, `/calls`;
* kit CRM routes still exist but are not in primary navigation.

Domain models **Company**, **Employee**, **Call**, **Transcript**, **SalesAnalysis**, and company knowledge (`CompanyProfile`, offerings, objections, scripts, scorecards) exist. Audio is stored on a **private** Laravel disk (`calls` → `storage/app/private/calls`). Analyzer uploads reuse that disk and are **not** streamed to unauthenticated users.

Pipeline: upload → `TranscribeCall` (ElevenLabs Scribe v2) → `AnalyzeCall`. `AnalyzeCall` uses `AnalysisContextBuilder` then **one** structured LLM call (schema v3). A second coaching pass was considered and rejected: the v3 JSON is bounded by collection limits, two calls would duplicate the transcript and company context, and failure/latency would double. `ConversationMetricsCalculator` overwrites talk-time metrics from transcript segments after speaker-role mapping (DEC-049). The Main Analyzer UI requires an explicit Generic or Company choice before upload (DEC-063). Analyzer uploads with `company_id` null get full generic deep analysis. Analyzer or admin uploads with a Company also receive that company’s knowledge, facts, and default active scorecard (DEC-057 / DEC-060). Context packing is UTF-8 character-safe with a 50,000-character budget (DEC-058). If transcription is not ready, analyzer upload is rejected with a generic unavailable message (no provider names). If no AI key/model is configured, the Call stays `analysis_pending`. Stored v1/v2 results remain presentable.

Admin Settings expose pipeline health (`AnalysisPipelineHealth`), a Transcription tab (`transcription_provider_settings`), existing AI provider cards, and application analysis settings (`analysis_settings`: report language = same as call, max output tokens). Runtime credentials prefer the database; `.env` is fallback only (DEC-053 / DEC-054). Workers read DB keys without restart (DEC-056). `php artisan config:cache` can freeze env fallback values; it does not freeze DB keys.

Admin analytics (`DashboardAnalyticsService`, `CompanyAnalyticsService`, `EmployeeAnalyticsService`) aggregate existing `calls` / `sales_analyses` rows. No analytics tables. Date basis is `COALESCE(recorded_at, created_at)` in the application timezone (DEC-042 / DEC-045). JSON section scores are aggregated in PHP, not via opaque MySQL JSON SQL. Charts are lightweight SVG (no extra chart library).

Analytics formulas (shared `AnalyticsAggregator`):

* Analyzed = calls with `status = completed`
* Completion rate = completed / total calls in the period
* Failed rate = failed / total
* Pending rate = (total − completed − failed) / total
* Analysis success rate = completed / (completed + failed); N/A when none finished
* Average sales score = mean of `overall_score` (nulls ignored)
* Average company scorecard = mean of `company_scorecard_score` (nulls ignored; never mixed with generic score)
* Average duration = mean of non-null `calls.duration_seconds`
* Trend % = current period vs previous window of the same length; N/A for `all_time` or when the previous window has no comparable data

Score bands (`config/sales-analyzer.php` `analytics.score_good` / `score_warning`): 80–100 good, 60–79 warning, below 60 poor. Empty scores render as **N/A**, never `NaN` or a fake 0.

Audio path pattern on the `calls` disk:

* admin: `{company_id}/{year}/{month}/{uuid}.{ext}`
* public: `public/{year}/{month}/{uuid}.{ext}`

Original filename is metadata only (DEC-014). Successful upload sets status `uploaded`. STT uses `processing`; AI uses `analyzing` (DEC-032). `completed` means a validated structured analysis exists (DEC-029 / DEC-030).

## Target runtime shape (planned)

Stay inside Laravel unless proven otherwise:

```
Browser
  → nginx
    → PHP-FPM / Laravel
      → MySQL
      → private local disk `calls`
      → queue workers (`sales-worker.service`, database queue)
        → ElevenLabs Scribe v2 (STT + diarization)
        → configured kit LLM (OpenAI / Anthropic / Gemini)
        → embeddings / vector store remain TBD (company knowledge is structured MySQL, not RAG)
```

## Preferred processing chain

```
Upload
  → store audio
  → queue job
  → transcription
  → analysis
  → persist result
  → report ready
```

Status values for a call: `uploaded` → `processing` → `transcribed` → `analysis_pending` or `analyzing` → `completed`, or `failed`.

Queues: production uses `QUEUE_CONNECTION=database` and systemd unit `/etc/systemd/system/sales-worker.service`.

## AI architecture

LLM vendor is **operator-configured** via Settings → AI (`ai_provider_settings`). STT is **operator-configured** via Settings → Transcription (`transcription_provider_settings`). These are separate provider domains (DEC-052). Product does not lock a single LLM vendor (DEC-006 remains Open).

Resolution order for transcription credentials:

```
DB configured provider → env fallback (`config('sales-analyzer.transcription.api_key')`) → not configured
```

`ActiveTranscriptionProvider` is the only resolver. `ElevenLabsTranscriptionClient` does not read `env()` for secrets. Analysis HTTP uses the stored LLM key/model plus application `max_output_tokens` (`ConfiguredSalesAnalysisProvider`).

### Open choices

| Concern | Status | Candidate examples (not a decision) |
|---|---|---|
| LLM | Open (DEC-006) | Kit settings: OpenAI, Anthropic, Gemini. First configured active provider is used. |
| Transcription (STT) | Accepted (DEC-024 / DEC-025) | ElevenLabs Scribe v2 |
| Diarization | Accepted (DEC-027 / DEC-034) | Same ElevenLabs STT call. Transcript speakers stay integers. Seller/customer mapping lives on the analysis JSON. |
| Embeddings | Open | Same LLM vendor or dedicated embeddings API. |
| Vector store | Open | Could be skipped in MVP if context is a short form. Later: pgvector-like, dedicated vector DB, or files+MySQL. **TBD.** |

Fine-tuning is **not** part of MVP. See DEC-004 in [DECISIONS.md](DECISIONS.md).

Analysis is expected to be **several structured LLM calls**, not a mandatory multi-agent framework. See [AI_ANALYSIS.md](AI_ANALYSIS.md).

## Storage

| Data | Current | Planned |
|---|---|---|
| Relational records | MySQL `sales` | Keep MySQL for users, companies, calls, scores |
| Audio files | Private local disk `calls` (`storage/app/private/calls`) | Object storage (S3-compatible) **TBD** |
| Transcripts | `transcripts` + `transcript_segments` | Keep normalized tables |
| Sales analyses | `sales_analyses` (versioned JSON `result`, schema v3) | Keep JSON source of truth; generic `overall_score` and `company_scorecard_score` are separate |
| Transcription / analysis settings | `transcription_provider_settings`, `analysis_settings` | DB is runtime source of truth; `.env` is optional fallback |
| Company knowledge | `company_profiles`, `company_facts`, `company_offerings`, `company_objections`, `company_sales_scripts`, `company_scorecards`, `company_scorecard_criteria`, `company_scorecard_caps` | Documents + optional RAG still **TBD**. No vector store in this phase. |

## Frontend architecture

Current: Inertia React + Vite + Ziggy.

* Public product UI uses `PublicLayout` and `Pages/Public/Home` (upload, polling, transcript, structured report). Upload is disabled when transcription is not ready.
* Admin UI remains Custom Admin Kit layouts (`AdminLayout`, `/dashboard`, CRUD, Settings).
* Settings tabs: General, Users, Transcription, AI, App. Pipeline status is shown on Transcription and AI.
* Same Laravel/Inertia app; no separate frontend.

## Isolation on the server

This project must not affect other sites on the same host.

Constraints (accepted operationally during deploy):

* project files only under `/var/www/sales`;
* dedicated MySQL database `sales` (production) and `sales_testing` (PHPUnit only, isolated MySQL user);
* dedicated nginx vhost `sales.owlsolutions.net`;
* dedicated queue worker `sales-worker.service` (this project only);
* no edits to other nginx sites, other `.env` files, or other databases;
* nginx `reload` after `nginx -t`, not a casual `restart`.

## Testing isolation

PHPUnit must never be able to open production `sales`.

* database name: `sales_testing`
* MySQL user: `sales_testing` (privileges only on `sales_testing.*`)
* credentials: local `.env.testing` (gitignored); `.env.testing.example` has no password
* `App\Support\TestingDatabaseGuard` hard-fails if `APP_ENV` is not `testing` or `DB_DATABASE` is `sales`
* bootstrap: `tests/bootstrap.php` loads `.env.testing` and forces the testing database/user before Laravel boots

See DEC-021 and DEC-022.

## What not to do yet

* Do not add microservices “for AI cleanliness.”
* Do not train a custom model.
* Do not pick an LLM provider in code until a decision is recorded.
* Do not migrate kit CRM tables away until Phase 1 adaptation is specified.

## Open architecture questions

* Audio object storage.
