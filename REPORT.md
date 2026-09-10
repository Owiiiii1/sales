# Sales Analyzer — Phase 5 Company Knowledge & Scorecards Report

## Baseline

* accepted baseline SHA (Phase 4 on `main`): `555029d3ef9e6fb09a1023181f2de50332b7f49d`
* HEAD before work: `555029d3ef9e6fb09a1023181f2de50332b7f49d`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none

Production database backup (outside Git): `/home/deploy/backups/sales/sales-pre-phase5-20260910-081604.sql`

Phase 4 live ElevenLabs / LLM verification remains deferred by the Project Manager and did **not** block this phase.

## Company Knowledge Model

Company knowledge is a separate domain layer (DEC-036). `companies` was not expanded into a wide table.

`company_profiles` is one-to-one with `Company` (`company_id` unique). Fields are nullable text / longText: short description, sales context, target audience, ICP, value proposition, USP, pricing context, competitors, customer pains, sales goals, desired next steps, forbidden claims, mandatory questions, notes.

Admin enters business information. There is no `Prompt` field. `AnalysisContextBuilder` turns the stored text into LLM context.

## Offerings

`company_offerings` stores knowledge for analysis, not an inventory catalog.

* `type`: `product` | `service` | `other`
* name, description, target customer, value proposition, pricing, differentiators, common use cases
* `is_active`

Inactive offerings are ignored by the context builder.

## Objections

`company_objections` stores expected handling for remarks such as “too expensive”.

* objection
* recommended_response
* notes, priority
* `is_active`

## Scripts

`company_sales_scripts` allows several scripts per company. Multiple active scripts may be concatenated into analysis context. There is no exclusive primary-script constraint.

## Scorecards

`company_scorecards` + `company_scorecard_criteria` (DEC-037).

* scorecard: name, description, `is_default`, `is_active`
* criterion: `key`, name, description, weight, `max_score` (default 100), `is_critical`, `ai_instructions`, sequence, `is_active`
* unique `(scorecard_id, key)`

No fictitious company scorecard is created automatically. Analysis uses the default active scorecard, or the first active scorecard if none is marked default. Admin upload does not pick a scorecard; the company’s default is used.

Weights need not sum to 100 in the database. Admin UI shows **Total weight** and a warning when the sum is not 100. Save is not blocked.

## Context Builder

`AnalysisContextBuilder` receives a Call.

* `company_id` null → generic context only (`company_context_used=false`).
* Company present → basic company data, profile, active offerings, active objections, active scripts, default/first active scorecard + active criteria. `company_context_used=true` even if knowledge is empty.

The job does not assemble this itself. `AnalyzeCall` calls the builder, then the provider.

## Context Budget

Config: `sales-analyzer.analysis.context_budget_characters` (default **24,000**, minimum 1,000).

Packing priority:

1. scorecard
2. mandatory questions
3. forbidden claims
4. sales script
5. offerings
6. objections
7. core profile
8. competitors / notes

Overflow is truncated, a warning is logged, and analysis continues.

## Schema v2

New writes use `schema_version = 2`. Existing v1 JSON remains displayable. Generic and company-aware calls both persist v2. Generic payloads may omit `company_specific`; the validator fills an empty structure.

Added result fields:

* `company_context_used`
* `company_specific.script_adherence`
* `mandatory_questions` (`asked` / `missed`)
* `forbidden_claims.violations`
* `objection_handling.matched`
* `offering_accuracy.issues`
* `scorecard.criteria` + application-side `total_score`

Generic `overall_score` is not replaced by the company scorecard.

## Score Calculation

LLM returns per-criterion evaluation (`key`, `score`, `max_score`, `applicable`, `summary`, `evidence`, `critical_failure`).

Laravel (`CompanyScoreCalculator`) computes:

`normalized = score / max_score`

`weighted = normalized * weight`

`total = round((sum weighted / sum applicable weights) * 100)`

Non-applicable criteria are excluded. Unknown or duplicate criterion keys are rejected. Missing snapshot keys are filled as `applicable=false`. Critical failure is a flag only; it does not force total=0.

UI shows **Overall Sales Score** and **Company Scorecard** separately.

## Context Snapshot

`sales_analyses` additive columns:

* `company_context_hash`
* `scorecard_id` (nullable, nullOnDelete)
* `company_scorecard_score`
* `scorecard_snapshot` JSON
* `context_snapshot` JSON

Editing company knowledge does not rewrite old analyses (DEC-039). Admin **Re-run analysis** writes a new row/snapshot from the current knowledge.

## Prompt Security

Prompt sections are explicit (DEC-041):

* SYSTEM RULES
* GENERIC SALES METHODOLOGY
* COMPANY-SPECIFIC INSTRUCTIONS (when a Company is attached)
* COMPANY CONTEXT (trusted admin text)
* CALL TRANSCRIPT (untrusted)

The model is told not to treat transcript text as instructions, including “ignore previous instructions”. Company rules override conflicting generic advice. Forbidden claims, mandatory questions, script adherence, objection handling, and offering accuracy are evaluated when context exists.

## Admin UI

`GET /companies/{company}` is a tabbed company detail page:

* Overview (identity, contacts, knowledge completeness checklist)
* Knowledge (business-language labels; no Prompt field)
* Offerings / Objections / Scripts / Scorecard CRUD
* Employees
* Calls (recent)

Companies index has **View**. Scorecard UI shows Total weight and a not-100 warning. Completeness is a simple 6-item percent (target audience, USP, pains, active offering, active script, active scorecard with criteria).

Call detail shows Analysis context: company, scorecard name, schema version, company context used yes/no. Context snapshot is a collapsed admin debug block. Raw system prompt is not shown.

## Analysis Integration

Pipeline is unchanged: `TranscribeCall` → `AnalyzeCall`.

`AnalyzeCall` → `AnalysisContextBuilder` → provider → `SalesAnalysisResultValidator` (with context) → `SalesAnalysisWriter`.

Admin-created calls already select a Company; that Company now determines knowledge context.

## Public Calls

Public anonymous uploads keep `company_id` null. Analysis is generic: `company_context_used=false`. The public form does not ask for company knowledge (DEC-040).

## Tests

`php artisan test`: **115 passed**, 652 assertions.

Coverage includes:

* profile create/update and one-to-one unique `company_id`
* offerings / objections / scripts CRUD and company isolation
* scorecards, criteria, weights, default, active/inactive
* context builder: public generic only; company knowledge; inactive ignored; budget truncation
* analysis: company context included; generic unaffected; unknown criteria rejected; weighted score application-side; non-applicable excluded; snapshots saved; knowledge edits do not mutate old analysis; rerun uses a new snapshot
* UI: company detail tabs, knowledge/scorecard props, call analysis context metadata

Existing Phase 1–4 tests remained green.

## Live Provider Verification

`Deferred by Project Manager`

No live ElevenLabs or LLM call was required or attempted for this phase.

## Production Sentinel

Backup taken before migrations: `/home/deploy/backups/sales/sales-pre-phase5-20260910-081604.sql`

Recorded on production connection `database=sales` **before** migrate:

| Metric | Before |
|---|---|
| users | 1 |
| companies | 0 |
| employees | 0 |
| calls | 0 |
| transcripts | 0 |
| transcript_segments | 0 |
| sales_analyses | 0 |
| jobs | 0 |
| admin | id `1`, `admin@admin.com` |

After `php artisan migrate --force` and `php artisan test`:

| Metric | After |
|---|---|
| users | 1 |
| companies | 0 |
| employees | 0 |
| calls | 0 |
| transcripts | 0 |
| transcript_segments | 0 |
| sales_analyses | 0 |
| company_profiles | 0 |
| company_offerings | 0 |
| company_objections | 0 |
| company_sales_scripts | 0 |
| company_scorecards | 0 |
| company_scorecard_criteria | 0 |
| jobs | 0 |
| admin | id `1`, `admin@admin.com` |

Tests did not write production rows. Migrations were additive only (new knowledge tables + nullable snapshot columns on `sales_analyses`). No fresh/reset.

## Documentation

Updated:

* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/AI_ANALYSIS.md`
* `docs/PRODUCT.md`
* `docs/ROADMAP.md`
* `docs/STATUS.md`
* `docs/DECISIONS.md`
* `README.md`

Accepted:

* DEC-036 — Company knowledge is a separate domain layer
* DEC-037 — Company scorecards are configurable
* DEC-038 — Weighted score is calculated application-side
* DEC-039 — Analyses store immutable context snapshots
* DEC-040 — Public calls use generic analysis only
* DEC-041 — Transcript is untrusted prompt content

## Changed Files

`git diff --stat 555029d3ef9e6fb09a1023181f2de50332b7f49d..0342d15a2cc327528a2c31ef6ca29254a1327f83`

```
 README.md                                          |   4 +-
 REPORT.md                                          | 388 ++++++++--------
 app/Http/Controllers/CallsController.php           |   6 +
 app/Http/Controllers/CompaniesController.php       | 108 ++++-
 .../Controllers/CompanyKnowledgeController.php     | 183 ++++++++
 app/Http/Requests/CompanyObjectionRequest.php      |  44 ++
 app/Http/Requests/CompanyOfferingRequest.php       |  46 ++
 app/Http/Requests/CompanyProfileRequest.php        |  60 +++
 app/Http/Requests/CompanySalesScriptRequest.php    |  37 ++
 .../Requests/CompanyScorecardCriterionRequest.php  |  60 +++
 app/Http/Requests/CompanyScorecardRequest.php      |  39 ++
 app/Jobs/AnalyzeCall.php                           |  14 +-
 app/Models/Company.php                             |  26 ++
 app/Models/CompanyObjection.php                    |  38 ++
 app/Models/CompanyOffering.php                     |  43 ++
 app/Models/CompanyProfile.php                      |  36 ++
 app/Models/CompanySalesScript.php                  |  36 ++
 app/Models/CompanyScorecard.php                    |  53 +++
 app/Models/CompanyScorecardCriterion.php           |  45 ++
 app/Models/SalesAnalysis.php                       |  13 +
 app/Services/Analysis/AnalysisContextBuilder.php   | 357 +++++++++++++++
 app/Services/Analysis/CompanyScoreCalculator.php   |  50 ++
 .../Analysis/ConfiguredSalesAnalysisProvider.php   |   1 +
 app/Services/Analysis/DTO/AnalysisContext.php      |  12 +
 app/Services/Analysis/DTO/SalesAnalysisResult.php  |   8 +
 .../Analysis/SalesAnalysisPromptBuilder.php        |  85 ++--
 .../Analysis/SalesAnalysisResultValidator.php      | 210 ++++++++-
 app/Services/Analysis/SalesAnalysisSchema.php      |  98 +++-
 app/Services/Analysis/SalesAnalysisWriter.php      |   5 +
 app/Support/CompanyKnowledgeCompleteness.php       |  34 ++
 app/Support/SalesAnalysisPresenter.php             |   7 +
 config/sales-analyzer.php                          |   6 +
 database/factories/CompanyObjectionFactory.php     |  30 ++
 database/factories/CompanyOfferingFactory.php      |  34 ++
 database/factories/CompanyProfileFactory.php       |  39 ++
 database/factories/CompanySalesScriptFactory.php   |  29 ++
 .../factories/CompanyScorecardCriterionFactory.php |  34 ++
 database/factories/CompanyScorecardFactory.php     |  29 ++
 database/factories/SalesAnalysisFactory.php        |   2 +
 ...9_10_080000_create_company_knowledge_tables.php | 113 +++++
 ...09_10_080100_add_analysis_context_snapshots.php |  32 ++
 docs/AI_ANALYSIS.md                                |  29 +-
 docs/ARCHITECTURE.md                               |  14 +-
 docs/DATA_MODEL.md                                 | 102 +++--
 docs/DECISIONS.md                                  |  88 +++-
 docs/PRODUCT.md                                    |  16 +-
 docs/ROADMAP.md                                    |  32 +-
 docs/STATUS.md                                     |  13 +-
 resources/js/Components/Public/AnalysisReport.jsx  |  89 +++-
 resources/js/Pages/Calls/Show.jsx                  |  24 +-
 resources/js/Pages/Companies/Index.jsx             |   3 +-
 resources/js/Pages/Companies/Show.jsx              | 502 +++++++++++++++++++++
 routes/owl-admin-pages.php                         |  19 +
 tests/Feature/AnalysisContextBuilderTest.php       | 185 ++++++++
 tests/Feature/CompaniesTest.php                    |   2 +-
 tests/Feature/CompanyKnowledgeTest.php             | 331 ++++++++++++++
 tests/Feature/SalesAnalysisTest.php                | 220 ++++++++-
 tests/Unit/CompanyScoreCalculatorTest.php          |  50 ++
 tests/Unit/SalesAnalysisResultValidatorTest.php    | 146 ++++++
 59 files changed, 4045 insertions(+), 314 deletions(-)
```

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `0342d15a2cc327528a2c31ef6ca29254a1327f83`
* REPORT SHA/files commit: pending
* first successful push: pending
* commit messages:
  * `Add company knowledge, scorecards, and company-aware analysis context.`
* push result: pending

## Problems / Warnings

* Live external provider verification deferred by Project Manager (ElevenLabs and LLM).
* Until Settings → AI has an active provider, model, and key, production calls will stop at `analysis_pending` after transcription.
* Vite optional `fontaine` warning on `npm run build`.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged from Phase 2.2).
* Context budget is a character budget (24,000), not a tokenizer-accurate token count.

## Final Status

`PHASE 5 PASSED`
