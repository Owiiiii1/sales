# Sales Analyzer

AI-assisted analysis of **sales phone calls**: transcription, speakers, company context, scorecards, and a structured coaching report.

Working name. It may change.

This repository is **not** a generic Laravel demo. Product intent is in [`docs/PROJECT.md`](docs/PROJECT.md).

## Current status

**Phase 0–2.1 are complete.** `/` is the public analyzer. Admins sign in at `/login`.

Public upload stores audio privately and returns an opaque token. **STT/LLM analysis is not connected yet.** Kit CRM screens still exist as hidden legacy routes.

Details: [`docs/STATUS.md`](docs/STATUS.md) · roadmap: [`docs/ROADMAP.md`](docs/ROADMAP.md)

## Stack

* Laravel 13 / PHP 8.5
* MySQL 8
* Inertia + React + Vite
* OwlSolutions Custom Admin Kit v0.5.0
* nginx, Ubuntu 24.04

## Live site

[https://sales.owlsolutions.net](https://sales.owlsolutions.net)

Guest `/` is the public analyzer. Admin login is `/login`. Protected admin routes redirect guests to `/login`.

## Documentation

| Doc | Contents |
|---|---|
| [docs/PROJECT.md](docs/PROJECT.md) | What and why |
| [docs/PRODUCT.md](docs/PRODUCT.md) | MVP and later product |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Stack and runtime shape |
| [docs/AI_ANALYSIS.md](docs/AI_ANALYSIS.md) | Analysis engine, knowledge, scorecards |
| [docs/DATA_MODEL.md](docs/DATA_MODEL.md) | Planned entities |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Phases |
| [docs/DECISIONS.md](docs/DECISIONS.md) | Decision log |
| [docs/STATUS.md](docs/STATUS.md) | What is actually running |
| [docs/WORKFLOW.md](docs/WORKFLOW.md) | How the team ships |

`REPORT.md` is the **latest stage report** for the Technical Lead. It is overwritten each phase. Durable facts belong in `docs/`.

## Development workflow

Technical Lead (ChatGPT) writes the task → Cursor implements, updates `docs/` + `REPORT.md`, commits, pushes `main`, replies `готово` → PM tells the Lead `готово` → Lead reviews **GitHub**, not the chat.

Full rules: [`docs/WORKFLOW.md`](docs/WORKFLOW.md)

## Security

Do not commit `.env`, database passwords, `APP_KEY`, API keys, or tokens.

Production secrets stay on the server in `/var/www/sales/.env` (gitignored).
