# Sales Analyzer — Processing UX, Transcript Modal & Cancellation

## Baseline

Phase 7.2 put Company selection on `/`. After upload the Main Analyzer showed a single spinner and, once STT finished, dumped the full transcript onto the page. There was no way to stop a long job. Call #4 demonstrated the gap: the UI stayed on “analyzing” with no staged progress and no Stop.

This change is UX + a first-class `cancelled` state. The transcription/LLM pipeline is unchanged.

Also included: log writes no longer block `markFailed` (worker `www-data` vs `deploy`-owned `laravel.log`), and Gemini HTTP 400 now prefers `error.message` over a numeric `error.code`.

## Transcript UX

Full `CallTranscript` is no longer rendered on `/`. When a transcript exists, a compact block shows **Call transcript / Transcript ready / Open transcript**. The full text, timestamps, speakers, duration, and language open in `TranscriptModal` (max ~900–1100px, ~90vh, inner scroll, ESC and overlay click close). `CallTranscript` is reused with `framed={false}` inside the modal.

Admin Call detail used the same full-page transcript; it now uses **Open transcript** + the same modal.

## Processing Progress

After upload, Main Analyzer shows **Call processing** with real stages:

1. File uploaded
2. Transcribing call
3. Preparing analysis
4. Analyzing call
5. Complete

No fake percentages. Desktop is a compact horizontal stepper; mobile is vertical. Active uses a light spinner; completed a check; failed an X; cancelled a stop square; `analysis_pending` shows Analysis unavailable without an infinite spinner.

`CallProcessingProgress` maps backend status → step states and is returned on public upload/status/show payloads.

## Status Mapping

| Backend | UI |
|---|---|
| `uploaded` / `processing` | transcription active |
| `transcribed` | preparing analysis active |
| `analyzing` | analysis active |
| `completed` | all complete (compact “Analysis complete ✓”) |
| `analysis_pending` | analysis unavailable + existing safe message |
| `failed` without transcript | transcription failed |
| `failed` with transcript | analysis failed |
| `cancelled` | cancelled on `cancelled_stage` |

## Cancellation

`POST /analysis/{public_token}/cancel` (UUID token only). Admin: `POST /calls/{call}/cancel`.

Cancellable: `uploaded`, `processing`, `transcribed`, `analyzing`. Completed/failed → 409. Already cancelled → 200 idempotent.

UI: **Stop processing** → confirm modal (not `window.confirm`). After success: **Processing stopped**, polling stops, upload form remains. Transcript stays openable if it already exists.

## Job Guards

`TranscribeCall` and `AnalyzeCall` return immediately if the Call is cancelled. After the provider returns they reload under `lockForUpdate`. Cancelled calls do not dispatch `AnalyzeCall`, do not write a new analysis, and do not move to `completed`. `markFailed` will not overwrite `cancelled`.

## Provider Cancellation Limitations

ElevenLabs and LLM HTTP calls are not aborted mid-flight. The Call becomes `cancelled` immediately in our DB. A late STT response may still save the transcript. A late LLM response must not publish a final result.

## Race Conditions

Covered in tests: cancel before the job starts; cancel during STT (late response does not start analysis); cancel after transcribed; cancel during analyzing (late LLM does not complete); cannot cancel completed; repeated cancel is safe.

## Tests

* Progress mapping for every backend status
* Home no longer inlines full transcript; modal + progress + cancel are wired
* Cancel uploaded / processing / transcribed / analyzing
* 409 on completed; idempotent repeat; raw Call id rejected
* Job guards and late-provider races
* Public payloads omit ids, storage paths, and traces

`php artisan test`: **233 passed**, 1613 assertions.

## Database Changes

Migration `2026_09_11_123000_add_call_cancellation_columns.php`:

* `calls.cancelled_at` nullable timestamp
* `calls.cancelled_stage` nullable string (`transcription` | `preparing` | `analysis`)

## Production Sentinel

Backup: `/home/deploy/backups/sales/sales-pre-processing-ux-20260911-125143.sql` (outside the repo). Migration `2026_09_11_123000_add_call_cancellation_columns` applied with `php artisan migrate --force`.

## Documentation

DEC-064 accepted. STATUS, PRODUCT, ARCHITECTURE, DATA_MODEL updated.

## Changed Files

Backend: Call model/status, cancellation service, progress presenter, public/admin cancel routes, TranscribeCall/AnalyzeCall guards, TranscriptPresenter, logging ignore_exceptions, ProviderHttp error messages.

Frontend: Home processing UI, ProcessingProgress, TranscriptModal, CancelProcessingModal, CallTranscript modal variant, Admin Call transcript modal, i18n.

Tests: CallProcessingProgressTest, CallCancellationTest, PublicAnalyzerTest, SalesAnalysisTest, ProviderHttpTest.

Docs: STATUS, PRODUCT, ARCHITECTURE, DATA_MODEL, DECISIONS (DEC-064), REPORT.

## Git

Commit and push to `main` after tests, build, production backup, migrate, sentinel, and secret scan. Secret scan: no literal secrets in the diff.

## Problems / Warnings

Gemini `responseSchema` + v3 `additionalProperties` still yields HTTP 400 on live company analysis (separate from this UX work). Worker PHP changes need a worker recycle (`--max-time=3600` or systemd restart). Log ACL for `www-data` was applied on `storage/logs`.

## Final Status

PROCESSING UX & CANCELLATION PASSED
