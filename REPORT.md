# Sales Analyzer — Phase 7.2 Main Analyzer Company Selection Report

## Baseline

Laravel 13 + Inertia/React on `https://sales.owlsolutions.net`. Pipeline: upload → ElevenLabs STT → schema v3 LLM analysis. Company knowledge and scorecards already exist in Admin (Phase 5). `AnalysisContextBuilder` already branches on `company_id`. No new modules. No migrations.

`php artisan test`: **179 tests, 179 passed, 1307 assertions.**

`npm run build`: succeeded (`Home-BdpGVXLS.js`, `AnalysisReport-rJj-xg8G.js`). Known non-blocking `fontaine` warning.

## Previous Product Logic

`/` was an unauthenticated upload that always created:

```text
company_id = null
employee_id = null
source = public
```

Company-specific analysis only ran for Admin uploads. DEC-040 forbade asking for a company on the public form.

## New Analyzer Logic

`/` is the **main analyzer workspace**. The operator selects context, then uploads audio.

- Default: Generic analysis (`company_id` null).
- Optional Company: company-specific analysis via existing `AnalysisContextBuilder`.
- Optional Employee: only after a Company is selected; stored on the Call for analytics, not as prompt instructions.
- `source` remains `public` (no terminology-only migration).
- The system never guesses Company from audio or transcript (DEC-057).

## Company Selection

Active companies only. Payload to the page:

```json
{ "id": 1, "name": "ABC", "knowledge_completeness": 80, "scorecard_name": "Default" }
```

First option: `No company — Generic analysis`. Not required.

## Employee Selection

Active employees with `company_id`. Disabled + `No employee` when no Company is selected. Changing Company clears Employee. Not required.

## Upload Validation

`POST /analyze` (`PublicAnalyzeRequest`):

| Field | Rules |
|---|---|
| `audio` | existing audio rules |
| `company_id` | nullable, integer, exists **active** companies |
| `employee_id` | nullable, integer, exists **active** employees; requires `company_id`; `employee.company_id` must match |

`uploaded_by` and `source` cannot be injected. Call is created with the selected ids (or null) and `source=public`.

## Analysis Context

Unchanged. `AnalyzeCall` still calls `AnalysisContextBuilder`:

- `company_id` null → generic v3
- `company_id` set → profile, offerings, objections, scripts, mandatory questions, forbidden claims, default scorecard

Employee display name is metadata on the JSON payload (`employee_name`), not sales instructions.

## Generic Mode

UI copy: **Generic analysis** — *The call will be analyzed using general sales methodology without company-specific rules.*

## Company Mode

UI copy: **Company-specific analysis** — *Company knowledge, scripts, objections and scorecard will be used.* Compact line: company name, knowledge %, scorecard name when present.

Report badge: `Generic analysis` or `Company analysis · {name}`.

## Security

Route `/` stays unauthenticated technically. Frontend never receives `sales_context`, scripts, forbidden claims, objections, scorecard `ai_instructions`, or notes. Analysis context is built only on the backend. Public JSON still omits `id`, `storage_path`, `company_id`, `employee_id`, `uploaded_by`.

## Analytics Compatibility

Existing Phase 6 rules:

- Analyzer Call **with** Company → company analytics
- Analyzer Call **with** Employee → employee analytics
- Generic (`company_id` null) → excluded from company/employee analytics; counted as Public Analyses on the global dashboard

Admin → Calls shows Company, Employee, source, status as before.

## Tests

`tests/Feature/PublicAnalyzerTest.php` now covers:

- home payload: active companies/employees only; no knowledge secrets
- generic upload: null company/employee, generic `AnalysisContextBuilder`
- company+employee upload: FKs saved, company context used, `source=public`
- employee without company / other company / inactive employee / inactive company rejected
- source/uploader injection ignored
- analyzer company/employee Call included in those analytics; generic remains `public_analyses`

Existing suite remains green (179).

## Database Changes

None. `calls.company_id` and `calls.employee_id` already existed.

## Production Sentinel

No `migrate`. Counts after this phase (no knowledge dumped):

| Table | Count |
|---|---|
| calls | 0 |
| companies | 1 |
| employees | 1 |
| transcripts | 0 |
| sales_analyses | 0 |
| ai_provider_settings | 3 |
| users | 1 |
| transcription_provider_settings | 1 |
| analysis_settings | 1 |

No worker restart. No `.env` edits.

## Documentation

Updated: `PRODUCT.md`, `PROJECT.md`, `ARCHITECTURE.md`, `AI_ANALYSIS.md`, `DATA_MODEL.md`, `STATUS.md`, `ROADMAP.md`, `DECISIONS.md`.

- DEC-057 Accepted — Main analyzer explicitly selects company context
- DEC-040 Superseded by DEC-057
- DEC-018 / DEC-044 wording aligned (generic = `company_id` null; selected company is analytics-bound)

Terminology: **Main Analyzer Workspace** / **Analyzer**, not anonymous public customer upload.

## Changed Files

Phase 7.2:

- `app/Http/Controllers/PublicAnalyzerController.php`
- `app/Http/Requests/PublicAnalyzeRequest.php`
- `app/Http/Requests/Concerns/ValidatesEmployeeCompany.php`
- `resources/js/Pages/Public/Home.jsx`
- `resources/js/Components/Public/AnalysisReport.jsx`
- `resources/js/i18n/catalog.js`
- `lang/ru.json`, `lang/uk.json`
- `tests/Feature/PublicAnalyzerTest.php`
- docs listed above
- `REPORT.md`

Also on `main` from earlier uncommitted UI/i18n work that this page depends on (locale switcher, `useT()`, create-company modal). No secrets.

## Git

Commit and push to `main` after tests, build, sentinel, and secret scan.

## Problems / Warnings

- Vite optional `fontaine` warning (unchanged).
- Live STT/LLM verification still deferred.
- Analyzer route remains unauthenticated by design (DEC-017); company **knowledge** is not exposed.
- Context budget is still byte-based `strlen` (known from YFS fill; out of this phase’s scope).

## Final Status

**PHASE 7.2 PASSED**
