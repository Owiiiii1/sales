# Sales Analyzer — Decision Log

Record product and architecture decisions here.

Do **not** invent Accepted decisions. If something is not decided, status is Open.

Format:

```
DEC-XXX
Title
Status
Date
Decision
Reason
Consequences
```

Status values: `Accepted` | `Open` | `Superseded` | `Rejected`.

---

## DEC-001

**Title:** Laravel as backend foundation  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Build Sales Analyzer as a Laravel application (currently Laravel 13).

**Reason:** Team stack, Custom Admin Kit requires Laravel 13 (`illuminate/* ^13`), existing OwlSolutions hosting pattern.

**Consequences:** PHP/Eloquent/queues/Inertia are the default. A second backend is an exception, not the starting point.

---

## DEC-002

**Title:** OwlSolutions Custom Admin Kit instead of Filament  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Use `owlsolutions/custom-admin-kit` v0.5.0 as the admin UI kit (Inertia/React), not Filament.

**Reason:** Internal standard for OwlSolutions admin products; already installed on this host app.

**Consequences:** Admin UX follows the kit. Kit CRM modules are a starting point, not the product domain. Filament is out of scope unless this decision is superseded.

---

## DEC-003

**Title:** MySQL as the current relational database  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Use MySQL 8 on this server (database `sales`). MariaDB was preferred in generic hosting notes but is not installed here; MySQL is what runs.

**Reason:** Existing server standard; dedicated DB created for isolation.

**Consequences:** Schema and migrations target MySQL. Switching engines would be a new decision.

---

## DEC-004

**Title:** Do not train a separate AI model for MVP  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** MVP uses a general-purpose LLM with prompting, structured output, company context, and scorecards. No from-scratch training. Fine-tuning is not in scope until enough quality-labeled calls exist (future possibility only).

**Reason:** Speed, cost, and lack of labeled data.

**Consequences:** Quality depends on prompts, retrieval, and scorecard design. Provider choice is a separate open decision.

---

## DEC-005

**Title:** Company-specific context is required for quality analysis  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Analysis without company knowledge is considered limited. The product must be designed so relevant company context is supplied before or during scoring (form in MVP; stored knowledge / RAG later).

**Reason:** Sales quality is company- and offer-specific.

**Consequences:** Data model and pipeline include context versioning. Generic-only scoring is not the target quality bar.

---

## DEC-006

**Title:** AI / LLM provider  
**Status:** Open  
**Date:** 2026-09-09

**Decision:** Not selected.

**Reason:** Too early; kit AI settings UI is not a vendor lock-in.

**Consequences:** Do not hard-code a provider in product code until this is Accepted. Candidates may be listed as examples only.

---

## DEC-007

**Title:** STT / transcription (and diarization) provider  
**Status:** Open  
**Date:** 2026-09-09

**Decision:** Not selected.

**Reason:** Depends on language, diarization quality, cost, and privacy. Not evaluated yet.

**Consequences:** Phase 3 cannot be implemented as production integration until this closes. Conversation metrics that need timestamps/overlap remain TBD.

---

## DEC-008

**Title:** Avoid premature microservices  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Orchestrate processing inside Laravel if performance and libraries allow. Add a separate Python/AI service only for a real technical need.

**Reason:** Small team, one deployable, fewer moving parts.

**Consequences:** Queue workers in this app are the default. A microservice requires a new decision with a concrete reason.

---

## DEC-009

**Title:** Technical Lead / Cursor / PM work through GitHub and `готово`  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Source of truth is GitHub `main`. Cursor implements, documents, commits, pushes, and replies `готово`. PM tells the Technical Lead `готово`. The Lead reviews GitHub (especially `REPORT.md`) and then accepts or returns the phase.

**Reason:** Review must be of pushed code, not chat transcripts.

**Consequences:** Unpushed local work does not count as done. See [WORKFLOW.md](WORKFLOW.md).

---

## DEC-010

**Title:** Separate Company and Employee domain  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `Company` is the tenant/business domain entity. `Employee` is the person whose calls are analyzed and is **not** a system `User`. Employees do not need login access.

**Reason:** Call quality is scored per company and per salesperson. Mixing kit `customers`/`staff` or equating Employee with User would blur tenancy and coaching.

**Consequences:** New tables `companies` and `employees`. Kit CRM tables remain but are legacy. User remains the login account (`uploaded_by` on calls is optional).

---

## DEC-011

**Title:** Call status stored as string  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** `calls.status` is a string, not a MySQL ENUM. Current allowed application values: `pending`, `uploaded`, `processing`, `completed`, `failed`.

**Reason:** The processing pipeline will grow. Strings are easier to evolve than DB enums.

**Consequences:** Validation uses `Rule::in(Call::STATUSES)`. New statuses require a code change, not a schema rewrite.

---

## DEC-012

**Title:** Legacy Admin Kit CRM tables retained temporarily  
**Status:** Accepted  
**Date:** 2026-09-09

**Decision:** Keep kit tables/models/controllers/routes for `customers`, `services`, `staff`, `orders`, `order_staff`, and Calendar. Hide them from primary navigation. Do not use them in new Sales Analyzer code.

**Reason:** Removing working starter modules before the new domain is stable risks kit upgrades and unnecessary production risk.

**Consequences:** Direct URLs to legacy screens still work. They are not product IA. Deletion is a later decision.

---

## Template for new entries

```
## DEC-013

**Title:**  
**Status:** Open | Accepted  
**Date:** YYYY-MM-DD

**Decision:**

**Reason:**

**Consequences:**
```
