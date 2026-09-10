# Sales Analyzer — Phase 7.1 Provider Settings Completion Report

## Baseline

* accepted baseline SHA (Phase 7 on `main`): `33583e3fc487191ef611894ece9409c7c0ef6724`
* HEAD before work: `33583e3fc487191ef611894ece9409c7c0ef6724`
* working tree before work: clean
* branch: `main` tracking `origin/main`
* unpushed commits before work: none

Live ElevenLabs / LLM verification remains deferred by the Project Manager and did **not** block this phase.

## Existing Settings Audit

Before this phase, Settings tabs were General, Users, AI, App (Telegram hidden).

Settings → AI already covered OpenAI, Anthropic, and Gemini: encrypted keys, masked display, check connection, model activate. That layer was left working.

There was no key/value app-settings table. Telegram uses a dedicated table. Transcription used `config('sales-analyzer.transcription.api_key')` and `ELEVENLABS_STT_MODEL` inside `ElevenLabsTranscriptionClient` (direct config/env). `ActiveAiProvider` already existed for LLM. Analysis HTTP clients hardcoded `temperature => 0.2`. Anthropic sent `max_tokens => 8192`; OpenAI and Gemini had no max-output setting.

## Transcription Settings

New admin tab: **Settings → Transcription**.

Backend: `transcription_provider_settings` + `TranscriptionSettingsController` + `ActiveTranscriptionProvider`.

UI: provider label ElevenLabs (not a fake multi-provider selector), API key, Scribe v2 model, connection status, Check connection, Save. Blank key save does not erase the stored key. Configuration source is shown (Database / Environment / Not configured).

## ElevenLabs Configuration

* Provider row bootstrapped as `elevenlabs` / `ElevenLabs` with no secret.
* Default model: `scribe_v2` (application-side list only; no fake STT model discovery).
* API key encrypted with Laravel `encrypted` cast, `$hidden = ['api_key']`, masked in Inertia (`first4...last4`).
* Check connection: `GET https://api.elevenlabs.io/v1/user` with `xi-api-key`. No audio, no transcription.
* Success: Connected, `is_active=true`, `last_checked_at`, cleared error.
* Failure: sanitized error, no raw secrets.

## Configuration Resolution

Priority is explicit (DEC-053 / DEC-054):

```
DB configured provider
→ env fallback (`config('sales-analyzer.transcription.api_key')` / `ELEVENLABS_API_KEY`)
→ not configured
```

If a non-empty DB key exists, env is ignored. If the DB key is empty, env is used and the admin UI shows `Configured via environment` without revealing the value. Env-only credentials are treated as connected/ready (no Check required). DB credentials are ready only with key + model + connection passed + active.

`php artisan config:cache` can freeze env fallback. It does not freeze DB keys. Workers read DB on each job (DEC-056).

## AI Settings

Left in place: provider cards, API key, check, model, activate / deactivate.

Added on the AI tab:

* Analysis Pipeline health block (shared with Transcription)
* Analysis Behavior (output language, max output tokens, schema v3 informational)

**Temperature:** not added as a Settings field. All three adapters support it, but structured v3 JSON needs stability more than creativity. Adapters keep hardcoded `temperature = 0.2`.

## Analysis Behavior Settings

New `analysis_settings` table (product-level, not per provider row):

* `report_language_mode = same_as_call` (disabled/info in UI: “Analysis report follows the detected call language.”)
* `max_output_tokens` default **16384**, min 4096, max 32768
* Provider caps: OpenAI 32768, Anthropic 16384, Gemini 16384
* Schema version remains informational v3

`ConfiguredSalesAnalysisProvider` passes `maxOutputTokensFor($provider)` into `completeJson`.

## Pipeline Health

`AnalysisPipelineHealth` returns a normalized payload (no keys):

* transcription: ready, provider, model, message, source
* analysis: ready, provider, model, message
* `pipeline_ready` only when both layers are ready

Messages include: API key missing, Connection not checked, Connection failed, Model missing, No active provider, Ready.

Admin Settings show this block on Transcription and AI tabs.

## Public Behavior

Public upload is accepted only when transcription is ready.

Otherwise HTTP 503 `{ "message": "Audio analysis is temporarily unavailable." }` — no Call created, no ElevenLabs/provider names, no keys.

Public homepage disables upload with the same copy.

If STT succeeded but LLM is not configured, status remains `analysis_pending`. Public copy:

`Transcription completed, but AI analysis is temporarily unavailable.`

## Admin Behavior

Call detail exposes `analysis_ready` and `analysis_unavailable_message`. Run / Re-run analysis is disabled when AI is not configured. `POST /calls/{call}/analyze` returns a validation error and does **not** dispatch `AnalyzeCall`. The job still keeps its own `isConfigured()` check.

## Encryption & Secret Safety

* Laravel encrypted casts on `TranscriptionProviderSetting` and existing `AiProviderSetting`
* `api_key` hidden from `toArray()` / JSON
* `SecretMask` for admin payloads
* connection errors sanitized
* no keys in logs, docs, REPORT, or Git
* plaintext key is not stored in MySQL

## Tests

`php artisan test`: **168 passed**, 1158 assertions.

Coverage added/updated:

* Transcription settings: bootstrap, encrypted save, masked output, blank does not erase, replace key, model stored, activation via check, check success/failure, no secret leaks
* Resolver: DB preferred, env fallback, no config, model fallback, decrypted key internal-only
* ElevenLabs client uses resolver; source has no `env(`
* Pipeline health: nothing configured, STT only, AI only, both, failed connection, env STT
* Public: unavailable STT safe message; AI pending remains safe
* Admin: Settings payload; Run Analysis rejected when AI unavailable; configured pipeline still dispatches mocked jobs
* Existing Phase 1–7 tests remain green

`ConversationMetricsCalculatorTest` now uses `RefreshDatabase` so unit-suite inserts cannot leak into later feature tests.

## Live Provider Verification

Deferred by Project Manager

Check connection is a real HTTP call to ElevenLabs `GET /v1/user` (mocked in CI). It is not a fake button.

## Database Changes

Additive migration `2026_09_10_120000_create_transcription_and_analysis_settings.php`:

* `transcription_provider_settings`
* `analysis_settings`

Bootstrap: ElevenLabs row without API key, model `scribe_v2`; one analysis_settings row (`same_as_call`, 16384).

No fresh/reset. Production backup taken before `php artisan migrate --force`.

## Production Sentinel

Pre-migrate backup: `/home/deploy/backups/sales/sales-pre-phase71-20260910-103439.sql` (database `sales`).

After `php artisan migrate --force`:

| Table | Count |
|---|---|
| calls | 0 (unchanged) |
| companies | 0 |
| employees | 0 |
| transcripts | 0 |
| sales_analyses | 0 |
| ai_provider_settings | 3 (unchanged) |
| users | 1 (unchanged) |
| transcription_provider_settings | 1 (ElevenLabs, no key) |
| analysis_settings | 1 (`same_as_call`, 16384) |

STT `api_key` is SQL NULL. No worker restart was required for DB credentials.

## Documentation

Updated:

* `docs/ARCHITECTURE.md`
* `docs/AI_ANALYSIS.md`
* `docs/PRODUCT.md`
* `docs/STATUS.md`
* `docs/ROADMAP.md`
* `docs/DECISIONS.md`
* `docs/DATA_MODEL.md`

Accepted:

* DEC-052 — Transcription provider settings are managed separately from LLM settings
* DEC-053 — Runtime provider credentials prefer database configuration
* DEC-054 — Environment credentials are fallback configuration
* DEC-055 — Pipeline readiness is exposed as one normalized health state
* DEC-056 — Provider credentials are configurable without worker restart

## Changed Files

ALL changes `33583e3fc487191ef611894ece9409c7c0ef6724`..`c0c1e5c4e7a462cc251dcaae62ba378b258347d7`:

```
 REPORT.md                                          | 245 +++++++---------
 app/Http/Controllers/CallsController.php           |  12 +
 app/Http/Controllers/PublicAnalyzerController.php  |  17 +-
 .../Controllers/Settings/AiSettingsController.php  |  17 +-
 .../Settings/AnalysisSettingsController.php        |  50 ++++
 .../Controllers/Settings/SettingsController.php    |  25 +-
 .../Settings/TranscriptionSettingsController.php   | 102 +++++++
 app/Models/AiProviderSetting.php                   |   4 +
 app/Models/AnalysisSetting.php                     |  25 ++
 app/Models/TranscriptionProviderSetting.php        |  40 +++
 app/Services/Ai/AiProviderManager.php              |   3 +-
 app/Services/Ai/Clients/AnthropicClient.php        |   5 +-
 app/Services/Ai/Clients/GeminiClient.php           |   4 +-
 app/Services/Ai/Clients/OpenAiClient.php           |   4 +-
 app/Services/Ai/Contracts/AiProviderClient.php     |   1 +
 .../Analysis/AnalysisSettingsRepository.php        |  40 +++
 .../Analysis/ConfiguredSalesAnalysisProvider.php   |   2 +
 app/Services/Pipeline/AnalysisPipelineHealth.php   | 152 ++++++++++
 .../Transcription/ActiveTranscriptionProvider.php  |  72 +++++
 .../Transcription/DTO/TranscriptionCredentials.php |  29 ++
 .../Transcription/ElevenLabsConnectionChecker.php  |  42 +++
 .../ElevenLabsTranscriptionClient.php              |  14 +-
 app/Support/SecretMask.php                         |  36 +++
 config/sales-analyzer.php                          |  14 +
 ..._create_transcription_and_analysis_settings.php |  64 ++++
 docs/AI_ANALYSIS.md                                |  15 +-
 docs/ARCHITECTURE.md                               |  23 +-
 docs/DATA_MODEL.md                                 |  32 +-
 docs/DECISIONS.md                                  |  74 ++++-
 docs/PRODUCT.md                                    |   3 +-
 docs/ROADMAP.md                                    |  15 +-
 docs/STATUS.md                                     |  18 +-
 resources/js/Pages/Calls/Show.jsx                  |  30 +-
 resources/js/Pages/Public/Home.jsx                 |  24 +-
 .../js/Pages/Settings/AnalysisBehaviorPanel.jsx    |  70 +++++
 resources/js/Pages/Settings/Index.jsx              |  22 +-
 resources/js/Pages/Settings/PipelineStatus.jsx     |  95 ++++++
 resources/js/Pages/Settings/TranscriptionPanel.jsx | 139 +++++++++
 routes/owl-admin-pages.php                         |   6 +
 tests/Feature/PipelineHealthTest.php               | 144 +++++++++
 tests/Feature/ProviderSettingsTest.php             | 325 +++++++++++++++++++++
 tests/Feature/PublicAnalyzerTest.php               |   1 +
 tests/Feature/SalesAnalysisTest.php                |   4 +-
 tests/Unit/ActiveTranscriptionProviderTest.php     |  91 ++++++
 tests/Unit/AnalysisSettingsRepositoryTest.php      |  24 ++
 tests/Unit/ConversationMetricsCalculatorTest.php   |   2 +
 46 files changed, 1960 insertions(+), 216 deletions(-)
```

## Git

* branch: `main`
* remote: `https://github.com/Owiiiii1/sales.git`
* implementation commit: `c0c1e5c4e7a462cc251dcaae62ba378b258347d7`
* REPORT SHA/files commit: `d14649b67737593bc89f8fb30e6022cfad5108c7`
* first successful push: `33583e3..d14649b  main -> main`
* commit messages:
  * `Add transcription settings and pipeline readiness to admin.`
  * `Record Phase 7.1 commit SHA and changed files in REPORT.md.`
  * `Record Phase 7.1 GitHub push result in REPORT.md.`
* push result: **PASS** — `To https://github.com/Owiiiii1/sales.git` `33583e3..d14649b  main -> main`

## Problems / Warnings

* Live external provider verification deferred by Project Manager (ElevenLabs and LLM).
* Vite optional `fontaine` warning on `npm run build`.
* Production MySQL user `sales` still has grants on `sales_testing.*` (unchanged from Phase 2.2).
* Temperature is intentionally not configurable.
* Env fallback still requires config cache clear / worker restart if `.env` is edited by hand. DB keys do not.

## Final Status

`PHASE 7.1 PASSED`
