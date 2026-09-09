# Sales Analyzer — Phase 4 Structured AI Sales Analysis Report

## Baseline

* accepted baseline SHA (Phase 3 on `main`): `d9f143be418a64f96eb32be240121d8463fbb8fc`
* HEAD before work: `d9f143be418a64f96eb32be240121d8463fbb8fc`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none

Production database backup (outside Git): `/home/deploy/backups/sales/sales-pre-phase4-20260909-163145.sql`

Phase 3 live ElevenLabs verification remains deferred by the Project Manager and did **not** block this phase.

## Architecture

Application layer:

* `SalesAnalysisProvider` interface
* `ConfiguredSalesAnalysisProvider` adapter over kit AI settings
* `SalesAnalysisPromptBuilder`
* `SalesAnalysisResultValidator`
* `SalesAnalysisResult` DTO
* `AnalyzeCall` job

Pipeline:

```
uploaded → processing → transcribed → analysis_pending | analyzing → completed
```

`processing` is STT only. `analyzing` is AI only. Missing AI configuration is `analysis_pending`, not a fatal error.

Domain code does not depend on a specific LLM vendor. HTTP stays in kit provider clients, not in the job.

## Analysis Schema

`schema_version` = `1`.

JSON `result` is the source of truth. Denormalized columns: `overall_score`, `summary`, `provider`, `model`.

Version 1 includes overall score 0–100, summary, call_outcome, customer_intent, speaker_roles, seven sections with `applicable`, strengths/issues as evidence objects, buying signals, missed opportunities, recommendations, better phrases, and next_step.

Inapplicable sections (for example no pricing talk) use `applicable: false` instead of a fake low score.

## Provider Integration

Reused Custom Admin Kit Settings → AI. No second settings table.

Found and reused:

* table `ai_provider_settings`
* model `AiProviderSetting` (encrypted `api_key`, `is_active`, `active_model`, `available_models`)
* `AiSettingsController` (save key, check connection, activate/deactivate)
* `AiProviderManager` and clients: OpenAI, Anthropic, Gemini

Kit clients previously only listed models. `AiProviderClient::completeJson()` was added so chat/completions HTTP lives next to the existing clients. `ActiveAiProvider` reads the active row (active flag + key + model).

If nothing is configured, `AnalyzeCall` sets `analysis_pending` and returns.

## Prompt

`SalesAnalysisPromptBuilder` builds system + user messages.

* generic sales methodology only
* allowed product languages: English, Russian, Ukrainian
* write the report in the conversation language
* do not translate the transcript
* structured JSON required
* no hallucination / no invented events
* evidence quotes must be short
* seller/customer mapping in `speaker_roles` only
* score semantics 0–100
* `applicable: false` when a section does not occur
* company name is metadata, not RAG
* company description is not injected

## Validation

`SalesAnalysisResultValidator` requires keys, 0–100 scores, known section names, arrays, allowed outcomes, intents, and speaker roles. Syntactically valid JSON that fails schema is rejected and not saved.

OpenAI uses `response_format.json_schema`. Anthropic/Gemini request JSON and are parsed, then validated the same way.

## Speaker Roles

Transcript segments are unchanged. Analysis JSON maps integer speakers to `seller` | `customer` | `unknown` | `other`. Uncertain mappings must be `unknown`.

## Status Lifecycle

```
uploaded
→ processing
→ transcribed
→ analysis_pending
→ analyzing
→ completed
```

Failure: `failed`.

Public copy:

* uploaded: `Your call is queued for transcription.`
* processing: `Transcribing your call…`
* transcribed: `Transcription complete.`
* analysis_pending: `Transcription complete. AI analysis is not configured yet.`
* analyzing: `Analyzing your sales call…`
* completed: structured report
* failed: generic transcription or analysis error (unsupported language still has its own public string)

## Queue Pipeline

1. Upload dispatches `TranscribeCall` (unchanged).
2. Successful STT stores transcript, sets `transcribed`, dispatches `AnalyzeCall`.
3. `AnalyzeCall` requires a transcript.
4. No AI settings → `analysis_pending`.
5. Configured → `analyzing` → provider → validate → replace `sales_analyses` → `completed`.

Job: 3 attempts, backoff 60 / 180 / 600 seconds, timeout 180. Transient: timeout / 429 / 5xx. Permanent 4xx / schema / missing transcript do not retry. A previous successful analysis is kept if a later run fails.

Existing `sales-worker.service` was not replaced.

Admin `POST /calls/{call}/analyze` dispatches the same job (Run analysis / Re-run analysis). No LLM call in the controller.

## Public UI

Polling continues through `transcribed` and `analyzing`, and stops on `analysis_pending`, `completed`, or `failed`.

`GET /analysis/{public_token}` after `completed` returns the structured report without Call/Transcript/analysis ids, storage paths, provider/model, prompts, or raw provider payloads.

`AnalysisReport` now renders overall score, summary, outcome, intent, strengths/weaknesses, the seven sections, buying signals, objections, missed opportunities, recommendations, better phrases, and next step.

Transcript is shown once STT has finished (including while analyzing).

## Admin UI

Call detail shows analysis provider, model, schema version, timestamps, speaker roles, and the same structured report. Buttons:

* Retry transcription
* Run analysis (`transcribed`, `analysis_pending`, `failed` with a transcript)
* Re-run analysis (`completed`)

## Data Model

Additive migration `2026_09_09_170000_create_sales_analyses_table` (no fresh/reset).

`sales_analyses`: `call_id` unique FK cascade, provider, model, schema_version, overall_score, summary, result JSON, started_at, completed_at, error_message, timestamps.

Call hasOne SalesAnalysis.

## Tests

`php artisan test`: **84 passed, 0 failed, 418 assertions**. No live LLM or ElevenLabs calls.

Coverage includes:

* transcribed call dispatches `AnalyzeCall`
* no transcript → analysis impossible
* no provider config → `analysis_pending`
* configured provider → analyzing then completed
* valid structured result / overall score / JSON saved
* malformed JSON rejected
* invalid score / outcome / speaker role rejected
* provider 5xx retryable
* permanent provider failure handled
* previous successful analysis preserved on failed rerun
* public report safe (no provider/model/ids/storage_path)
* report language preserved (Russian summary stored as-is)
* admin detail includes analysis
* manual Run analysis and Re-run analysis
* public payloads for analyzing and completed
* existing Phase 1–3 tests remain green

`npm run build` is recorded below.

## Live Provider Verification

`Deferred by Project Manager`

No live ElevenLabs or LLM call was required or attempted for this phase.

## Production Sentinel

Recorded on production connection `database=sales` after `php artisan migrate --force` and `php artisan test`.

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

Tests did not write production rows. Migration only added an empty `sales_analyses` table.

## Documentation

Updated:

* `docs/ARCHITECTURE.md`
* `docs/DATA_MODEL.md`
* `docs/AI_ANALYSIS.md`
* `docs/PRODUCT.md`
* `docs/ROADMAP.md`
* `docs/STATUS.md`
* `docs/DECISIONS.md`

Accepted:

* DEC-030 — Sales analysis has versioned structured schema
* DEC-031 — Generic sales analysis precedes company-specific context
* DEC-032 — AI analysis runs asynchronously
* DEC-033 — Evidence-backed findings are required
* DEC-034 — Speaker roles are analysis metadata, not transcript mutation
* DEC-035 — Live external-provider verification may be deferred during development

## Changed Files

`git diff --stat d9f143be418a64f96eb32be240121d8463fbb8fc..83023a7b8843e16aabff8572b4d2c4655db89d46`

```
 README.md                                          |   4 +-
 REPORT.md                                          | 395 +++++++----------
 app/Exceptions/Analysis/AnalysisException.php      |  13 +
 .../Analysis/PermanentAnalysisException.php        |  19 +
 .../Analysis/TransientAnalysisException.php        |  11 +
 app/Http/Controllers/CallsController.php           |  30 +-
 app/Http/Controllers/PublicAnalyzerController.php  |  14 +-
 app/Jobs/AnalyzeCall.php                           | 125 ++++++
 app/Jobs/TranscribeCall.php                        |   4 +-
 app/Models/Call.php                                |   7 +
 app/Models/SalesAnalysis.php                       |  45 ++
 app/Models/Transcript.php                          |   4 +
 app/Models/TranscriptSegment.php                   |   4 +
 app/Providers/AppServiceProvider.php               |   3 +
 app/Services/Ai/ActiveAiProvider.php               |  27 ++
 app/Services/Ai/AiProviderManager.php              |  15 +
 app/Services/Ai/Clients/AnthropicClient.php        |  48 +++
 app/Services/Ai/Clients/GeminiClient.php           |  45 ++
 app/Services/Ai/Clients/OpenAiClient.php           |  46 ++
 app/Services/Ai/Contracts/AiProviderClient.php     |  14 +
 app/Services/Ai/JsonPayloadParser.php              |  28 ++
 app/Services/Ai/ProviderHttp.php                   |  61 +++
 .../Analysis/ConfiguredSalesAnalysisProvider.php   |  58 +++
 app/Services/Analysis/DTO/AnalysisContext.php      |  11 +
 app/Services/Analysis/DTO/SalesAnalysisResult.php  |  18 +
 .../Analysis/SalesAnalysisPromptBuilder.php        |  83 ++++
 app/Services/Analysis/SalesAnalysisProvider.php    |  14 +
 .../Analysis/SalesAnalysisResultValidator.php      | 253 +++++++++++
 app/Services/Analysis/SalesAnalysisSchema.php      | 149 +++++++
 app/Services/Analysis/SalesAnalysisWriter.php      |  33 ++
 app/Support/SalesAnalysisPresenter.php             | 190 +++++++++
 app/Support/TranscriptPresenter.php                |   8 +-
 database/factories/SalesAnalysisFactory.php        | 103 +++++
 database/factories/TranscriptFactory.php           |  40 ++
 database/factories/TranscriptSegmentFactory.php    |  31 ++
 ...26_09_09_170000_create_sales_analyses_table.php |  31 ++
 docs/AI_ANALYSIS.md                                |  13 +-
 docs/ARCHITECTURE.md                               |  23 +-
 docs/DATA_MODEL.md                                 |  31 +-
 docs/DECISIONS.md                                  |  88 +++-
 docs/PRODUCT.md                                    |   4 +-
 docs/ROADMAP.md                                    |  14 +-
 docs/STATUS.md                                     |  43 +-
 resources/js/Components/Public/AnalysisReport.jsx  | 193 +++++++--
 resources/js/Pages/Calls/Index.jsx                 |   6 +-
 resources/js/Pages/Calls/Show.jsx                  |  58 ++-
 resources/js/Pages/Public/Home.jsx                 |  18 +-
 routes/owl-admin-pages.php                         |   1 +
 tests/Feature/SalesAnalysisTest.php                | 466 +++++++++++++++++++++
 tests/Unit/SalesAnalysisResultValidatorTest.php    |  62 +++
 50 files changed, 2638 insertions(+), 366 deletions(-)
```

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `83023a7b8843e16aabff8572b4d2c4655db89d46`
* message: `Add generic structured AI sales analysis after transcription.`
* push: pending

## Problems / Warnings

* Live external provider verification deferred by Project Manager (ElevenLabs and LLM).
* Until Settings → AI has an active provider, model, and key, production calls will stop at `analysis_pending` after transcription.
* Vite optional `fontaine` warning on `npm run build`.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged from Phase 2.2).

## Final Status

`PHASE 4 PASSED`
