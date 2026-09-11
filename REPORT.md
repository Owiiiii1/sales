# Sales Analyzer — Report Structure & UX Revision

## Baseline

Latest completed live analysis before this change: Call **#13** (`public`, company_id 3, schema v3, provider Gemini `gemini-3.7-flash`, overall_score 48, company_scorecard_score `null`, transcript language `ru`). Call #4 was an earlier completed Gemini run of the same pipeline with the shallow `{text}` wire schema.

No secrets, API keys, or internal credentials are recorded here.

## Live Result Audit

Sanitized JSON from Call #13 showed nested keys present but almost no semantic content:

| Block | Count | What was actually stored |
|---|---|---|
| `critical_mistakes` | 3 | keys present; `mistake`/`why`/`better_action`/`example_phrase` empty; `impact=high` |
| `missed_signals` | 1 | `signal` empty; `impact` and `seller_response_quality` filled with defaults |
| `missed_opportunities` | 2 | `{text}` items with real sentences (shallow schema fit) |
| `better_phrases` | 1 | `original`/`problem`/`better`/`why_better` empty |
| `coaching_priorities` | 3 | `priority` 1–3; `skill`/`why`/`practice` empty |
| `timeline` | 6 | titles filled from `{text}` fallback; `description` empty; type forced |
| `turning_points` | 2 | `what_changed` empty; default `impact` |
| `what_to_repeat` / `stop` / `start` | 2 / 3 / 3 | `text` filled; `why` empty |
| `company_specific.scorecard.criteria` | 9 | all snapshot keys present, **all `applicable=false`**, company score `null` |

`report_language` was not stored on the result. The worker had no UI locale to use.

## Gemini Output Findings

Gemini did return objects for the high-value arrays, but the wire schema only required `{text}`. Fields that were not in that item schema were dropped. Normalization then invented a usable-looking v3 shape:

* empty `mistake` + default `impact=high` → empty critical cards (“Влияние: Высокое”)
* missing scorecard `key`/`score` → validator skipped items, then filled every snapshot criterion as N/A
* timeline titles taken from `text`; empty titles became “Timeline moment”

Useful `{text}` blocks (`what_to_repeat`, `missed_opportunities`) survived because they matched the shallow item shape.

## Gemini Wire Schema Fix

Provider-specific `GeminiClient::forGeminiWire()` now models slim nested items for:

* `critical_mistakes`: timestamp_seconds, mistake, impact, why, better_action, example_phrase
* `better_phrases`: timestamp_seconds, original, problem, better, why_better
* `coaching_priorities`: priority, skill, why, evidence (string array), practice, success_criteria
* `company_specific.scorecard.criteria`: key, score, max_score, applicable, summary

Live compiler probes: adding full nested `missed_signals` and `timeline` **together with** those three arrays exceeds generateContent’s budget (HTTP 400 `INVALID_ARGUMENT`). Those two arrays stay `{text}`; after parse, `text` is copied to `signal` / `title`, and timeline `type` defaults to `turning_point` only when a title exists.

Other arrays stay the simplified `{text}` item. Full v3 is still attached in the prompt and enforced by the validator.

## Validator Changes

Strategy: **skip malformed individual items** when the rest of the payload is valid. Do not create empty cards.

Skipped when the required semantic field is blank: `critical_mistakes.mistake`, `missed_signals.signal`, `better_phrases` (original and better), `coaching_priorities.skill`, `timeline.title` + valid type, `turning_points.what_changed`, empty practice `text`, empty findings `text`.

Scorecard: map by `key`; attach snapshot `name`; do not treat unknown keys as N/A. If a **majority of expected keys are missing**, throw `TransientAnalysisException` so the job retries instead of publishing an all-N/A company score. Remaining unmatched keys after a majority hit may still be N/A when Gemini explicitly omitted only a minority.

## Report Language

Main Analyzer: selected UI locale at upload (`en` / `ru` / `uk`) is stored on `calls.ui_locale` and used by `AnalysisContextBuilder`. Priority: **stored UI locale > company `report_language` > transcript language**. The worker does not read session/browser locale. Quotes stay in the transcript language. Company `report_language` remains for admin/manual calls without `ui_locale`.

## Short Report

Default Main Analyzer completed view (`ShortAnalysisReport`): scores (company primary + generic secondary, or overall), executive summary, max 3 strengths, max 3 problems, max 3 next-call actions, compact scorecard weakest/strongest, and an info card with **Open full report**.

## Full Report

`GET /analysis/{public_token}/full` is a read-only Inertia page of the same `sales_analyses` row. No new AI job. Public token only. Admin Call detail still defaults to the full report.

## Report Information Architecture

Full report groups: A outcome → B what happened → C what went well → D what went poorly → E how to improve → F deep skills → G what to improve first / playbook → H company standard (company calls only) → I transcript via existing modal, not inline.

## Timeline UX

`Хронология звонка` is one collapsed row with a moment count and visible Expand/Collapse. Inner timeline renders only after expand.

## Deep Analysis UX

Skill blocks use an explicit Expand/Collapse control on the header row, not a card that looks static.

## Critical Mistakes

Block kept. Cards show mistake, impact, why, better action, example phrase, timestamp when present. Empty cards are not rendered. Empty list omits the section.

## Missed Opportunities

Prefer `missed_signals`, fallback `missed_opportunities`. Shows the customer signal, why it mattered, recommended action, and a better reply when present. Hidden when both are empty.

## Better Phrases

Was / Problem / Better / Why / timestamp. Hidden when empty.

## Coaching Priorities

UI label is **What to improve first** / **Что улучшить в первую очередь**. Skill, why, evidence, practice, success criteria. Max 5. Hidden when empty.

## Company Scorecard

Criteria mapped by key with human `name` labels (`Диагностика потребности — 12 / 18`). Applicable rows as a compact score table; N/A grouped at the bottom. Majority-missing output fails the analysis instead of a false all-N/A score.

## Empty Block Policy

No section heading or empty container is rendered after normalization if the block has no valid items.

## Visual Design

Section groups, inner cards, spacing, restrained borders. Critical cards use a light rose tone; strengths a light green tone; coaching cards are numbered. Not a rainbow UI.

## Live Re-analysis

Re-run `AnalyzeCall` only (no transcription) on Call **#13** after migrate. First attempt compiled (HTTP 200) then truncated JSON (`TransientAnalysisException`); second attempt **completed**.

Sanitized outcome:

* `ui_locale=ru`, transcript language `ru`, narrative Russian
* overall_score 48, **company_score 49** (was `null`)
* `critical_mistakes` 3/3 with mistake, why, example phrase
* `better_phrases` 2/2 with original + better
* `coaching_priorities` 3/3 with skill/why/practice (Russian skill titles)
* `missed_signals` 2/2 with signal text
* `timeline` 8 titled moments
* scorecard 9/9 applicable, none N/A
* Quotes on this call were mostly unset on timeline items; transcript and report are both Russian, so quote-language split was not observable on this recording

This call did have critical mistakes, better phrases, coaching, and missed signals; they were empty before because of the wire schema, not because the conversation lacked them.

## Tests

`php artisan test`: **261 passed**, 1795 assertions.

Covered: Gemini wire fields for mistakes / phrases / coaching / scorecard; `text` mapped to timeline title and missed signal; malformed critical item skipped; empty mistake not presented; majority-missing scorecard fails; mapped criteria accepted; UI ru→Russian report / UI en→English report with original quotes; stored locale used instead of later session locale; completed Main Analyzer JSON includes `short` (max 3); full report route uses the same result and creates no AI job; public token payload has no internal id/secrets.

UI expand/collapse and section order are implemented in `FullAnalysisReport` / `TimelineSection`; PHP asserts the payload and route contract (no browser runner in this repo).

## Production Verification

* Backup: `/home/deploy/backups/sales/sales-20260911-151507-notablespaces.sql.gz`
* Migration `2026_09_11_160000_add_ui_locale_to_calls_table` applied (`migrate --force`)
* `npm run build` succeeded (`FullReport`, `FullAnalysisReport`, `Home` chunks)
* Shipped Gemini `responseJsonSchema` live compile: HTTP 200
* Call #13 re-analyzed without transcription: `completed`, overall 48, company 49, filled mistakes/phrases/coaching/signals/scorecard
* Public `GET /analysis/{token}` 200, no `id`/`storage_path`/`api_key`
* `GET /analysis/{token}/full` 200
* `/` 200, `/up` 200
* `jobs` table empty

## Changed Files

Backend: Gemini adapter, validator, schema, prompt, context builder, upload/request, Call `ui_locale` migration, presenter, short-report composer, public controller/routes.

Frontend: `ShortAnalysisReport`, `FullAnalysisReport`, shared cards, Home short default, admin full default, i18n.

Tests and docs: PRODUCT, STATUS, AI_ANALYSIS, ARCHITECTURE, DECISIONS (DEC-067–071), DATA_MODEL, REPORT.md.

## Git

* `php artisan test`: 261 passed, 1795 assertions
* `npm run build` succeeded
* Secret scan of the working tree/diff: no API keys or credentials
* Backup + `ui_locale` migration already applied
* Live re-analysis of Call #13 completed without transcription
* Commit and push `origin/main`

## Final Status

**REPORT STRUCTURE & UX REVISION PASSED**
