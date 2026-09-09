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

**Status: Planned**

Phase 1 already created Company / Employee / Call records and admin CRUD.

Remaining core-domain work:

* audio upload
* file storage
* richer call statuses / processing timestamps in the UI
* public or operator upload flow

## Phase 3 — Transcription

**Status: Planned**

* provider selection (decision still Open);
* audio processing;
* transcription;
* diarization;
* transcript storage.

## Phase 4 — AI Sales Analysis

**Status: Planned**

* first scoring engine (LLM + structured prompts, no custom trained model);
* analysis sections;
* structured output;
* report UI.

Provider still Open until DEC-006 / DEC-007 close.

## Phase 5 — Company Context

**Status: Planned**

* company profile;
* knowledge base;
* scripts;
* products;
* objections;
* RAG / retrieval.

MVP may use a simpler context form before this phase.

## Phase 6 — Scorecards

**Status: Planned**

* methodologies;
* custom criteria;
* weights;
* scorecard versioning.

## Phase 7 — User Product

**Status: Planned**

* public upload flow;
* accounts;
* personal cabinet;
* history.

**The order between public upload and accounts may change.** A very small public Upload Call might ship before full accounts (see [PRODUCT.md](PRODUCT.md) Phase 1).

## Phase 8 — Management Analytics

**Status: Planned**

* employee dashboard;
* team analytics;
* trends;
* recurring weaknesses;
* comparisons.

## Phase 9 — Integrations

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
