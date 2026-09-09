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

**Decision:** `calls.company_id` is nullable. Anonymous/public uploads store `company_id = null`, `employee_id = null`, `uploaded_by = null`. Admin uploads still require a Company.

**Reason:** Public users are not tenants. Inventing a dummy “Public” company would pollute the admin company list.

**Consequences:** Public audio is stored under `public/{year}/{month}/` on the private disk. Admin UI still requires company on create.

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

**Consequences:** HTTP details stay in `ElevenLabsTranscriptionClient`. Jobs and controllers consume `TranscriptionResult` only. The API key is `ELEVENLABS_API_KEY` in the environment, never Git.

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

**Consequences:** Prompt forbids company-specific invention. A later phase adds company knowledge.

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

## Template for new entries

```
## DEC-036

**Title:**  
**Status:** Open | Accepted  
**Date:** YYYY-MM-DD

**Decision:**

**Reason:**

**Consequences:**
```
