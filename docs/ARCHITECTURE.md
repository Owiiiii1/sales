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

Laravel 13 + Custom Admin Kit, with a public product surface and an admin foundation:

* guest `/` → public Sales Analyzer homepage (upload + future report);
* `/login` → admin login;
* authenticated `/dashboard` → admin;
* product admin routes: `/companies`, `/employees`, `/calls`;
* kit CRM routes still exist but are not in primary navigation.

Domain models **Company**, **Employee**, and **Call** exist. Audio is stored on a **private** Laravel disk (`calls` → `storage/app/private/calls`). Public uploads reuse that disk and are **not** streamed to anonymous users. There is still **no** transcription or LLM pipeline.

Audio path pattern on the `calls` disk:

* admin: `{company_id}/{year}/{month}/{uuid}.{ext}`
* public: `public/{year}/{month}/{uuid}.{ext}`

Original filename is metadata only (DEC-014). Successful upload sets status `uploaded`, not `completed` (DEC-015). `CallProcessingPipeline` exists as a no-op hook; it is not dispatched into processing in this phase.

## Target runtime shape (planned)

Stay inside Laravel unless proven otherwise:

```
Browser
  → nginx
    → PHP-FPM / Laravel
      → MySQL
      → private local disk `calls` (Phase 2; object storage still TBD)
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

Status values for a call: `pending` → `uploaded` → `processing` → `completed`, or `failed`. After Phase 2 upload the record stays `uploaded` until a later phase starts processing.

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
| Audio files | Private local disk `calls` (`storage/app/private/calls`) | Object storage (S3-compatible) **TBD** |
| Transcripts | Not implemented | Table vs JSON. **TBD** — [DATA_MODEL.md](DATA_MODEL.md) |
| Company knowledge | Not implemented | Documents + optional RAG. **TBD** |

## Frontend architecture

Current: Inertia React + Vite + Ziggy.

* Public product UI uses `PublicLayout` and `Pages/Public/Home` (upload + report shell). It is not the admin panel.
* Admin UI remains Custom Admin Kit layouts (`AdminLayout`, `/dashboard`, CRUD).
* Same Laravel/Inertia app; no separate frontend.

## Isolation on the server

This project must not affect other sites on the same host.

Constraints (accepted operationally during deploy):

* project files only under `/var/www/sales`;
* dedicated MySQL database `sales` (production) and `sales_testing` (PHPUnit only, isolated MySQL user);
* dedicated nginx vhost `sales.owlsolutions.net`;
* no edits to other nginx sites, other `.env` files, or other databases;
* nginx `reload` after `nginx -t`, not a casual `restart`.

## Testing isolation

PHPUnit must never be able to open production `sales`.

* database name: `sales_testing`
* MySQL user: `sales_testing` (privileges only on `sales_testing.*`)
* credentials: local `.env.testing` (gitignored); `.env.testing.example` has no password
* `App\Support\TestingDatabaseGuard` hard-fails if `APP_ENV` is not `testing` or `DB_DATABASE` is `sales`
* bootstrap: `tests/bootstrap.php` loads `.env.testing` and forces the testing database/user before Laravel boots

See DEC-021 and DEC-022.

## What not to do yet

* Do not add microservices “for AI cleanliness.”
* Do not train a custom model.
* Do not pick STT/LLM providers in code until a decision is recorded.
* Do not migrate kit CRM tables away until Phase 1 adaptation is specified.

## Open architecture questions

* Queue driver for production audio jobs.
* Audio object storage.
* Whether kit AI settings screens will wrap the product’s LLM/STT keys or a separate config will be used.
* Idempotency and retry policy for provider calls.
