# Sales Analyzer — Data Model

This document describes the **intended domain model**.

It is **not** implemented. **Do not create migrations in this phase.**

## What exists in the database today

MySQL database `sales` currently has **admin kit + Laravel** tables, including:

* `users`, `sessions`, `cache`, `jobs`, …
* `customers`, `orders`, `services`, `staff`, `order_staff`
* `ai_provider_settings`, `telegram_bot_settings`

Those CRM tables are **kit starter modules**. They are **not** Sales Analyzer `Company` / `Employee` / `Call`.

Do **not** delete kit tables in this documentation phase. Whether they are later removed, ignored, or mapped is **Open question** (Phase 1).

## Conceptual model (planned)

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
  ├── Transcript          [table vs JSON TBD]
  ├── Speaker Segment     [storage TBD]
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

Organization / tenant whose calls are analyzed and whose knowledge/scorecard apply.

Planned ideas (fields not final):

* name
* owner / billing user **TBD**
* default scorecard
* locale **TBD**

MVP may not persist Company as a first-class tenant (context pasted into a form). Phase 2 expects stored companies.

## Employee

A sales manager or other person **whose calls are analyzed**.

Not the same as User.

* An employee does **not** necessarily have a login.
* Link to User is optional. **TBD.**
* Fields (preliminary): name, role/title, company_id, external ids **TBD**.

Do not assume kit `staff` is this entity.

## Call

An uploaded or imported conversation.

Preliminary fields:

* `company_id`
* `employee_id` (nullable if unknown)
* `source` (upload, telephony, CRM, …)
* `original_filename`
* `storage_path`
* `mime_type`
* `duration`
* `status`
* `recorded_at`
* `uploaded_by` (User, nullable for public MVP)
* processing timestamps (`transcribed_at`, `analyzed_at`, `failed_at`, …)

Also likely: error message, provider job ids. **TBD.**

**No migration now.**

## Transcript

Result of transcription.

Options:

1. dedicated `transcripts` table;
2. JSON (or files) on the Call / a processing payload table.

**Decision TBD.**

If stored, keep provider, model/version, language, and raw response metadata.

## Speaker Segment

Preliminary shape:

* speaker (label or id)
* start time
* end time
* text
* confidence

Storage **TBD** (rows vs JSON array vs file).

Speaker identity mapping (which label is the manager) is a separate concern. **TBD.**

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
| Call | Transcript | 1:1 or 1:N if re-transcribed. **TBD** |
| Call | Analysis | 1:N (versions) |
| Scorecard | Criterion | 1:N |
| Analysis | Criterion Result | 1:N |
| Criterion Result | Criterion | Logical link; snapshot recommended |

## What we will not do in this phase

* no new migrations
* no Eloquent models for the domain
* no deletion of kit tables
* no assuming `staff` = Employee or `customers` = clients on calls

## Open questions

* Multi-company users vs single-tenant companies.
* Public MVP: persist Call without User?
* Audio blob in object storage vs local disk.
* Transcript and segments as JSON vs normalized tables.
* Soft deletes and retention.
