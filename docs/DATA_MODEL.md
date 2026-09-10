# Sales Analyzer — Data Model

This document describes the **domain model**.

Phase 1 implemented `companies`, `employees`, and `calls`. Phase 3 added transcripts. Phase 4 added `sales_analyses`. Phase 5 added company knowledge and configurable scorecards. Phase 6 computes analytics from those tables; there is no `analytics_daily` (DEC-042). Phase 7.1 added `transcription_provider_settings` and `analysis_settings`.

**Do not** treat kit CRM tables as Sales Analyzer domain.

## What exists in the database today

MySQL database `sales` has Laravel + admin kit tables **and** Phase 1 domain tables:

* `users`, `sessions`, `cache`, `jobs`, …
* **legacy kit CRM:** `customers`, `orders`, `services`, `staff`, `order_staff` (DEC-012 — retained, hidden from nav)
* `ai_provider_settings`, `telegram_bot_settings`, `transcription_provider_settings`, `analysis_settings`
* **Sales Analyzer:** `companies`, `employees`, `calls`, `transcripts`, `transcript_segments`, `sales_analyses`, `company_profiles`, `company_offerings`, `company_objections`, `company_sales_scripts`, `company_scorecards`, `company_scorecard_criteria`

## Implemented domain (Phase 1)

```
User
  └── uploaded Calls (nullable uploaded_by)

Company
  ├── Employee
  ├── Call
  ├── CompanyProfile (1:1)
  ├── CompanyOffering
  ├── CompanyObjection
  ├── CompanySalesScript
  └── CompanyScorecard
        └── CompanyScorecardCriterion

Employee
  └── Call
```

Delete rules:

* Company cannot be deleted while it has employees or calls (`restrictOnDelete` + application check).
* Employee cannot be deleted while it has calls.
* `uploaded_by` is nullified if the User is deleted.

## Conceptual model (later)

```
User
  └── (member of) Company          [cardinality TBD]

Company
  ├── Employee
  ├── Call
  ├── Company Knowledge (several possible tables)
  └── Scorecard

Employee
  └── Call

Call
  ├── Transcript          [implemented]
  ├── Speaker Segment     [transcript_segments]
  └── Analysis (versioned)
        └── Analysis Criterion Result
              └── Scorecard Criterion
                    └── Scorecard
```

Cardinalities below are **preliminary**.

## User

A person who can **log in**.

* Kit already has `users` (name, email, password, …).
* Product roles (owner, manager, viewer) are **TBD**.
* A user may belong to one company or many. **Open question.**

Do not assume Employee = User.

## Company

Organization / tenant whose calls are analyzed.

**Implemented table `companies`:**

* id
* name (required)
* legal_name nullable
* website nullable
* industry nullable
* description nullable
* country, city, phone, email nullable
* is_active boolean default true
* timestamps

No slug in Phase 1.

Relations: `hasMany` employees, `hasMany` calls.

## Employee

A sales manager or other person **whose calls are analyzed**. Not a User.

**Implemented table `employees`:**

* id
* company_id → companies.id (restrict on delete)
* first_name (required)
* last_name, email, phone, position, external_id nullable
* is_active boolean default true
* timestamps

Indexes: company_id (FK), is_active, external_id.

Relations: `belongsTo` company, `hasMany` calls.

## Call

An uploaded or imported conversation.

**Implemented table `calls`:**

* id
* public_token unique UUID (DEC-019) — public status/report identifier
* company_id → companies nullable (DEC-018); **required** for admin uploads, **null** for public uploads
* employee_id → employees nullable (restrict)
* source string (`manual` for admin, `public` for anonymous upload)
* external_id nullable
* original_filename — client name only; not used as the physical filename
* storage_path — relative path on the private `calls` disk
* mime_type, file_size nullable
* duration_seconds nullable (ffprobe is not installed; WAV headers may be parsed)
* status string: `pending` → `uploaded` → `processing` (STT) → `transcribed` → `analysis_pending` or `analyzing` → `completed`. `failed` on error.
* recorded_at nullable
* uploaded_by → users nullable (`nullOnDelete`); null for public uploads
* processing_started_at, processing_completed_at, error_message nullable
* timestamps

Physical files live on disk `calls` (`storage/app/private/calls`), never under `public/`. Admin stream/download remain authenticated. There is **no** public audio URL (DEC-020). Deleting a Call deletes its file after the DB row is removed (DEC-016). Transcripts cascade-delete with the Call.

A call’s employee must belong to the selected company when both are present. Public JSON never includes `storage_path` or internal ids.

## Transcript

**Implemented tables `transcripts` and `transcript_segments` (DEC-027).**

`transcripts`:

* id
* call_id unique → calls (cascade)
* provider (`elevenlabs`)
* model (`scribe_v2`)
* language nullable (`en` / `ru` / `uk`)
* raw_text
* duration_seconds nullable
* confidence nullable
* provider_request_id nullable
* provider_metadata json (compact: request id, detected language, model, speaker count, duration, language probability)
* started_at, completed_at
* timestamps

`transcript_segments`:

* id
* transcript_id → transcripts (cascade)
* speaker unsigned int (0-based)
* start_seconds, end_seconds
* text
* confidence nullable
* sequence
* timestamps
* indexes: transcript_id, sequence, speaker

Call hasOne Transcript. Transcript hasMany segments ordered by sequence. UI label is `Speaker N` (N = speaker + 1). Manager vs Client is **not** stored on the transcript (DEC-034).

## Sales Analysis

**Implemented table `sales_analyses` (DEC-030). New writes use schema v3 (Phase 7). v1/v2 rows remain presentable.**

* id
* call_id unique → calls (cascade)
* provider, model nullable
* schema_version (new writes are `3`; v1/v2 rows remain valid to display)
* overall_score nullable 0–100 (generic sales methodology)
* company_scorecard_score nullable 0–100 (application-weighted; not a substitute for overall_score)
* summary
* result JSON (source of truth: v2 company_specific plus v3 deep-coaching blocks; `conversation_metrics` is application-filled)
* started_at, completed_at, error_message
* company_context_hash nullable
* scorecard_id nullable → company_scorecards (nullOnDelete)
* scorecard_snapshot JSON nullable
* context_snapshot JSON nullable
* timestamps

Call hasOne SalesAnalysis. Re-analysis replaces the row only after a validated provider response. Knowledge edits do not rewrite an existing analysis (DEC-039).

Speaker roles (`seller` / `customer` / `unknown` / `other`) live in `result.speaker_roles`, not on transcript segments.

## Transcription provider settings

**Implemented table `transcription_provider_settings` (DEC-052).** Separate from `ai_provider_settings`.

* id
* provider unique (`elevenlabs`)
* label (`ElevenLabs`)
* api_key encrypted nullable (Laravel encrypted cast; hidden on serialization)
* is_connected, is_active
* active_model nullable (default `scribe_v2`)
* available_models json nullable (application-side list)
* last_checked_at, last_error nullable
* settings json nullable
* timestamps

Bootstrap inserts the ElevenLabs row without a secret. Runtime resolution: non-empty DB key → `config('sales-analyzer.transcription.api_key')` env fallback → not configured (DEC-053 / DEC-054).

## Analysis settings

**Implemented table `analysis_settings`.** Product-level, not per LLM provider.

* id
* report_language_mode default `same_as_call`
* max_output_tokens unsigned int default 16384
* timestamps

Schema version remains informational (`config('sales-analyzer.analysis.schema_version')` = 3). Temperature is not stored.

## Speaker Segment

Implemented as `transcript_segments` rows (see Transcript). Speaker identity mapping (which label is the manager) is **not** stored in Phase 3.

## Analysis

Result of analyzing one call. **Must support versioning** (`schema_version`).

Implemented as `sales_analyses` with JSON `result` as the structured report. Generic methodology scores stay in `overall_score`. Company scorecard totals are `company_scorecard_score` (DEC-038). Public calls keep `company_context_used=false` (DEC-040).

A call may be re-analyzed when prompts, models, scorecards, or company context change.

Preliminary metadata:

* `call_id`
* `model`
* `provider`
* `prompt_version`
* `methodology_version`
* `scorecard_id` / `scorecard_version`
* `company_context_version`
* structured result JSON
* overall score
* created_at
* status (complete / failed / partial) **TBD**

Keep the synthesis JSON even if criterion rows exist, so the full report can be rebuilt.

## Analysis Criterion Result

Per-criterion outcome for one Analysis:

* analysis_id
* criterion_id (and/or snapshot of name/weight if the scorecard changed)
* score
* max_score
* comments / evidence quotes **TBD**
* critical failure flag **TBD**

## Scorecard

**Implemented tables `company_scorecards` and `company_scorecard_criteria` (DEC-037).**

* id
* company_id → companies (cascade)
* name
* description nullable
* is_default
* is_active
* timestamps

Criteria:

* id
* scorecard_id → company_scorecards (cascade)
* key (unique per scorecard)
* name
* description nullable
* weight decimal (sum need not be 100 in the database)
* max_score integer default 100
* is_critical default false
* ai_instructions nullable
* sequence
* is_active
* timestamps

No fictitious scorecard is created for a company. Analysis uses the company’s default active scorecard, or the first active scorecard if none is marked default. Weights are not required to sum to 100; admin UI shows Total weight and warns when the sum is not 100.

Per-analysis criterion results live in `result.company_specific.scorecard.criteria`. The weighted total is calculated in Laravel from the scorecard snapshot (DEC-038). Critical failure is stored as a flag and does not force total=0.

## Company Knowledge

**Implemented (DEC-036). Not RAG / embeddings.**

`company_profiles` (one-to-one with Company):

* short_description, sales_context, target_audience, ideal_customer_profile, value_proposition, usp, pricing_context, competitors, customer_pains, sales_goals, desired_next_steps, forbidden_claims, mandatory_questions, notes

`company_offerings`: type `product` | `service` | `other`, name, description, target_customer, value_proposition, pricing, differentiators, common_use_cases, is_active.

`company_objections`: objection, recommended_response, notes, priority, is_active.

`company_sales_scripts`: name, description, script_text, is_active. Multiple active scripts may be concatenated into analysis context.

Admin-entered knowledge is trusted context. Transcripts are untrusted prompt content (DEC-041). Each analysis stores an immutable `context_snapshot` / `company_context_hash` (DEC-039). Changing knowledge does not re-analyze old calls.

Documents, embeddings, and a vector store remain later / TBD.

## Relationships (summary)

| From | To | Notes |
|---|---|---|
| Company | Employee | One company, many employees |
| Company | Call | Direct, even if employee missing |
| Employee | Call | Many calls per employee |
| Call | Transcript | 1:1 (`call_id` unique). Retranscribe replaces the row after provider success. |
| Company | CompanyProfile | 1:1 |
| Company | CompanyOffering / Objection / SalesScript / Scorecard | 1:N, cascade |
| Scorecard | Criterion | 1:N, unique key per scorecard |
| Call | SalesAnalysis | 1:1 (`call_id` unique). Re-analysis replaces the row after validation. Snapshots stay with that row. |
| SalesAnalysis | Scorecard | nullable FK; snapshot is authoritative for that analysis |

## Analytics (computed, no extra tables)

Admin dashboard / company / employee analytics read `calls` + `sales_analyses` (DEC-042).

* Date: `recorded_at` if set, else `created_at` (DEC-045), grouped in `config('app.timezone')`.
* Generic average uses `overall_score`. Company average uses `company_scorecard_score` (DEC-046). Nulls are N/A, never a fake 0.
* Scorecard history uses `scorecard_snapshot` + `result.company_specific.scorecard` (DEC-043), not the live scorecard rows.
* Public calls (`company_id` null) are excluded from employee and company analytics (DEC-044). They may appear on the global dashboard, with a separate Public Analyses count.

## What we will not do yet

* no deletion of kit CRM tables (DEC-012)
* no assuming `staff` = Employee or `customers` = Company
* no company document store / embeddings / vector RAG

## Open questions

* Multi-company users vs single-tenant companies.
* Public MVP: persist Call without User?
* Audio blob in object storage vs local disk.
* Transcript JSON vs tables: decided — normalized tables (DEC-027).
* Soft deletes and retention.
