# Sales Analyzer — Architecture

## Intent

Keep the system understandable.

On day one, prefer **one Laravel application** that orchestrates upload, queues, storage, and AI calls.

Add a separate Python/AI service only if Laravel cannot reasonably host a required library or performance becomes a real problem. That split is **not** the default.

## Current stack (running)

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel 13.31 | Installed in `/var/www/sales` |
| Language | PHP 8.5.8 | PHP-FPM socket `/run/php/php8.5-fpm.sock` |
| Database | MySQL 8.0.46 | Database `sales`, user `sales`@`localhost` |
| Frontend | Inertia + React + Vite | Kit frontend-setup completed |
| Admin | OwlSolutions Custom Admin Kit v0.5.0 | Stock `admin` preset |
| Telegram SDK | Nutgram 4.50.0 | Installed with the kit; bot **not** configured |
| Web server | nginx 1.24.0 | Site config only for this domain |
| OS | Ubuntu 24.04.4 LTS | Shared server; this project is isolated |
| Domain | `https://sales.owlsolutions.net` | HTTPS via Let’s Encrypt |
| Git | `https://github.com/Owiiiii1/sales.git` | Branch `main` |

See [STATUS.md](STATUS.md) for operational detail.

## Current application shape

The app is a standard Laravel 13 project with kit routes:

* guest `/` → admin login;
* `/login` → redirect to `/`;
* authenticated `/` → dashboard;
* kit CRM and settings routes as published by the admin preset.

There is **no** Sales Analyzer domain layer yet (no Call, Company, Employee, Analysis models beyond kit CRM).

## Target runtime shape (planned)

Stay inside Laravel unless proven otherwise:

```
Browser
  → nginx
    → PHP-FPM / Laravel
      → MySQL
      → filesystem / object storage (TBD)
      → queue workers
        → STT / diarization provider (TBD)
        → LLM provider (TBD)
        → embeddings / vector store (TBD)
```

## Preferred processing chain

```
Upload
  → store audio
  → queue job
  → transcription
  → analysis
  → persist result
  → report ready
```

Status values for a call (planned, names TBD): e.g. `uploaded` → `transcribing` → `analyzing` → `ready` / `failed`.

Queues **will** be needed for heavy audio work. Which queue backend (database, Redis, etc.) is **TBD**. Laravel currently uses `QUEUE_CONNECTION=database` in production `.env`; that may or may not be enough. **Open question.**

## AI architecture

**Not chosen.** Do not treat any vendor as selected.

### Open choices

| Concern | Status | Candidate examples (not a decision) |
|---|---|---|
| LLM | Open | General-purpose APIs (OpenAI, Anthropic, Gemini, others). Kit already has UI stubs for OpenAI / Anthropic / Gemini — that is kit UI, not a product decision. |
| Transcription (STT) | Open | Whisper-class APIs, Deepgram, AssemblyAI, Google, provider bundled with diarization, others. |
| Diarization | Open | Same provider as STT, or a separate step. |
| Embeddings | Open | Same LLM vendor or dedicated embeddings API. |
| Vector store | Open | Could be skipped in MVP if context is a short form. Later: pgvector-like, dedicated vector DB, or files+MySQL. **TBD.** |

Fine-tuning is **not** part of MVP. See DEC-004 in [DECISIONS.md](DECISIONS.md).

Analysis is expected to be **several structured LLM calls**, not a mandatory multi-agent framework. See [AI_ANALYSIS.md](AI_ANALYSIS.md).

## Storage

| Data | Current | Planned |
|---|---|---|
| Relational records | MySQL `sales` | Keep MySQL for users, companies, calls, scores |
| Audio files | Not implemented | Local disk vs S3-compatible object storage. **TBD** |
| Transcripts | Not implemented | Table vs JSON. **TBD** — [DATA_MODEL.md](DATA_MODEL.md) |
| Company knowledge | Not implemented | Documents + optional RAG. **TBD** |

## Frontend architecture

Current: Inertia React pages from the admin kit, Vite build, Ziggy routes.

Public Upload Call UI is **Planned**. Whether it lives in the same Inertia app or a separate public layout is **TBD**. Preference: same Laravel/Inertia app unless there is a reason to split.

## Isolation on the server

This project must not affect other sites on the same host.

Constraints (accepted operationally during deploy):

* project files only under `/var/www/sales`;
* dedicated MySQL database `sales`;
* dedicated nginx vhost `sales.owlsolutions.net`;
* no edits to other nginx sites, other `.env` files, or other databases;
* nginx `reload` after `nginx -t`, not a casual `restart`.

## What not to do yet

* Do not add microservices “for AI cleanliness.”
* Do not train a custom model.
* Do not pick STT/LLM providers in code until a decision is recorded.
* Do not migrate kit CRM tables away until Phase 1 adaptation is specified.

## Open architecture questions

* Queue driver for production audio jobs.
* Audio object storage.
* Whether kit AI settings screens will wrap the product’s LLM/STT keys or a separate config will be used.
* Max upload size / duration limits.
* Idempotency and retry policy for provider calls.
