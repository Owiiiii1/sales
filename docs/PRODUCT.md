# Sales Analyzer — Product Concept

This document describes the product we intend to build. It is not a description of the current UI.

The live site has a **main analyzer workspace** at `/` for uploading a sales call (generic or company-specific), plus an admin at `/login`. Transcription is queued through ElevenLabs Scribe v2. Admins configure the STT key and LLM providers in Settings without editing `.env`. The operator selects a Company (optional) and Employee (optional) before upload. Generic AI sales analysis runs when no company is selected. Analyzer uploads with a Company use that company’s knowledge and default scorecard. See [STATUS.md](STATUS.md).

## Problem

Sales teams record calls, but they rarely get a **repeatable, company-specific** review of those calls.

Manual coaching does not scale. Generic transcription tools do not know the offer, the script, or the scorecard. Generic “AI summaries” do not tell a manager which sales behavior failed.

## Product thesis

If we combine:

1. a reliable transcript with speakers;
2. structured sales analysis;
3. company knowledge;
4. an explicit scorecard;

then a single uploaded call can produce a coaching-quality report.

## Users (planned)

| Role | Intent | When |
|---|---|---|
| Anonymous / light user | Upload a call and get a report | Phase 1 (MVP) — **superseded as product framing**: `/` is an operator analyzer (DEC-057), still unauthenticated technically |
| Logged-in user | History, saved company context | Phase 2 |
| Employee (no login required) | Person whose calls are scored | Phase 2 domain |
| Company admin / owner | Team quality, scorecards, knowledge | Later phases |
| Sales lead | Rankings, trends, coaching | Phase 8 |

Exact role model is **Open question**. Do not invent permissions yet.

## MVP / Phase 1

First public version should be **as simple as possible**.

### What MVP should do

Main screen: **Upload Call**.

The user uploads audio:

* mp3
* wav
* m4a
* other formats **TBD**

After processing, the user gets an **analysis page**.

A simple company-context input before analysis is allowed. That may be:

* a short form on the upload screen; or
* a separate basic form.

Which of those is **TBD**.

### What MVP should not require

* a mandatory personal cabinet;
* accounts (may exist internally for operators; not required for the public flow);
* CRM integrations;
* telephony ingestion;
* team dashboards;
* custom scorecard builder in the UI.

### MVP success looks like

A person can upload one call, optionally give company/product context, and receive a structured sales analysis they can act on.

Exact public URL, branding, and whether analysis is behind a gate (email, waitlist, payment) are **Open questions**. The live analyzer is `https://sales.owlsolutions.net/` (DEC-017, DEC-057). Generic analysis remains available when no Company is selected. Company-specific analysis is chosen explicitly before upload.

## Phase 2

Accounts and persistence:

* users;
* companies;
* personal accounts;
* call history;
* employees;
* stored company context.

This turns a one-off upload tool into a product that remembers who called, for which company, and what the analysis was.

Order of “public upload” vs “accounts first” may change. See [ROADMAP.md](ROADMAP.md).

## Later capabilities (not a final backlog)

These are possible later capabilities. **Do not treat the list as committed scope.**

* automatic import of calls;
* Bitrix24;
* Twilio;
* 3CX;
* Aircall;
* RingCentral;
* other CRM / telephony systems;
* manager dashboard for a team lead;
* ranking and dynamics of managers;
* coaching workflows;
* recurring weaknesses;
* team analytics;
* comparison between employees or periods;
* automatic recommendations.

## Product surfaces (planned)

| Surface | Purpose | Status |
|---|---|---|
| Upload Call | Submit audio | Implemented (analyzer + admin) |
| Analysis report | Deep coaching report for one call | Implemented (Phase 4–7) |
| Company context form | Admin Sales Knowledge (not a public prompt field) | Implemented (Phase 5) |
| Admin / operator UI | Adapted from Custom Admin Kit | Implemented (Phase 1) |
| Settings | Pipeline health, Transcription, AI providers, analysis behavior | Implemented (Phase 7.1) |
| Personal cabinet | History, companies, employees | Planned (Phase 8) |
| Knowledge admin | Scripts, products, objections | Implemented (Phase 5) |
| Scorecard admin | Criteria and weights | Implemented (Phase 5) |
| Team analytics | Dashboard, company analytics, employee analytics | Implemented (Phase 6) |

## What we reuse from the admin kit

**Planned for a later phase, not decided in detail:**

* authentication and users;
* settings shell;
* layout, language switcher, profile;
* possibly AI provider settings UI (the kit already has AI/Telegram settings screens).

**Not the product domain:**

* Customers
* Orders
* Services
* Staff
* Calendar

Those kit CRM modules must not be mistaken for Sales Analyzer entities. Mapping (for example Staff → Employee) is **Open question** and must not be assumed.

## Language and markets

The current admin kit supports `en`, `ru`, `uk`.

Product language for reports, prompts, and public UI is **TBD**.

## Monetization

**Open question.** Not defined.

## Open product questions

* Is MVP fully public, invite-only, or internal-first?
* Is company context a free-text blob in MVP or a small structured form?
* Do we show a demo report without upload?
* Retention period for uploaded audio. **TBD** (legal/privacy).
* Consent / recording-legality UX. **TBD**.
