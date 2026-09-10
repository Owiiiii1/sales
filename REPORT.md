# Sales Analyzer — Phase 6 Sales Analytics Dashboard Report

## Baseline

* accepted baseline SHA (Phase 5 on `main`): `6a942534f28201cb21a0aa112a8e52561774feac`
* HEAD before work: `6a942534f28201cb21a0aa112a8e52561774feac`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none

No production backup was taken for this phase: there is no migration and analytics are computed from existing rows.

Phase 4–5 live ElevenLabs / LLM verification remains deferred by the Project Manager and did **not** block this phase.

## Analytics Architecture

Analytics sit on existing Companies, Employees, Calls, Transcripts, Sales Analyses, and Company Scorecards. There is no `analytics_daily` table (DEC-042).

```
Individual Calls
      ↓
Employee Analytics
      ↓
Company Analytics
      ↓
Management Dashboard
```

Service layer (thin controllers):

* `AnalyticsFilter` — one period / company / employee object for every page
* `AnalyticsQuery` — loads only needed `calls` columns + analysis scores / `result` / `scorecard_snapshot`
* `AnalyticsAggregator` — PHP-side JSON aggregation (sections, outcomes, snapshots)
* `DashboardAnalyticsService`
* `CompanyAnalyticsService` — always `company_id` set, `companyBound=true`
* `EmployeeAnalyticsService` — always that employee

Date basis (DEC-045): `COALESCE(recorded_at, created_at)` via `Call::analyticsAt()`, grouped in `config('app.timezone')`. The same clock is used for presets, charts, and previous-period comparison.

Public calls (`company_id` null) never enter employee or company analytics (DEC-044). The global dashboard includes them in totals and a **Public Analyses** card.

No public analytics JSON API. No PDF/CSV export.

## Filters

`AnalyticsFilter` presets:

* Last 7 days (`last_7`)
* Last 30 days (`last_30`) — **default**
* Last 90 days (`last_90`)
* This month
* Previous month
* All time
* Custom (`from` / `to` dates)

Query params: `period`, `from`, `to`, `company_id`, `employee_id`.

Company filter: All companies, or one company. Employee list is scoped to that company when a company is selected. Employee–company mismatch drops the employee and sets `employee_mismatch`.

Employee / company pages force their own ids so a crafted query cannot widen the scope.

Grouping: **day** when the window is ≤ 45 days, otherwise **week**.

## Dashboard

`GET /dashboard` is the sales analytics view.

KPI cards:

* Total Calls
* Analyzed Calls
* Analysis Success Rate
* Average Sales Score
* Average Company Scorecard
* Active Employees (distinct `employee_id` in the period)
* Calls This Period (same window as Total Calls)
* Failed Calls
* Public Analyses (global only; hidden when a company or employee is selected)

Layout: Filters → KPI cards → rates note → three trend charts → section performance → outcomes / intent → scorecard → mandatory questions / forbidden claims → recent strengths/weaknesses → recent calls.

## Employee Analytics

New `GET /employees/{employee}` (`employees.show`).

Overview: name, position, company, active flag. Not an HR profile.

KPIs: total calls, analyzed, average generic score, average company scorecard, failure rate, average duration.

Also: score / calls trends, section performance, scorecard performance, outcomes, recent calls, recent strengths / weaknesses.

Only that employee’s calls. Public calls are excluded.

Employees index has a **View** link.

## Company Analytics

Existing `GET /companies/{company}` gained an **Analytics** tab. Payload is loaded only when `tab=analytics`.

Shows: totals, analyzed, average sales score, average scorecard, roster employee count, success/failure, score and calls trends, sections, snapshot scorecard criteria, outcomes, top missed mandatory questions, forbidden-claim violations, employee comparison.

Employee comparison table (not a leaderboard): Employee, Calls, Analyzed, Avg Sales Score, Avg Company Score, Avg Duration, Failed, trend vs previous period when comparable. Sortable in the UI. No rank / badges.

## Score Metrics

Generic sales score and company scorecard are never mixed (DEC-046).

* Average sales = mean of `sales_analyses.overall_score` (nulls ignored)
* Average company scorecard = mean of `sales_analyses.company_scorecard_score` (nulls ignored)
* Missing scorecard → **N/A**, never a fake 0

Bands (`config/sales-analyzer.php` / `ScoreBand`): 80–100 good, 60–79 warning, below 60 poor.

Formulas:

* Analyzed = `status = completed`
* Completion rate = completed / total
* Failed rate = failed / total
* Pending rate = (total − completed − failed) / total
* Analysis success rate = completed / (completed + failed); N/A if none finished
* Average duration = mean of non-null `calls.duration_seconds`

## Trends

Three SVG charts (no extra npm chart library):

* Average Sales Score Over Time
* Calls Over Time
* Company Scorecard Over Time

Day buckets for short windows, week buckets for longer ones. `all_time` has no previous window; trend % is **N/A**. Otherwise the previous window is the same length immediately before the current period. Zero previous denominator → N/A, not a fabricated percent.

## Section Performance

From schema v2 `sales_analyses.result` generic sections:

* Opening & Rapport
* Discovery & Needs
* Questions & Listening
* Presentation & Value
* Objections
* Pricing / Negotiation
* Closing & Next Step

Each row: average score, call count, applicable count. `applicable=false` is excluded. Horizontal bars.

Lowest-performing applicable sections are listed as the reliable weakness layer. Recurring free-text strengths/weaknesses are a short recent list, not NLP clustering.

## Scorecard Performance

Historical criteria come from each analysis `scorecard_snapshot` plus `result.company_specific.scorecard` (DEC-043). Live `company_scorecard_criteria` is not used for history.

Per criterion: name, average normalized score (0–100), weight, applicable count, critical failure count.

## Outcomes

Distribution (bar counts): `sale`, `appointment`, `follow_up`, `proposal`, `interested`, `not_interested`, `lost`, `unresolved`, `unknown`.

Customer intent is a separate distribution: `high`, `medium`, `low`, `unknown`.

No revenue.

## Mandatory Questions

Company-specific analyses: asked count, missed count, top missed texts from the snapshot/result (canonical strings, not free-text clustering). Cap: 10.

## Forbidden Claims

Calls with violations, total violation count, latest 10 (call + texts). No alarm system.

## Performance Considerations

* No new tables / materialized aggregates
* Select only analytics columns; `storage_path` stays hidden; transcripts and provider secrets are not loaded
* Recent calls: 20
* Latest violations: 10
* Recent findings: 8
* Employee comparison is the company roster for the selected period (no extra pagination in v1; volume is small)
* Company analytics payload is not built on Overview / knowledge tabs

## UI

Shared `resources/js/Components/Analytics/Board.jsx`. Charts are SVG polylines. Dates use locale formatting (`toLocaleString`), not hardcoded UTC labels. Empty / failed-only / no-scorecard states render **N/A**. Color is restrained (emerald / amber / slate bands).

## Tests

`php artisan test`: **130 passed**, 927 assertions.

New / updated:

* `tests/Unit/AnalyticsFilterTest.php` — default last 30 days, custom range, previous window, `all_time` has no previous, score bands
* `tests/Feature/AnalyticsTest.php` — auth, empty N/A, KPI averages, date filter, public vs company vs employee, mismatch, sections exclude non-applicable, snapshot scorecard + critical failures, outcomes / mandatory / violations, employee isolation, day grouping + period comparison, no `storage_path` / transcript / API keys in payload
* `tests/Feature/NavigationRoutesTest.php` — dashboard asserts `filters` + `analytics`

Existing Phase 1–5 tests remain green.

## Database Changes

None. No migration. No `analytics_daily`.

## Production Sentinel

No migrate this phase.

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
| company_profiles | 0 |
| company_offerings | 0 |
| company_objections | 0 |
| company_sales_scripts | 0 |
| company_scorecards | 0 |
| company_scorecard_criteria | 0 |
| jobs | 0 |
| admin | id `1`, `admin@admin.com` |

Tests did not write production rows.

## Documentation

Updated:

* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/PRODUCT.md`
* `docs/ROADMAP.md`
* `docs/STATUS.md`
* `docs/DECISIONS.md`
* `README.md`

Accepted:

* DEC-042 — Analytics are computed from source-of-truth call data
* DEC-043 — Historical scorecard analytics use analysis snapshots
* DEC-044 — Public calls are excluded from employee/company analytics
* DEC-045 — Analytics date uses recorded_at with created_at fallback
* DEC-046 — Generic score and company scorecard remain separate metrics

Live provider verification: **Deferred by Project Manager**.

## Changed Files

`git diff --stat 6a942534f28201cb21a0aa112a8e52561774feac..dfa203ac5e95b0bae175977fb260274496a68a14`

```
 README.md                                          |   4 +-
 REPORT.md                                          | 349 ++++++-------
 app/Http/Controllers/CompaniesController.php       |  18 +-
 app/Http/Controllers/DashboardController.php       |  37 +-
 app/Http/Controllers/EmployeesController.php       |  23 +
 app/Models/Call.php                                |   6 +
 app/Services/Analytics/AnalyticsAggregator.php     | 556 +++++++++++++++++++++
 app/Services/Analytics/AnalyticsFilter.php         | 155 ++++++
 app/Services/Analytics/AnalyticsQuery.php          | 123 +++++
 app/Services/Analytics/CompanyAnalyticsService.php |  40 ++
 .../Analytics/DashboardAnalyticsService.php        |  35 ++
 .../Analytics/EmployeeAnalyticsService.php         |  33 ++
 app/Support/ScoreBand.php                          |  35 ++
 config/sales-analyzer.php                          |  10 +
 docs/ARCHITECTURE.md                               |  20 +-
 docs/DATA_MODEL.md                                 |  13 +-
 docs/DECISIONS.md                                  |  72 ++-
 docs/PRODUCT.md                                    |   2 +-
 docs/ROADMAP.md                                    |  32 +-
 docs/STATUS.md                                     |   9 +-
 resources/js/Components/Analytics/Board.jsx        | 504 +++++++++++++++++++
 resources/js/Pages/Companies/Show.jsx              |  29 +-
 resources/js/Pages/Dashboard.jsx                   | 107 +---
 resources/js/Pages/Employees/Index.jsx             |   3 +-
 resources/js/Pages/Employees/Show.jsx              |  32 ++
 routes/owl-admin-pages.php                         |   1 +
 tests/Feature/AnalyticsTest.php                    | 424 ++++++++++++++++
 tests/Feature/NavigationRoutesTest.php             |   6 +-
 tests/Unit/AnalyticsFilterTest.php                 |  68 +++
 29 files changed, 2381 insertions(+), 365 deletions(-)
```

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `dfa203ac5e95b0bae175977fb260274496a68a14`
* REPORT SHA/files commit: `324d8a9`
* first successful push: `6a94253..324d8a9  main -> main`
* commit messages:
  * `Add sales analytics dashboard for companies, employees, and calls.`
  * `Record Phase 6 commit SHA and changed files in REPORT.md.`
  * `Record Phase 6 GitHub push result in REPORT.md.`
* push result: **PASS** — `To https://github.com/Owiiiii1/sales.git` `6a94253..324d8a9  main -> main`

## Problems / Warnings

* Live external provider verification deferred by Project Manager (ElevenLabs and LLM).
* Vite optional `fontaine` warning on `npm run build`.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged from Phase 2.2).
* Employee comparison table is not paginated; acceptable while company rosters stay small.
* No CSV/PDF export, scheduled reports, billing, CRM, telephony, embeddings, or gamification (later phases).

## Final Status

`PHASE 6 PASSED`
