# Sales Analyzer — Gemini Structured Output Production Fix

## Incident

Call #4 (`analyzing`, transcript ~15k chars, company_id 3) never finished. Queue was empty. Gemini `gemini-3.7-flash` returned HTTP 400. Public/internal error collapsed to `HTTP 400 (400)`. A `storage/logs` permission error could also block `markFailed`, leaving the Call stuck in `analyzing`.

## Root Cause

1. `GeminiClient` sent schema v3 through legacy OpenAPI `generationConfig.responseSchema`. v3 is JSON Schema (`additionalProperties`, nullable unions). Gemini rejected it (`INVALID_ARGUMENT`, `additionalProperties` / later compiler errors).
2. After switching to `responseJsonSchema`, generateContent still cannot compile the **full nested** v3 graph (duplicate top-level `required`, nullable-union volume, nested complexity). Live compiler errors were `duplicate elements in required` then generic `Request contains an invalid argument`.
3. `markFailed` logged before persisting `failed`. Logger exceptions could skip the status write.

## Gemini Schema Fix

Kept `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`. Never send `responseSchema`.

Gemini adapter (`GeminiClient`):

* `responseMimeType = application/json`
* `responseJsonSchema` = shallow projection of v3 required keys/types (array items as `{text}` objects so the compiler accepts the graph)
* full provider-neutral `SalesAnalysisSchema::jsonSchema()` in the system prompt
* unique `required` lists on the domain schema (`customer_intent_confidence` was duplicated)
* `SalesAnalysisResultValidator` still enforces v3
* timeline `type` filled from `text` when Gemini omits the enum
* scorecard criteria without `key` are skipped; snapshot keys are still filled as not applicable

OpenAI / Anthropic wrappers unchanged. Domain schema v3 was not reduced.

## Error Parsing

`ProviderHttp` Gemini path uses `error.message` and `error.status`, never numeric `error.code` as the human text.

Example: `Gemini HTTP 400 INVALID_ARGUMENT: Invalid JSON schema`

Public analyzer still shows `Analysis failed. Please try again.`

## Failure-State Reliability

`AnalyzeCall` / `TranscribeCall` persist `failed` (and never overwrite `completed` / `cancelled`) **before** logging. Logger exceptions are swallowed. Stack `ignore_exceptions => true` remains.

## Log Permissions

`/var/www/sales/storage/logs` owner `deploy:www-data`, ACL `user:www-data:rwx` (default ACL for new files). `laravel.log` ACL `user:www-data:rw`. Not mode 777.

## Worker Restart

Unit: `/etc/systemd/system/sales-worker.service`  
`User=www-data`  
`ExecStart=/usr/bin/php8.5 artisan queue:work database --sleep=3 --tries=3 --timeout=300 --max-time=3600`

Deploy cannot `sudo` without a password (`kill` on the worker PID is not permitted).

Authorized restart:

```bash
sudo systemctl restart sales-worker.service
systemctl is-active sales-worker.service
systemctl show sales-worker.service -p MainPID,ExecMainStartTimestamp
```

Auto-recycle observed: PID 3056249, `ExecMainStartTimestamp=Fri 2026-09-11 13:23:36 CEST`. That recycle is **older than** the final Gemini adapter. Restart the unit so queued jobs load the new client.

Call #4 was re-run in-process (`sales:recover-stuck-calls --call=4 --retry --sync`) and did not depend on that worker PID.

## Stuck Call Recovery

`php artisan sales:recover-stuck-calls`

* Default: mark `processing`/`analyzing` failed when no matching `jobs` row and older than `sales-analyzer.analysis.stuck_after_minutes` (30).
* `--call=` targets one id (no age wait).
* `--retry` dispatches `TranscribeCall` / `AnalyzeCall`.
* `--sync` runs the job in this process (requires `--retry`).
* Safe message: `Processing interrupted or worker job was lost`.
* No cron auto-retry.

## Live Gemini Verification

| Item | Result |
|---|---|
| Model | `gemini-3.7-flash` |
| Endpoint | `v1beta` `generateContent` |
| HTTP | 200 |
| JSON decoded | yes |
| Validator | schema v3 accepted |
| Call #4 | `completed`, `overall_score` 48, `provider` gemini, `error_message` null |
| Transcription | not repeated |
| Queue after | 0 jobs |
| Secrets | not logged |

Earlier live 400s (duplicate `required`, then compiler `INVALID_ARGUMENT`) were fixed before this completed run. Public error never exposed Gemini internals.

## Tests

`php artisan test`: **250 passed**, 1700 assertions.

Covered: `responseJsonSchema` present and `responseSchema` absent; v3 keywords in the prompt payload; unique `required`; Gemini 400 message includes `INVALID_ARGUMENT` and `Invalid JSON schema` not `(400)`; public JSON stays `Analysis failed. Please try again.`; logging exception still sets `failed`; `failed()` does not resurrect `completed`; retry after Gemini 400 can complete; stuck-call recovery and `--retry`.

Frontend unchanged; `npm run build` not required.

## Production Verification

* Call #4: `completed` / schema 3 / score 48 / Gemini `gemini-3.7-flash`
* `jobs` table empty
* Worker active; needs an additional systemd restart for queued jobs (see Worker Restart)
* No new stuck analyzing jobs in queue

## Changed Files

`app/Services/Ai/Clients/GeminiClient.php`, `app/Services/Ai/ProviderHttp.php`, `app/Jobs/AnalyzeCall.php`, `app/Jobs/TranscribeCall.php`, `app/Services/Analysis/SalesAnalysisSchema.php`, `app/Services/Analysis/SalesAnalysisResultValidator.php`, `app/Services/Analysis/ConfiguredSalesAnalysisProvider.php`, `app/Services/Calls/StuckCallRecovery.php`, `app/Console/Commands/RecoverStuckCallsCommand.php`, `config/sales-analyzer.php`, tests, `docs/STATUS.md`, `docs/AI_ANALYSIS.md`, `docs/ARCHITECTURE.md`, `docs/DECISIONS.md` (DEC-065, DEC-066), `REPORT.md`.

## Git

Commit and push to `main` after tests, secret scan, and live Call #4 verification.

## Final Status

**GEMINI STRUCTURED OUTPUT PRODUCTION FIX PASSED**
