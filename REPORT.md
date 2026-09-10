# Sales Analyzer — Phase 7 Deep Call Analysis v2 Report

## Baseline

* accepted baseline SHA (Phase 6 on `main`): `92470fbdda97b8e2dc3cf34c54e094f8722d74d1`
* HEAD before work: `92470fbdda97b8e2dc3cf34c54e094f8722d74d1`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none

No production backup was taken: there is no migration. Schema v3 lives in `sales_analyses.result` JSON.

Live ElevenLabs / LLM verification remains deferred by the Project Manager and did **not** block this phase.

## Schema v3

New analyses write `schema_version = 3` (DEC-047). Stored v1/v2 rows remain presentable; missing v3 keys render as empty / omitted, not as errors.

v3 is v2 plus deep blocks:

* `executive_summary`
* `call_objective`
* `conversation_control`
* `customer_signals` / `missed_signals`
* `discovery_depth`
* `question_analysis`
* `listening`
* `value_communication`
* `objection_map`
* `negotiation` (`applicable=false` when price was not discussed)
* `trust_rapport`
* `closing`
* `timeline`
* `turning_points`
* `critical_mistakes`
* `what_to_repeat` / `what_to_stop` / `what_to_start`
* `coaching_priorities` (max 5)
* `next_call_playbook`
* `better_phrases` (`original` / `problem` / `better` / `why_better`; v2 `suggested`/`reason` still accepted)
* `alternative_path`
* `outcome_analysis`
* `sales_stage_map`
* `customer_intent_confidence`

`company_specific` from v2 is unchanged.

## Deep Analysis Areas

The report is written to answer: what happened; what each side wanted; who led; which signals were used or missed; where the call turned; which mistakes could change the outcome; what to say instead; what to do on the next similar call.

Critical mistakes are outcome-changing only. Tone is judged from wording, not a voice-emotion model. Interruptions are not claimed without overlap evidence.

## Prompt Strategy

`SalesAnalysisPromptBuilder` now requires evidence-first analysis, anti-fluff rules, collection size limits, and the v3 keys. Public calls still get a full deep generic analysis. Company context packing from Phase 5 is unchanged.

## One-pass vs Two-pass Decision

**One structured LLM call.**

A Pass-1 understanding / Pass-2 coaching split was considered. It was not shipped:

* v3 JSON is large but bounded (timeline 15, coaching 5, phrases 10, and similar caps)
* two calls would send the transcript and company context twice
* latency, cost, and failure modes would double
* talk-time metrics are already computed outside the model

If a single prompt later becomes unreliable against real providers, a second pass can be added without changing the stored schema.

## Evidence Model

Quotes, timestamps, and speakers are required on material findings when the transcript has them. Inferred fields (`customer_intent`, speaker roles) may carry `high|medium|low` confidence. The validator rejects unknown enums and negative timestamps.

Modest collection overflow is sliced to the cap. Arrays larger than 2× the cap are rejected.

## Timeline

`timeline` is a short list of material moments (`positive`, `warning`, `critical`, `turning_point`, `objection`, `buying_signal`, `missed_opportunity`). Cap 15 (DEC-050). UI is a vertical list with restrained badges, not a sentence-level dump.

## Conversation Metrics

`ConversationMetricsCalculator` (DEC-049) runs after validation:

* seller / customer talk percent (identified roles, positive segment durations)
* longest consecutive seller monologue
* speaker switches
* call duration

Missing data → `null`. Interruptions are not computed. LLM-supplied metrics are overwritten.

## Coaching

Ranked `coaching_priorities` (max 5), `what_to_repeat` / `stop` / `start`, a four-part next-call playbook, and a short alternative path. This is advice for the next similar call, not an HR coaching-plan module.

## Company Context Compatibility

Phase 5 context builder, scorecard snapshots, and `company_specific` remain. Company calls get v3 + company block. Public calls get v3 without invented company rules.

## Public UI

`AnalysisReport` is the deep report: overall score, executive summary, timeline, signals, critical mistakes, what worked, collapsible deep sections, missed opportunities, better phrases, coaching, playbook, alternative path. Transcript stays below the report on the public home page.

v2 reports without `executive_summary` still show the older summary / strengths / sections layout.

## Admin UI

`Calls/Show` uses the same `AnalysisReport`. Admin additionally shows provider, model, schema version, company context metadata, and timestamps.

## Validation

`SalesAnalysisResultValidator` requires v3 keys on new writes, checks enums (timeline types, stages, objection categories, confidence, impact), timestamps ≥ 0, and collection caps. Company scorecard rules from Phase 5 are unchanged.

## Tests

`php artisan test`: **142 passed**, 1000 assertions.

New / extended:

* `tests/Unit/ConversationMetricsCalculatorTest.php`
* `tests/Unit/SalesAnalysisResultValidatorTest.php` — v3 accept, malformed enums, negative timestamps, overflow slice/reject, objection categories
* `tests/Feature/DeepCallAnalysisTest.php` — public v3 + metrics, no secrets, legacy v2 presentable, admin render, evidence prompt
* `tests/Fixtures/DeepSalesCall.php` — synthetic discovery / price / weak-close transcript
* `SalesAnalysisFactory::validPayload()` is v3; `legacyV2Payload()` for presenter compatibility

Existing Phase 1–6 tests remain green.

## Live Provider Verification

`Deferred by Project Manager`

No live ElevenLabs or LLM call was required or attempted.

## Database Changes

None. No migration. No new searchable columns.

## Production Sentinel

Production connection `database=sales` after `php artisan test` (tests use `sales_testing` only):

| Metric | After |
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

Tests did not write production rows.

## Documentation

Updated:

* `docs/AI_ANALYSIS.md`
* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/PRODUCT.md`
* `docs/ROADMAP.md`
* `docs/STATUS.md`
* `docs/DECISIONS.md`
* `README.md`

Accepted:

* DEC-047 — Deep Call Analysis schema v3
* DEC-048 — Analysis prioritizes evidence over generic coaching
* DEC-049 — Conversation metrics are calculated application-side
* DEC-050 — Timeline contains only material call moments
* DEC-051 — Coaching output is prioritized and actionable

## Changed Files

`git diff --stat 92470fbdda97b8e2dc3cf34c54e094f8722d74d1..c69db2c50c30a93cbf67ef9f5c29f20964c7122e`

```
 README.md                                          |   4 +-
 REPORT.md                                          | 298 +++------
 app/Jobs/AnalyzeCall.php                           |   4 +-
 .../Analysis/ConversationMetricsCalculator.php     | 128 ++++
 .../Analysis/SalesAnalysisPromptBuilder.php        |  48 +-
 .../Analysis/SalesAnalysisResultValidator.php      | 674 ++++++++++++++++++++-
 app/Services/Analysis/SalesAnalysisSchema.php      | 667 +++++++++++++++++---
 app/Support/SalesAnalysisPresenter.php             | 298 ++++++++-
 config/sales-analyzer.php                          |   2 +-
 database/factories/SalesAnalysisFactory.php        | 269 ++++++++
 docs/AI_ANALYSIS.md                                |  54 +-
 docs/ARCHITECTURE.md                               |   2 +-
 docs/DATA_MODEL.md                                 |   6 +-
 docs/DECISIONS.md                                  |  72 ++-
 docs/PRODUCT.md                                    |   4 +-
 docs/ROADMAP.md                                    |  17 +-
 docs/STATUS.md                                     |   5 +-
 resources/js/Components/Public/AnalysisReport.jsx  | 561 ++++++++++++++---
 tests/Feature/DeepCallAnalysisTest.php             | 201 ++++++
 tests/Fixtures/DeepSalesCall.php                   |  54 ++
 tests/Unit/ConversationMetricsCalculatorTest.php   |  97 +++
 tests/Unit/SalesAnalysisResultValidatorTest.php    | 107 ++++
 22 files changed, 3153 insertions(+), 419 deletions(-)
```

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `c69db2c50c30a93cbf67ef9f5c29f20964c7122e`
* REPORT SHA/files commit: `c0509ab`
* first successful push: `92470fb..c0509ab  main -> main`
* commit messages:
  * `Add schema v3 deep call analysis and coaching report.`
  * `Record Phase 7 commit SHA and changed files in REPORT.md.`
  * `Record Phase 7 GitHub push result in REPORT.md.`
* push result: **PASS** — `To https://github.com/Owiiiii1/sales.git` `92470fb..c0509ab  main -> main`

## Problems / Warnings

* Live external provider verification deferred by Project Manager (ElevenLabs and LLM).
* Vite optional `fontaine` warning on `npm run build`.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged from Phase 2.2).
* One-pass v3 JSON is large; collection caps are the size control. A second pass can be added later if live models struggle.
* No PDF export, CRM, billing, or employee coaching plans (out of scope).

## Final Status

`PHASE 7 PASSED`
