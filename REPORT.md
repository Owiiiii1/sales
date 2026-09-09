# Sales Analyzer — Documentation Bootstrap Report

## Created documentation

| Path | Role |
|---|---|
| `docs/PROJECT.md` | Product name, idea, value, current vs intended |
| `docs/ARCHITECTURE.md` | Running stack, Laravel-first orchestration, AI providers Open |
| `docs/PRODUCT.md` | MVP Upload Call, Phase 2 accounts, later capabilities (non-final) |
| `docs/AI_ANALYSIS.md` | Pipeline, categories, multi-pass LLM calls, knowledge, scorecards |
| `docs/DATA_MODEL.md` | Planned User/Company/Employee/Call/Transcript/Analysis/Scorecard; kit tables unchanged |
| `docs/ROADMAP.md` | Phase 0 COMPLETED through Phase 9; working roadmap |
| `docs/DECISIONS.md` | DEC-001 … DEC-009 |
| `docs/STATUS.md` | Factual infrastructure and stock admin UI |
| `docs/WORKFLOW.md` | TL / Cursor / PM cycle, GitHub as source of truth |

## Updated files

* `README.md` — replaced Laravel marketing README with a compact project entry point
* `REPORT.md` — this stage report (overwritten)

No other project files were intended to change.

## Product scope captured

* Analyze sales phone calls, not generic transcription
* MVP: simple Upload Call + optional company context + analysis page; no required personal cabinet
* Company context required for quality analysis
* Scorecards are company-specific, not one universal score
* LLM + structured prompts for MVP; no custom trained model
* Later: accounts, knowledge/RAG, scorecard builder, team analytics, CRM/telephony ingest (illustrative, not committed)

## Architecture

**Fixed as current fact:** Laravel 13, MySQL 8, Inertia/React/Vite, Custom Admin Kit v0.5.0, nginx, Ubuntu 24.04, PHP 8.5, domain `sales.owlsolutions.net`.

**Accepted direction:** orchestrate in Laravel; queues for heavy audio; no premature microservices.

**Left TBD / Open:** LLM provider, STT/diarization provider, embeddings, vector store, queue driver for production audio, object storage for files, transcript vs JSON storage, public vs accounts-first order.

## Decisions

| ID | Title | Status |
|---|---|---|
| DEC-001 | Laravel backend | Accepted |
| DEC-002 | Custom Admin Kit, not Filament | Accepted |
| DEC-003 | MySQL as current RDBMS | Accepted |
| DEC-004 | No custom trained model in MVP | Accepted |
| DEC-005 | Company context required | Accepted |
| DEC-006 | LLM provider | Open |
| DEC-007 | STT provider | Open |
| DEC-008 | Avoid premature microservices | Accepted |
| DEC-009 | GitHub + `готово` workflow | Accepted |

## Functional changes

`None`

## Git

* branch: `main`
* commit SHA: pending
* message: pending
* push result: pending

## Verification

* application code unchanged (no `app/`, `routes/`, `resources/`, `database/migrations/`, `config/` edits in this stage)
* routes unchanged
* migrations unchanged
* package versions unchanged (`composer.json` / `package.json` not modified)
* no secrets added
* `git status` / `git diff` limited to `README.md`, `REPORT.md`, `docs/*`

## Final status

`DOCUMENTATION PASSED`
