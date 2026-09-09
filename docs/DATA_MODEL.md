# Sales Analyzer — Data Model

This document describes the **domain model**.

Phase 1 implemented `companies`, `employees`, and `calls`. Transcript, Analysis, Scorecard, and knowledge tables are still **Planned**.

**Do not** treat kit CRM tables as Sales Analyzer domain.

## What exists in the database today

MySQL database `sales` has Laravel + admin kit tables **and** Phase 1 domain tables:

* `users`, `sessions`, `cache`, `jobs`, …
* **legacy kit CRM:** `customers`, `orders`, `services`, `staff`, `order_staff` (DEC-012 — retained, hidden from nav)
* `ai_provider_settings`, `telegram_bot_settings`
* **Sales Analyzer:** `companies`, `employees`, `calls`

## Implemented domain (Phase 1)

```
User
  └── uploaded Calls (nullable uploaded_by)

Company
  ├── Employee
  └── Call

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
* status string: `pending` → `uploaded` → `processing` → `transcribed` (DEC-029). `completed` is reserved for later AI analysis. `failed` on error.
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

Call hasOne Transcript. Transcript hasMany segments ordered by sequence. UI label is `Speaker N` (N = speaker + 1). Manager vs Client is **not** stored.

## Speaker Segment

Implemented as `transcript_segments` rows (see Transcript). Speaker identity mapping (which label is the manager) is **not** stored in Phase 3.

## Analysis

Result of analyzing one call. **Must support versioning.**

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

A named scoring scheme for a company or a built-in template.

* name
* owner company (nullable for global templates) **TBD**
* version
* description

Companies choose or create scorecards. There is no single universal score.

## Scorecard Criterion

* scorecard_id
* key / name
* weight
* max score
* description
* critical flag
* instructions for AI
* sort order

Built-in examples (generic sales, service, B2B) live in [AI_ANALYSIS.md](AI_ANALYSIS.md) as illustrations only.

## Company Knowledge

Future storage may include one or more of:

* knowledge documents (files / chunks for RAG)
* products
* services
* scripts
* objections
* competitors
* sales rules (mandatory / forbidden)

**Final normalization TBD.** MVP may use a single context text field.

Versioning of knowledge used in an analysis should be traceable (`company_context_version` on Analysis).

## Relationships (summary)

| From | To | Notes |
|---|---|---|
| Company | Employee | One company, many employees |
| Company | Call | Direct, even if employee missing |
| Employee | Call | Many calls per employee |
| Call | Transcript | 1:1 (`call_id` unique). Retranscribe replaces the row after provider success. |
| Call | Analysis | 1:N (versions) |
| Scorecard | Criterion | 1:N |
| Analysis | Criterion Result | 1:N |
| Criterion Result | Criterion | Logical link; snapshot recommended |

## What we will not do yet

* no deletion of kit CRM tables (DEC-012)
* no assuming `staff` = Employee or `customers` = Company
* no analysis / scorecard tables in this phase

## Open questions

* Multi-company users vs single-tenant companies.
* Public MVP: persist Call without User?
* Audio blob in object storage vs local disk.
* Transcript JSON vs tables: decided — normalized tables (DEC-027).
* Soft deletes and retention.
