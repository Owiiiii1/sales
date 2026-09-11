# Sales Analyzer — Decision Log

Record product and architecture decisions here.

Do **not** invent Accepted decisions. If something is not decided, status is Open.

Format:

```
DEC-XXX
Title
Status
Date
Decision
Reason
Consequences
```

Status values: `Accepted` | `Open` | `Superseded` | `Rejected`.

---

## DEC-001

**Title:** Laravel as backend foundation  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Build Sales Analyzer as a Laravel application (currently Laravel 13).

**Reason:** Team stack, Custom Admin Kit requires Laravel 13 (`illuminate/* ^13`), existing OwlSolutions hosting pattern.

**Consequences:** PHP/Eloquent/queues/Inertia are the default. A second backend is an exception, not the starting point.

---

## DEC-002

**Title:** OwlSolutions Custom Admin Kit instead of Filament  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Use `owlsolutions/custom-admin-kit` v0.5.0 as the admin UI kit (Inertia/React), not Filament.

**Reason:** Internal standard for OwlSolutions admin products; already installed on this host app.

**Consequences:** Admin UX follows the kit. Kit CRM modules are a starting point, not the product domain. Filament is out of scope unless this decision is superseded.

---

## DEC-003

**Title:** MySQL as the current relational database  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Use MySQL 8 on this server (database `sales`). MariaDB was preferred in generic hosting notes but is not installed here; MySQL is what runs.

**Reason:** Existing server standard; dedicated DB created for isolation.

**Consequences:** Schema and migrations target MySQL. Switching engines would be a new decision.

---

## DEC-004

**Title:** Do not train a separate AI model for MVP  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** MVP uses a general-purpose LLM with prompting, structured output, company context, and scorecards. No from-scratch training. Fine-tuning is not in scope until enough quality-labeled calls exist (future possibility only).

**Reason:** Speed, cost, and lack of labeled data.

**Consequences:** Quality depends on prompts, retrieval, and scorecard design. Provider choice is a separate open decision.

---

## DEC-005

**Title:** Company-specific context is required for quality analysis  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Analysis without company knowledge is considered limited. The product must be designed so relevant company context is supplied before or during scoring (form in MVP; stored knowledge / RAG later).

**Reason:** Sales quality is company- and offer-specific.

**Consequences:** Data model and pipeline include context versioning. Generic-only scoring is not the target quality bar.

---

## DEC-006

**Title:** AI / LLM provider  
**Status:** Open  
**Date:** 2026-09-09

**Decision:** Not selected.

**Reason:** Too early; kit AI settings UI is not a vendor lock-in.

**Consequences:** Do not hard-code a provider in product code until this is Accepted. Candidates may be listed as examples only.

---

## DEC-007

**Title:** STT / transcription (and diarization) provider  
**Status:** Superseded  
**Date:** 2026-09-09

**Decision:** Not selected (superseded by DEC-024 / DEC-025).

**Reason:** Closed in Phase 3.

**Consequences:** See DEC-024 and DEC-025.

---

## DEC-008

**Title:** Avoid premature microservices  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Orchestrate processing inside Laravel if performance and libraries allow. Add a separate Python/AI service only for a real technical need.

**Reason:** Small team, one deployable, fewer moving parts.

**Consequences:** Queue workers in this app are the default. A microservice requires a new decision with a concrete reason.

---

## DEC-009

**Title:** Technical Lead / Cursor / PM work through GitHub and `готово`  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Source of truth is GitHub `main`. Cursor implements, documents, commits, pushes, and replies `готово`. PM tells the Technical Lead `готово`. The Lead reviews GitHub (especially `REPORT.md`) and then accepts or returns the phase.

**Reason:** Review must be of pushed code, not chat transcripts.

**Consequences:** Unpushed local work does not count as done. See [WORKFLOW.md](WORKFLOW.md).

---

## DEC-010

**Title:** Separate Company and Employee domain  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `Company` is the tenant/business domain entity. `Employee` is the person whose calls are analyzed and is **not** a system `User`. Employees do not need login access.

**Reason:** Call quality is scored per company and per salesperson. Mixing kit `customers`/`staff` or equating Employee with User would blur tenancy and coaching.

**Consequences:** New tables `companies` and `employees`. Kit CRM tables remain but are legacy. User remains the login account (`uploaded_by` on calls is optional).

---

## DEC-011

**Title:** Call status stored as string  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `calls.status` is a string, not a MySQL ENUM. Current allowed application values: `pending`, `uploaded`, `processing`, `completed`, `failed`.

**Reason:** The processing pipeline will grow. Strings are easier to evolve than DB enums.

**Consequences:** Validation uses `Rule::in(Call::STATUSES)`. New statuses require a code change, not a schema rewrite.

---

## DEC-012

**Title:** Legacy Admin Kit CRM tables retained temporarily  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Keep kit tables/models/controllers/routes for `customers`, `services`, `staff`, `orders`, `order_staff`, and Calendar. Hide them from primary navigation. Do not use them in new Sales Analyzer code.

**Reason:** Removing working starter modules before the new domain is stable risks kit upgrades and unnecessary production risk.

**Consequences:** Direct URLs to legacy screens still work. They are not product IA. Deletion is a later decision.

---

## DEC-013

**Title:** Call audio is private storage  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Call audio is stored on a private Laravel disk (`calls` → `storage/app/private/calls`) and is never published as a public URL or via the `/storage` symlink.

**Reason:** Recordings are sensitive. Direct links would leak audio without auth.

**Consequences:** Playback and download use authenticated routes (`calls.audio`, `calls.download`). The UI never receives the physical storage path.

---

## DEC-014

**Title:** Physical audio filename is generated  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Stored files are named `{company_id}/{year}/{month}/{uuid}.{ext}`. The original filename is saved only as `calls.original_filename`.

**Reason:** Original names can collide, contain unsafe characters, or leak customer information in filesystem listings.

**Consequences:** Downloads send the original name in `Content-Disposition`. Replacing audio is not supported in this phase; delete and re-upload.

---

## DEC-015

**Title:** Upload and AI processing are separate lifecycle stages  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** A successful file save sets `status = uploaded`. It does not set `completed` or `processing`. STT/LLM work starts in a later phase.

**Reason:** Upload success is not analysis success. Mixing them would hide processing failures.

**Consequences:** `CallProcessingPipeline` is a no-op hook and does not change status. Lifecycle remains `pending` → `uploaded` → `processing` → `completed`, with `failed` on error.

---

## DEC-016

**Title:** Audio files are removed with deleted Calls  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Delete the Call row first. If that succeeds, delete the audio file on the private disk when it exists. Unique generated paths prevent one delete from touching another Call’s file.

**Reason:** If the file were removed first and the DB delete failed, the record would point at missing audio. Orphaned files after a successful DB delete are recoverable from logs/backups; missing files with live rows are worse.

**Consequences:** Failed DB deletes leave the file in place. A later failed file delete can leave an orphan, which is logged.

---

## DEC-017

**Title:** Root domain is public product  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `https://sales.owlsolutions.net/` is the public Sales Analyzer homepage and does not require authentication. Admin login is `/login`. After login, admins land on `/dashboard`.

**Reason:** The primary user journey is upload-and-analyze, not the admin kit.

**Consequences:** Guests hitting protected admin routes redirect to `/login`, not `/`. Authenticated users hitting `/login` go to `/dashboard`.

---

## DEC-018

**Title:** Public Calls may have no Company  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `calls.company_id` is nullable. Generic analyzer uploads store `company_id = null`, `employee_id = null`, `uploaded_by = null`. The main analyzer may also attach an explicit Company and optional Employee (DEC-057). Admin uploads still require a Company.

**Reason:** Public users are not tenants. Inventing a dummy “Public” company would pollute the admin company list.

**Consequences:** Generic analyzer audio is stored under `public/{year}/{month}/` on the private disk. Analyzer uploads with a Company use `{company_id}/{year}/{month}/`. Admin UI still requires company on create.

---

## DEC-019

**Title:** Public Calls use opaque token  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Anonymous status and report access uses a unique, server-generated UUID `public_token`, not the database id.

**Reason:** Sequential `/calls/1` would let anyone enumerate recordings.

**Consequences:** Admin routes keep numeric ids. Public routes are `/analysis/{public_token}` and `/analysis/{public_token}/status`.

---

## DEC-020

**Title:** Public upload reuses private audio storage  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Public `POST /analyze` uses the same private `calls` disk, validation, and cleanup as admin upload. There is no public audio stream or download.

**Reason:** Making the file fetchable by token would leak recordings. Status/report can be public; the bytes stay private until a later product decision.

**Consequences:** Phase 2 admin `calls.audio` / `calls.download` remain auth-only. CAPTCHA is a possible future control; rate limits are the current public-upload throttle.

---

## DEC-021

**Title:** Testing DB uses isolated MySQL credentials  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** PHPUnit uses MySQL database `sales_testing` and MySQL user `sales_testing`. That user has privileges only on `sales_testing.*`. The production user `sales` is unchanged.

**Reason:** The production user also had grants on `sales_testing`. A test process using that user could still open `sales` if `DB_DATABASE` was wrong. A dedicated user removes that capability.

**Consequences:** `.env.testing` holds the testing password and is gitignored. `.env.testing.example` is committed without a password. Tests fail closed if `.env.testing` is missing.

---

## DEC-022

**Title:** Tests hard-fail on production database  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** If Laravel tests see `DB_DATABASE=sales`, or `APP_ENV` is not `testing`, or the DB username is the production `sales` user, the suite aborts with an exception / non-zero exit before `RefreshDatabase`.

**Reason:** Phase 2.1 wiped production `sales` when the shell had `APP_ENV=production`. Environment overrides are not enough.

**Consequences:** Guard lives in `App\Support\TestingDatabaseGuard`, `tests/bootstrap.php`, `tests/TestCase`, and `AppServiceProvider` during `runningUnitTests()`. There is no warning-only path.

---

## DEC-023

**Title:** Upload limits aligned to 200 MB  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** For `sales.owlsolutions.net` only: application max 200 MB, PHP-FPM `upload_max_filesize=200M` / `post_max_size=210M`, nginx `client_max_body_size 210M`.

**Reason:** nginx previously capped uploads at 64M while the product advertised 200 MB.

**Consequences:** Other vhosts keep their own limits. Global `nginx.conf` is unchanged.

---

## DEC-024

**Title:** ElevenLabs is initial STT provider  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** The first transcription provider is ElevenLabs, behind a `TranscriptionProvider` interface.

**Reason:** Batch Speech-to-Text with diarization and timestamps is available as Scribe v2, and the product needed a real STT integration before analysis.

**Consequences:** HTTP details stay in `ElevenLabsTranscriptionClient`. Jobs and controllers consume `TranscriptionResult` only. Phase 7.1 stores the key encrypted in `transcription_provider_settings`; `ELEVENLABS_API_KEY` remains an env fallback (DEC-053 / DEC-054). Never commit the key.

---

## DEC-025

**Title:** Scribe v2 is initial transcription model  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Use ElevenLabs model id `scribe_v2` for prerecorded / batch transcription.

**Reason:** Scribe v2 is the current batch STT model with diarization and word timestamps. Realtime Scribe is out of scope.

**Consequences:** Config default `ELEVENLABS_STT_MODEL=scribe_v2`. Endpoint `POST https://api.elevenlabs.io/v1/speech-to-text`.

---

## DEC-026

**Title:** Supported languages are EN/RU/UK  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Product languages are English, Russian, and Ukrainian (`en`, `ru`, `uk`). After STT, detected language is normalized and must be in that allowlist.

**Reason:** Those are the languages the product is built for.

**Consequences:** Other detected languages fail the call as unsupported. Public copy: `This language is not supported yet.`

---

## DEC-027

**Title:** Diarized transcript stored in normalized segments  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Persist a `transcripts` row per Call and ordered `transcript_segments` with 0-based speaker integers, start/end seconds, and text. UI shows `Speaker 1`, `Speaker 2`, …

**Reason:** Provider JSON must not leak through the app. Manager vs Client labeling is later (LLM), not this phase.

**Consequences:** `provider_metadata` stores only compact fields. Public JSON never includes it.

---

## DEC-028

**Title:** Transcription runs asynchronously  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** After upload, dispatch `TranscribeCall` on the Laravel database queue. Do not call STT inside the HTTP upload request.

**Reason:** Provider latency and retries would block uploads.

**Consequences:** Dedicated systemd worker `sales-worker.service`. Status stays `uploaded` until the job starts (`processing`).

---

## DEC-029

**Title:** `transcribed` is separate from full AI completion  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Successful STT sets Call status `transcribed`. `completed` is reserved for a later full AI analysis.

**Reason:** A transcript is not a sales analysis. Mixing them would hide the next pipeline stage.

**Consequences:** Public UI says transcription is complete and continues to analysis (`analysis_pending` / `analyzing` / `completed`). A transcript is still not a sales analysis.

---

## DEC-030

**Title:** Sales analysis has versioned structured schema  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Persist analyses in `sales_analyses` with `schema_version` starting at `1`. The structured report lives in JSON `result`; `overall_score`, `summary`, `provider`, and `model` are denormalized columns.

**Reason:** The report shape will evolve. Application code must not assume the current JSON is eternal.

**Consequences:** `SalesAnalysisResultValidator` rejects malformed output. Re-analysis replaces the row only after validation succeeds.

---

## DEC-031

**Title:** Generic sales analysis precedes company-specific context  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Phase 4 analyzes calls with generic sales methodology. Company documents, scripts, pricing rules, and RAG are out of scope. Company name may be metadata only.

**Reason:** Public uploads have no company. Pretending we know a company’s offer would hallucinate.

**Consequences:** Prompt forbids company-specific invention on public/generic calls. Phase 5 adds a separate company knowledge layer for calls that have a Company (DEC-036) without replacing this generic baseline.

---

## DEC-032

**Title:** AI analysis runs asynchronously  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `AnalyzeCall` runs on the Laravel queue after a successful transcript. Controllers only dispatch the job. STT uses status `processing`; AI uses `analyzing`. Missing AI settings yield `analysis_pending` without failing the call.

**Reason:** LLM latency and retries must not block HTTP. Transcription and analysis are different stages.

**Consequences:** `TranscribeCall` dispatches `AnalyzeCall`. Admin **Run analysis** / **Re-run analysis** also dispatch the job.

---

## DEC-033

**Title:** Evidence-backed findings are required  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Strengths, issues, and similar findings are objects with `text` plus optional short `quote`, `speaker`, and `timestamp_seconds`. The model must not invent events absent from the transcript. Inapplicable sections use `applicable: false`.

**Reason:** Coaching claims without evidence are not useful and are easy to hallucinate.

**Consequences:** Validator accepts finding objects (or simple strings, normalized). Public UI shows short quotes, not full transcripts.

---

## DEC-034

**Title:** Speaker roles are analysis metadata, not transcript mutation  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Transcript segments keep integer speakers. Analysis JSON may map them to `seller`, `customer`, `unknown`, or `other`. If the model is not confident, it must use `unknown`.

**Reason:** Diarization identity and sales-role labeling are different problems. Relabeling stored segments would destroy the STT record.

**Consequences:** Public transcript still shows Speaker 1 / Speaker 2. Admin analysis shows role mapping.

---

## DEC-035

**Title:** Live external-provider verification may be deferred during development  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Development phases may be accepted on mocked tests without live ElevenLabs or LLM API calls when the Project Manager defers those checks.

**Reason:** Missing keys must not stall application-layer work.

**Consequences:** REPORT records `Live external provider verification: deferred by Project Manager`. That is not a FAILED/BLOCKED phase by itself.

---

## DEC-036

**Title:** Company knowledge is a separate domain layer  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Store company-specific sales knowledge in dedicated tables (`company_profiles`, offerings, objections, scripts, scorecards). Do not add dozens of knowledge columns onto `companies`. Do not use JSON for naturally textual fields.

**Reason:** Company knowledge is a domain, not a company metadata dump. Generic analysis must keep working when `company_id` is null.

**Consequences:** `AnalysisContextBuilder` loads active knowledge for a Call’s Company. Generic analyzer calls (`company_id` null) skip this layer. RAG/embeddings remain later.

---

## DEC-037

**Title:** Company scorecards are configurable  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Companies may define one or more scorecards with weighted criteria. Analysis uses the default active scorecard. No fictitious scorecard is auto-created. Admin upload does not pick a scorecard in v1.

**Reason:** Companies score different behaviors. A universal scorecard would hide that.

**Consequences:** Missing scorecard means generic sections only. Weights need not sum to 100 in the database; the UI warns.

---

## DEC-038

**Title:** Weighted score is calculated application-side  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** The LLM returns per-criterion scores. Laravel computes `sum((score/max_score)*weight) / sum(applicable weights) * 100`. Non-applicable criteria are excluded. The LLM `total_score` is overwritten. Critical failure is a flag only; it does not force total=0.

**Reason:** Models are unreliable at weighted arithmetic. The snapshot is the source of weights.

**Consequences:** Generic `overall_score` and `company_scorecard_score` stay separate columns/UI values.

---

## DEC-039

**Title:** Analyses store immutable context snapshots  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Each `sales_analyses` row stores `company_context_hash`, `context_snapshot`, `scorecard_id`, and `scorecard_snapshot`. Editing company knowledge does not rewrite old analyses. Re-run analysis is manual and writes a new snapshot.

**Reason:** Coaching history must remain explainable against the rules that produced it.

**Consequences:** No knowledge version-control UI in v1. Snapshots are enough.

---

## DEC-040

**Title:** Public calls use generic analysis only  
**Status:** Superseded  
**Date:** 2026-09-10  
**Superseded by:** DEC-057

**Decision:** Anonymous public uploads keep `company_id` null, `company_context_used=false`, and generic methodology. The public form does not ask for company knowledge.

**Reason:** Guests have no company. Inventing one would hallucinate.

**Consequences:** Company-specific fields in schema v2 are empty/not applicable for public calls. Replaced by explicit Company selection on the main analyzer (DEC-057). Generic mode remains available when no Company is selected.

---

## DEC-041

**Title:** Transcript is untrusted prompt content  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Company knowledge is trusted admin-entered context. The transcript is untrusted. Prompts separate SYSTEM RULES, COMPANY CONTEXT, and CALL TRANSCRIPT, and tell the model to ignore instruction-like transcript text.

**Reason:** A caller (or a file) can say “ignore previous instructions.” That must not override system or company rules.

**Consequences:** Prompt-injection resistance is wording and structure, not a separate ML classifier.

---

## DEC-042

**Title:** Analytics are computed from source-of-truth call data  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Do not store daily aggregate tables in v1. Dashboard, company, and employee analytics query `calls` and `sales_analyses` and aggregate in a PHP service layer.

**Reason:** Volume is small. Premature materialization would duplicate scores that already live on analyses.

**Consequences:** Controllers stay thin (`AnalyticsFilter` + services). If volume grows, a later phase may add materialized aggregates.

---

## DEC-043

**Title:** Historical scorecard analytics use analysis snapshots  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Criterion averages, weights, and critical-failure counts come from each analysis `scorecard_snapshot` and `result.company_specific.scorecard`, not from the live scorecard rows.

**Reason:** Editing a scorecard tomorrow must not rewrite last month’s analytics.

**Consequences:** Live scorecard configuration is for new analyses only.

---

## DEC-044

**Title:** Public calls are excluded from employee/company analytics  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Generic analyzer calls (`company_id` null) never appear in employee or company analytics. The global dashboard may include them in overall totals and a Public Analyses card. Analyzer calls that selected a Company are company-bound and **do** appear in that company’s (and employee’s) analytics.

**Reason:** They have no employee and no company knowledge.

**Consequences:** Company filters drop public rows. Employee pages only load that employee’s calls.

---

## DEC-045

**Title:** Analytics date uses recorded_at with created_at fallback  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Every analytics query and chart uses `COALESCE(recorded_at, created_at)` in `config('app.timezone')`.

**Reason:** Upload time is not always call time. Mixing date columns on one screen would be misleading.

**Consequences:** Period presets and previous-period comparison share the same clock.

---

## DEC-046

**Title:** Generic score and company scorecard remain separate metrics  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Dashboard KPIs and charts never blend `overall_score` and `company_scorecard_score`. Missing scorecard values display as N/A, not 0.

**Reason:** They measure different rubrics (DEC-038). Mixing them would hide coaching signal.

**Consequences:** Two averages, two trend series.

---

## DEC-047

**Title:** Deep Call Analysis schema v3  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** New analyses write `schema_version = 3`. v1/v2 rows stay displayable. Analysis remains one structured LLM call; v3 adds executive, timeline, signals, coaching, and related blocks on top of v2.

**Reason:** The product is judged on a single-call report. A second LLM pass would duplicate the transcript and company context without a proven quality gain, while collection limits already bound payload size.

**Consequences:** Presenter must tolerate missing v3 keys. Validator requires v3 keys on new writes.

---

## DEC-048

**Title:** Analysis prioritizes evidence over generic coaching  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Prompts and UI forbid empty advice such as “build rapport” or “ask more questions” unless tied to a moment, a reason, and a replacement action.

**Reason:** Generic coaching is not useful to a sales manager.

**Consequences:** Recommendations, better phrases, and coaching priorities must cite transcript evidence.

---

## DEC-049

**Title:** Conversation metrics are calculated application-side  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Talk percentages, longest seller monologue, speaker switches, and duration come from transcript segments and `speaker_roles`. The LLM must not invent them. Interruptions are not counted without overlap data.

**Reason:** STT timestamps are more reliable than model guesses.

**Consequences:** Missing durations are `null`. Metrics are attached after validation.

---

## DEC-050

**Title:** Timeline contains only material call moments  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** `timeline` is capped at 15 items and should list turning points, objections, buying signals, and similar events — not every sentence.

**Reason:** A sentence-level timeline is unreadable.

**Consequences:** Validator slices modest overflow and rejects extreme arrays.

---

## DEC-051

**Title:** Coaching output is prioritized and actionable  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** `coaching_priorities` is ranked and capped at 5. `what_to_repeat` / `what_to_stop` / `what_to_start` and the next-call playbook are concrete actions for the next similar call, not a long-term HR plan.

**Reason:** Twenty equal tips are not coaching.

**Consequences:** Validator enforces the cap. No employee coaching-plan module in this phase.

---

## DEC-052

**Title:** Transcription provider settings are managed separately from LLM settings  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Store STT credentials and status in `transcription_provider_settings`, not in `ai_provider_settings`. Settings → Transcription configures ElevenLabs. Settings → AI remains the LLM layer.

**Reason:** Speech-to-text and chat-completion providers are different domains. Mixing keys would confuse operators and readiness checks.

**Consequences:** One ElevenLabs row is bootstrapped without a secret. A second STT vendor can be added later without a fake multi-select today.

---

## DEC-053

**Title:** Runtime provider credentials prefer database configuration  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Production runtime reads the active transcription (and existing LLM) credentials from the database first.

**Reason:** Operators must be able to configure the pipeline from admin without editing `.env` or restarting workers.

**Consequences:** `ActiveTranscriptionProvider` is the STT source of truth. `ElevenLabsTranscriptionClient` does not call `env()` for secrets.

---

## DEC-054

**Title:** Environment credentials are fallback configuration  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** If the DB STT key is empty, use `config('sales-analyzer.transcription.api_key')` (`ELEVENLABS_API_KEY`). If a DB key exists, ignore env. Admin UI shows configuration source: Database, Environment, or Not configured.

**Reason:** Existing env keys must keep working. Silent conflict between DB and env is worse than an explicit source label.

**Consequences:** Changing env still requires config cache clear / worker restart. Changing a DB key does not.

---

## DEC-055

**Title:** Pipeline readiness is exposed as one normalized health state  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** `AnalysisPipelineHealth` returns transcription, analysis, and `pipeline_ready`. The admin UI does not compute readiness in React. Public guests never see provider names or keys.

**Reason:** Upload and Run Analysis must not enter a known-broken path. Duplicating the rules in the frontend would drift.

**Consequences:** Public upload requires transcription ready. Missing LLM after STT remains `analysis_pending`.

---

## DEC-056

**Title:** Provider credentials are configurable without worker restart  
**Status:** Accepted  
**Date:** 2026-09-10

**Decision:** Database-stored keys and models are the runtime source of truth. Queue workers read them on each job. `config:cache` may freeze env fallbacks; it does not freeze DB credentials.

**Reason:** Restarting PHP-FPM or the queue worker just to rotate an API key is operational friction.

**Consequences:** Tests and production both resolve through the same provider services.

---

## DEC-057

**Title:** Main analyzer explicitly selects company context  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Main analyzer allows explicit Company and optional Employee selection before upload. Generic analysis remains a first-class explicit mode (DEC-063). The system never guesses Company from audio or transcript.

**Reason:** Sales Analyzer is an operator workspace, not an anonymous SaaS upload. Company knowledge already exists in Admin; the analyzer must be able to use it on demand. Guessing a company from audio would hallucinate context.

**Consequences:** `POST /analyze` accepts nullable `company_id` and `employee_id` with backend validation (active company, active employee belonging to that company, employee requires company). `source` stays `public`. Generic uploads keep `company_id` null and use `AnalysisContextBuilder::generic()`. Company uploads reuse existing Phase 5 company-specific analysis. Frontend receives only selector metadata (id, name, completeness %, scorecard name), never scripts, forbidden claims, or scorecard instructions. Empty select is not Generic; the operator must choose Generic or a Company (DEC-063).

---

## DEC-058

**Title:** Context character budgets are UTF-8 safe  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** `AnalysisContextBuilder` measures and truncates company context with `mb_strlen` / `mb_substr` in UTF-8. The default budget is 50,000 characters and remains configurable via `config/sales-analyzer.php`.

**Reason:** Real company knowledge is often Cyrillic or Ukrainian. Byte-based `strlen`/`substr` under-counted the budget and could split a character, breaking JSON encoding.

**Consequences:** Tests cover Cyrillic and Ukrainian truncation. Knowledge UI shows analysis context usage against the character budget. Tokenizer-specific packing is not used.

---

## DEC-059

**Title:** Critical score caps are applied application-side  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** After the weighted company score is calculated, active scorecard caps may lower the final score to `min(weighted_score, lowest_triggered_cap)`. The LLM does not compute caps. Trigger types are `criterion_critical_failure` and `forbidden_claim_violation`. Forbidden-claim caps fire only when analysis found a confirmed violation.

**Reason:** A critical factual error (for example an outdated event date) must not remain a high company score even if other criteria are strong.

**Consequences:** Cap rules are snapshotted with the scorecard. Stored analyses keep the caps that applied at write time. Re-run uses current cap rules. UI can show weighted score, final score, and the triggered cap name.

---

## DEC-060

**Title:** Verifiable company facts are authoritative analysis context  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Companies store `company_facts` (label, value, current/outdated, optional valid_until/source). Active facts are packed as a high-priority `VERIFIABLE COMPANY FACTS` block. CURRENT facts are authoritative; OUTDATED facts must not be presented as current.

**Reason:** Dates, cities, prices, and package names change. Free-text knowledge is easy to leave stale. The analyzer needs an explicit fact list to check claims against.

**Consequences:** Inactive facts are excluded. The model must not invent facts missing from supplied context. This is not a knowledge graph.

---

## DEC-061

**Title:** Company report language may differ from call language  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** `company_profiles.report_language` may be `same_as_call`, `en`, `ru`, or `uk`. Generic calls use global `analysis_settings.report_language_mode`. Company calls use the company setting. Evidence quotes stay in the original transcript language.

**Reason:** Operators may want a Russian coaching report of an English or Ukrainian call without translating the evidence.

**Consequences:** The prompt says to analyze the transcript as-is, write narrative in the report language, and never translate quotes.

---

## DEC-062

**Title:** Stage talk metrics are calculated application-side  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** After LLM analysis, `ConversationMetricsCalculator` intersects transcript segments, speaker roles, and `sales_stage_map` to compute per-stage seller/customer talk percent, duration, and switches. `discovery_talk_balance` is derived from the discovery stage. Missing or invalid stage bounds yield `null`, not a guessed ratio.

**Reason:** Talk balance during discovery is a coaching signal. The LLM already returns stage boundaries; the application can measure time without asking the model to invent percentages.

**Consequences:** Interruptions are not calculated. Invalid timestamps are skipped. One-pass analysis is unchanged.

---

## DEC-063

**Title:** Main Analyzer requires explicit analysis context selection before upload  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Main Analyzer requires explicit analysis context selection before upload. Generic remains a first-class explicit mode. The Company select starts with placeholder `Select analysis context`. The operator must choose either `No company — Generic analysis` or a specific Company. `company_id = null` is valid only after that explicit Generic choice. Analyze stays disabled until a context is selected.

**Reason:** An empty select previously meant Generic, so Generic was pre-selected and Analyze was enabled immediately. Context must be a conscious choice, not an accidental default.

**Consequences:** The UI uses a `generic` sentinel that is never sent as `company_id`. Backend `POST /analyze` still accepts omitted/null `company_id` as generic. Employee is disabled and null in Generic mode. Context summary shows only safe metadata (company name, knowledge %, scorecard name, optional employee name). `calls.source = public` is historical and now means Main Analyzer Workspace upload; consider renaming to `manual_analyzer` on a later suitable migration, not a dedicated one.

---

## DEC-064

**Title:** User cancellation is a first-class Call state  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** User cancellation is a first-class Call state. External provider requests may not be physically abortable, but cancelled calls never continue the internal pipeline. `POST /analysis/{public_token}/cancel` sets `status = cancelled` with `cancelled_at` and `cancelled_stage`. `TranscribeCall` and `AnalyzeCall` no-op when the Call is cancelled, including after a late provider response. Completed or failed Calls cannot be cancelled. Repeated cancel is idempotent.

**Reason:** Operators need to stop a long transcription or analysis without leaving the UI spinning, and without treating a manual stop as a pipeline failure.

**Consequences:** ElevenLabs and LLM HTTP calls may still finish on the provider side. A transcript received after cancel may be stored. A new analysis result is not written after cancel. Main Analyzer maps backend statuses to a staged progress UI without fake percentages. The transcript opens in a modal instead of filling the page.

---

## DEC-065

**Title:** Gemini structured output uses JSON Schema (`responseJsonSchema`)  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Gemini structured output uses JSON Schema wire format (`responseJsonSchema`) rather than legacy OpenAPI `responseSchema`. The client keeps `POST /v1beta/models/{model}:generateContent` with `generationConfig.responseMimeType = application/json`. Schema v3 is not reduced or converted to OpenAPI, and the domain schema stays provider-neutral. Gemini’s generateContent compiler cannot ingest the full nested v3 graph (duplicate `required`, nullable-union volume, nested complexity). The Gemini adapter therefore sends a shallow JSON Schema projection of v3 required keys/types as `responseJsonSchema`, attaches the complete schema in the prompt, and validates the result with `SalesAnalysisResultValidator`.

**Reason:** Schema v3 is JSON Schema (`additionalProperties`, nullable unions such as `["integer","null"]`). Sending it as legacy `responseSchema` produced Gemini HTTP 400 `INVALID_ARGUMENT`. OpenAI and Anthropic keep their own wrappers; Gemini adapts at the client boundary.

**Consequences:** Gemini errors are parsed from `error.message` and `error.status`, never from numeric `error.code` as the human-readable text. Public analyzer copy stays `Analysis failed. Please try again.` Provider internals stay in logs/admin debug only. High-value nested blocks are now modeled on the Gemini wire (DEC-071); remaining arrays stay a shallow `{text}` projection.

---

## DEC-066

**Title:** Logging failures must never prevent call state transitions  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Logging failures must never prevent call state transitions. `TranscribeCall` and `AnalyzeCall` persist `failed` (or leave `cancelled`/`completed` untouched) before attempting to write logs. Logger exceptions are swallowed. `config/logging.php` stack channel uses `ignore_exceptions => true`. Stuck `processing`/`analyzing` calls whose queue job is gone can be marked failed with `php artisan sales:recover-stuck-calls` (optional `--retry` / `--sync`; no cron auto-retry).

**Reason:** A `storage/logs` permission error previously blocked `markFailed`, leaving Call #4 in `analyzing` with an empty queue after Gemini HTTP 400.

**Consequences:** Production `storage/logs` is owned `deploy:www-data` with ACL `user:www-data:rwx` (file `user:www-data:rw`), not mode 777. Worker PHP changes still require `sales-worker.service` recycle (`--max-time=3600` or `sudo systemctl restart sales-worker.service`).

---

## DEC-067

**Title:** Short report is default Main Analyzer output  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Short report is default Main Analyzer output.

**Reason:** The completed live report was too long to use as the first screen. Operators need scores, the executive summary, and a few next actions before the deep blocks.

**Consequences:** After `completed`, `/` shows `ShortAnalysisReport`. The full deep report is not rendered on the Main Analyzer home page.

---

## DEC-068

**Title:** Full report is a separate read-only view of the same analysis result  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Full report is a separate read-only view of the same analysis result.

**Reason:** Opening the deep report in a new tab keeps the short view fast without storing a second analysis or starting a new AI job.

**Consequences:** `GET /analysis/{public_token}/full` is an Inertia page that reads the existing `sales_analyses` row through `SalesAnalysisPresenter`. Admin Call detail defaults to the full report. No second analysis is created.

---

## DEC-069

**Title:** Empty report sections are omitted from UI  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Empty report sections are omitted from UI.

**Reason:** Empty headings and empty cards looked like missing analysis rather than “nothing to show for this call.”

**Consequences:** After normalization, sections with no valid items are not rendered. Malformed items are skipped instead of becoming empty cards.

---

## DEC-070

**Title:** Main Analyzer report language follows selected UI locale  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Main Analyzer report language follows selected UI locale.

**Reason:** Operators review in the interface language even when the call is in another language. Reading locale later from the worker session/browser is racy and wrong.

**Consequences:** `POST /analyze` stores `calls.ui_locale` (`en` / `ru` / `uk`). `AnalysisContextBuilder` uses that stored locale ahead of company `report_language` and transcript language. Quotes stay in the transcript language. Company `report_language` remains for admin/manual analysis when `ui_locale` is null.

---

## DEC-071

**Title:** Gemini wire schema explicitly models high-value nested analysis blocks  
**Status:** Accepted  
**Date:** 2026-09-11

**Decision:** Gemini wire schema explicitly models high-value nested analysis blocks.

**Reason:** The previous shallow `{text}` array item schema caused Gemini to omit `mistake`, `original`/`better`, `skill`, scorecard `key`/`score`, and similar fields. The validator then filled empty cards and N/A criteria.

**Consequences:** `GeminiClient::forGeminiWire()` describes slim nested items for `critical_mistakes`, `better_phrases`, `coaching_priorities`, and `company_specific.scorecard.criteria`. Full nested `missed_signals` and `timeline` together with those blocks exceed Gemini’s generateContent compiler budget, so they stay a `{text}` projection with `text` mapped to `signal`/`title`. Other arrays stay simplified. The full v3 graph is still not sent as `responseJsonSchema`. If a majority of expected scorecard keys are missing, company analysis fails rather than silently scoring N/A.

---

## Template for new entries

```
## DEC-072

**Title:**  
**Status:** Open | Accepted  
**Date:** YYYY-MM-DD

**Decision:**

**Reason:**

**Consequences:**
```
