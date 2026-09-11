# Ukrainian Transcription Language Fix

## Baseline

Call **#17** (`source=public`, `company_id=3`, `ui_locale=ru`) failed transcription at 2026-09-11 14:11:09. Public error: `This language is not supported yet.` No transcript row. Audio remained on the private disk. `failed_jobs` has no TranscribeCall row for this call. Production `laravel.log` was readable. No API keys are recorded here.

Deploy cannot read the audio file (owner `www-data`, ACL mask empty). The original worker STT is the source of the raw language code.

## Live Result Audit

| Field | Value |
|---|---|
| call id | 17 |
| status (before fix) | failed |
| public error | This language is not supported yet. |
| ui_locale | ru |
| transcript | none |
| laravel.log | `Unsupported transcription language: spa` |
| failed_jobs | not this call |

## Root Cause

Exact raw ElevenLabs `language_code`: **`spa`**.

That is Spanish, not a Ukrainian variant. `LanguageCode::normalize('ukr')` already returned `uk`; this incident never hit that alias. `spa` must not be rewritten to `uk`. UI locale was Russian; UI language is not the call language.

A separate allowlist gap still existed: BCP-47 tags such as `uk-UA` were lowercased as a whole (`uk-ua`) and failed `isSupported()`. That is fixed even though it was not this call.

Production `LOG_LEVEL=error`, so `Log::warning` / `Log::info` never reached `laravel.log`. Language diagnostics now use `Log::error`.

## Normalization Change

Primary BCP-47 subtag, then aliases:

* en / eng / english / en-US / en-GB → `en`
* ru / rus / russian / ru-RU → `ru`
* uk / ukr / ukrainian / uk-UA / UK-UA / ua-UA → `uk`

Unknown codes (`spa`, `de`, `pol`) stay themselves and stay unsupported.

## Retry Policy

Main Analyzer does not send UI locale as ElevenLabs `language_code`.

One automatic retry with `language_code=ukr` only if auto-detect is unsupported **and** the transcript contains Ukrainian-specific letters (`і`, `є`, `ї`, `ґ`).

Call #17 auto-detect stayed `spa` with Latin/Spanish text, so that retry did not run.

An ops hint `new TranscribeCall(17, 'uk')` was tried once. ElevenLabs then returned `detected_language=ukr` with `language_probability=1`, but the stored text was Spanish conversation, not Ukrainian. That result was discarded so `spa` is not masked as `uk`.

## Errors and Logging

Internal: `Unsupported transcription language returned by ElevenLabs: <raw code>`.

Public: `Could not detect a supported call language.` (RU: `Не удалось определить поддерживаемый язык звонка.`)

Reject logs (error level): `call_id`, `raw_language_code`, `normalized_language_code`, `language_probability`, `text_script`, `has_ukrainian_letters`. No transcript text, no secrets.

## Live Retry

1. Worker recycled at 16:41 CEST (PID 3092544). Auto `TranscribeCall(17)`: HTTP success, raw code **`spa` again**, call failed with the new public message. No Ukrainian-letter retry.
2. Ops hint `uk`: STT completed (`language=uk`, `detected=ukr`, probability 1, 68 segments, 2 speakers, timestamps present, duration ~635s), then analysis completed. Transcript text was Spanish, not Ukrainian.
3. Forced `uk` result and its analysis row were removed. Call **#17** restored to `failed` / `Could not detect a supported call language.` Audio kept. No second Call created.

This recording is Spanish. ElevenLabs auto-detect was correct. It is not a missed `ukr` mapping.

## Tests

`php artisan test`: **270 passed**, 1851 assertions (transcription filter 17 passed after log-level change).

## Production Verification

* `/` 200, `/up` 200
* Call #17 status `failed`, no transcript, audio present
* Public error uses the new copy, not the raw `spa` code
* Queue `jobs=0`
* Worker running (max-time recycle loaded the STT client)

## Changed Files

Backend: `LanguageCode`, ElevenLabs client, `TranscribeCall` optional language hint, public error mapping, `lang/ru.json`, `lang/uk.json`.

Frontend: Main Analyzer scrolls to the result block after Analyze Call (`Home.jsx`).

Tests and docs: PRODUCT/STATUS/AI_ANALYSIS/ARCHITECTURE/DECISIONS (DEC-072), REPORT.md.

## Git

Commit and push `origin/main` after tests, secret scan, and live retry.

## Final Status

**UKRAINIAN TRANSCRIPTION LANGUAGE FIX PASSED**

Call #17 remains `failed` because the provider language is Spanish (`spa`), which is outside the EN/RU/UK allowlist.
