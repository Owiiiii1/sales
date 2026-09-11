# Sales Analyzer — Phase 7.2 Correction: Explicit Analysis Context

## Baseline

Phase 7.2 already put Company and optional Employee on `/`. Empty `<select>` value was Generic, so Generic was pre-selected and **Analyze Call** was enabled immediately. This correction makes context an explicit choice. No new modules. No migration.

## What changed

### 1. Company / context select

The Main Analyzer select starts unselected:

* placeholder: `Select analysis context`
* explicit Generic: `No company — Generic analysis` (sentinel `generic`, never sent as `company_id`)
* explicit Company: a named active company

`company_id = null` remains valid on `POST /analyze` only after the operator chooses Generic. Upload is not submitted until a context is selected.

### 2. Employee

Still optional.

* Generic: Employee disabled, value null
* Company: Employee optional, list filtered to that company
* Changing context still resets Employee (`changeCompany` clears `employeeId`)

### 3. Analyze button

Disabled until context is selected. Hint:

`Select a company or Generic analysis first.`

Label is `Analyze Call`.

### 4. Context summary

Shown only after a context is chosen, before upload.

Generic:

```
Generic analysis
General sales methodology
No company-specific rules will be used
```

Company:

```
{Company} · Company analysis
Knowledge: {percent}%
Scorecard: {name}
Employee: {name}   // only if selected
```

Home payload still exposes only `id`, `name`, `knowledge_completeness`, `scorecard_name` for companies and `id`, `company_id`, `name` for employees. No scripts, forbidden claims, notes, or other knowledge secrets.

### 5. Report badge

* Generic: `Generic analysis`
* Company: `Company analysis · {Company Name}`
* If Employee: nearby `Employee · {Employee Name}`

Admin call show passes `analysisMode`, `companyName`, and `employeeName` into the shared report.

### 6. Source

`calls.source = public` is unchanged. No migration.

Technical debt: `public` is historical and now means upload through the Main Analyzer Workspace. On a later suitable migration, consider `manual_analyzer`. Do not migrate only for this rename.

## Backend

Unchanged contract:

* omitted / null `company_id` → generic (`company_id` and `employee_id` null)
* company + optional employee still validated (active company, active employee of that company, employee requires company)
* inactive company/employee rejected

Explicitness is UI-only. The `generic` sentinel is not posted.

## Files

* `resources/js/Pages/Public/Home.jsx`
* `resources/js/i18n/catalog.js`
* `resources/js/Components/Public/AnalysisReport.jsx`
* `resources/js/Pages/Calls/Show.jsx`
* `tests/Feature/PublicAnalyzerTest.php`
* `docs/PRODUCT.md`, `docs/STATUS.md`, `docs/DECISIONS.md` (DEC-063), `docs/ROADMAP.md`, `docs/DATA_MODEL.md`, `docs/PROJECT.md`, `docs/ARCHITECTURE.md`

## Tests added

* initial home state has no selected context
* Home UI requires explicit context before Analyze (source contract: placeholder, disabled Analyze, Generic sentinel, Employee reset)
* explicit Generic upload keeps `company_id` / `employee_id` null
* explicit Company upload without Employee is allowed
* home company/employee payload shape is only safe metadata (nested Inertia assert without extra keys)
* existing generic, company+employee, and validation tests remain

## Docs

Recorded: **Main Analyzer requires explicit analysis context selection before upload. Generic remains a first-class explicit mode.**

DEC-063 accepted. DEC-057 consequences updated so empty select is no longer Generic.

## Database

None. No production backup or migrate for this correction.
