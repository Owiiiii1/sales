# Sales Analyzer — Phase 7.3 Methodology Alignment & Context Reliability Report

## Baseline

Phase 7.2 already let the main analyzer choose a Company before upload. YFS company knowledge is filled in production. The remaining failure mode was analysis reliability: context packing used byte `strlen`/`substr` (unsafe for Ukrainian/Cyrillic), core profile packed too late, facts were mixed into free-text knowledge, score caps did not exist, and company report language could not override the call language.

This phase is a reliability fix for the current one-call analyzer. It is not a new product layer.

## UTF-8 Context Fix

`AnalysisContextBuilder` now measures and truncates with `mb_strlen(..., 'UTF-8')` and `mb_substr(..., ..., ..., 'UTF-8')`. Truncation cannot split a UTF-8 character. Packed context remains valid UTF-8 and JSON-encodable.

## Context Budget

Default `sales-analyzer.analysis.context_budget_characters` is **50,000**, still overridable with `SALES_ANALYSIS_CONTEXT_BUDGET`. No tokenizer-specific system was added.

## Context Priority

Fixed v1 order:

1. Scorecard
2. Verifiable company facts
3. Core profile
4. Mandatory questions
5. Forbidden claims
6. Offerings / pricing
7. Sales scripts
8. Objections
9. Competitors / notes

Under budget pressure, facts and core profile survive before scripts and notes. There is no drag-and-drop priority UI.

## Company Facts

New table `company_facts` (`label`, `value`, `status` current/outdated, `valid_until`, `source`, `is_active`). Company detail has a Facts tab with CRUD. Current and outdated rows are visually distinct.

Active facts are packed as:

```
VERIFIABLE COMPANY FACTS
CURRENT:
...
OUTDATED:
...
```

The prompt treats CURRENT facts as authoritative, forbids presenting OUTDATED facts as current, and forbids inventing facts missing from context.

## Score Caps

New table `company_scorecard_caps`. Trigger types: `criterion_critical_failure`, `forbidden_claim_violation`. After the weighted company score:

`final = min(weighted_score, lowest_triggered_cap)`

The LLM does not calculate caps. A forbidden-claim cap fires only when company-specific analysis recorded at least one confirmed violation, not because forbidden-claim text exists in knowledge.

The scorecard snapshot stores the cap rules used for that analysis. Result JSON stores `weighted_score`, `total_score` (final), and `triggered_caps`. Older analyses are left unchanged. Re-run uses current cap rules.

Company → Scorecard includes a Score Caps CRUD block and optional score bands (90/75/60/40). Missing custom bands fall back to the existing generic 80/60 bands. Global analytics bands are unchanged.

## Score Display

If the call has a Company and the analysis used a custom scorecard, the report primary number is **Company Score**. Generic **General Sales Score** remains secondary. Generic calls still show **Overall Sales Score**. Cap UI can show weighted vs final and the triggered cap name.

## Report Language

`company_profiles.report_language`: `same_as_call` | `en` | `ru` | `uk` (default `same_as_call`). Generic calls use `analysis_settings.report_language_mode`. Company calls use the company setting. Evidence quotes stay in the original transcript language even when the narrative is Russian.

## Stage Talk Metrics

`ConversationMetricsCalculator` now intersects segments, speaker roles, and `sales_stage_map`. Each valid stage gets seller/customer talk %, duration, and switches. `discovery_talk_balance` is `{seller_talk_percent, customer_talk_percent}` or `null` when discovery bounds are missing or invalid. Interruptions are not calculated.

## Tests

Added/updated coverage for UTF-8 budgets, packing priority, facts CRUD and context inclusion, score caps (none / one / lowest of many / forbidden violation / snapshot immutability / rerun), company Russian report override, generic same-as-call, original quotes, discovery talk ratio, invalid timestamps, truncated JSON retry, and a large valid v3 payload.

`php artisan test`: **205 passed**, 1440 assertions.

## Database Changes

Migration `2026_09_11_120000_phase_73_facts_caps_language_bands.php`:

* `company_facts`
* `company_scorecard_caps`
* `company_profiles.report_language`
* `company_scorecards.score_bands`

## Production Sentinel

Backup: `/home/deploy/backups/sales/sales-pre-phase73-20260911-120034.sql` (outside the repo).

| | Before | After |
|---|---|---|
| database | `sales` | `sales` |
| users | 1 | 1 |
| companies | 1 | 1 |
| employees | 1 | 1 |
| calls | 0 | 0 |
| company_facts | missing | 0 |
| company_scorecard_caps | missing | 0 |
| admin | id 1 / admin@admin.com | unchanged |

Production row counts for users/companies/employees/calls did not change. New tables exist and are empty until operators add facts and caps.

## Documentation

Updated AI_ANALYSIS, ARCHITECTURE, DATA_MODEL, PRODUCT, STATUS, ROADMAP, DECISIONS.

Added:

* DEC-058 — Context character budgets are UTF-8 safe
* DEC-059 — Critical score caps are applied application-side
* DEC-060 — Verifiable company facts are authoritative analysis context
* DEC-061 — Company report language may differ from call language
* DEC-062 — Stage talk metrics are calculated application-side

## Changed Files

See git commit. Principal areas: analysis context/prompt/validator/metrics, company facts and score caps, company/report UI, tests, docs.

## Git

Commit and push to `main` after tests, build, production backup, migrate, sentinel, and secret scan. Secret scan: no literal secrets in the diff.

## Problems / Warnings

None known at write time. Live LLM verification remains deferred. YFS facts/caps are not auto-seeded; operators add them in Admin → Company → Facts / Scorecard.

## Final Status

PHASE 7.3 PASSED
