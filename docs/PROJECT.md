# Sales Analyzer — Project Overview

## Product name

**Sales Analyzer**

Working name. It may change later. Until a rename is decided, use this name in code, docs, and UI copy unless a later decision says otherwise.

## What we are building

Sales Analyzer analyzes **phone conversations of sales managers**.

It is not a general-purpose meeting transcriber. The product’s job is to evaluate **how well a sale was conducted**, using:

* the conversation itself;
* the company that made the call;
* the product or service being sold;
* the client and the goal of the call;
* the company’s sales rules, scripts, and methodology;
* a scorecard with explicit criteria.

Long-term direction: an **AI Sales Intelligence / Sales Coaching** platform.

That long-term direction is a product intent, not a commitment to a specific feature set or timeline.

## Why it exists

A transcript or a generic AI “call summary” is not enough.

A useful analysis must answer questions like:

* Did the manager discover the real need?
* Did they present the right offer for this company?
* Did they handle objections the way this company expects?
* Did they miss buying signals?
* Was the next step clear and useful?
* What should the manager do differently next time?

The same call can be “good” for one company and “bad” for another. Company context is part of the product, not an optional extra.

## Core idea (first version)

Basic first-version flow:

1. The user opens the site.
2. The user uploads an audio file of a phone conversation.
3. The system transcribes the audio.
4. The system identifies participants (speaker diarization).
5. The system analyzes the conversation as a sales call.
6. The system uses context of the specific company.
7. The system produces a structured report.

## Value proposition

**Not** “turn speech into text.”
**Not** “give a generic AI grade.”

**Yes:** score and explain the quality of the sale with company, product, client, goals, rules, scripts, methodology, scorecard, and concrete manager mistakes.

## What this repository is today

This repository currently hosts a **deployed Laravel 13 application** with **OwlSolutions Custom Admin Kit v0.5.0** plus Sales Analyzer admin foundation (Companies, Employees, Calls) and **private audio upload**.

Transcription, AI scoring, and public upload are **not** built yet.

See:

* [STATUS.md](STATUS.md) — what is actually running;
* [ROADMAP.md](ROADMAP.md) — planned phases;
* [DECISIONS.md](DECISIONS.md) — accepted vs open decisions.

## Audience

Primary users (planned, not all in MVP):

* sales managers (coaching on their own calls);
* sales leads / owners (quality and team trends);
* later: trainers, QA, operations. **TBD.**

MVP is intentionally simpler: a public-style upload and analysis flow without a required personal cabinet. See [PRODUCT.md](PRODUCT.md).

## Out of scope for now

Do not treat the following as current product:

* the kit CRM screens (Customers, Orders, Services, Staff, Calendar);
* Telegram bot;
* configured AI providers in admin settings;
* call upload, transcription, or analysis features.

Those kit screens exist because the admin kit was installed as a standard base. They are **not** the Sales Analyzer domain.

## Related documents

| Document | Purpose |
|---|---|
| [PRODUCT.md](PRODUCT.md) | MVP, later phases, product surfaces |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Current stack and intended runtime shape |
| [AI_ANALYSIS.md](AI_ANALYSIS.md) | Analysis engine, knowledge, scorecards |
| [DATA_MODEL.md](DATA_MODEL.md) | Planned domain entities |
| [ROADMAP.md](ROADMAP.md) | Phased delivery |
| [DECISIONS.md](DECISIONS.md) | Decision log |
| [STATUS.md](STATUS.md) | Factual current state |
| [WORKFLOW.md](WORKFLOW.md) | How the team ships work |
