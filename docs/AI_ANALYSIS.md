# Sales Analyzer — AI Analysis

This is a design document for the analysis engine. **No AI pipeline is implemented yet.** Providers are not selected.

## Main principle

MVP does **not** train a neural network from scratch.

Use:

* a strong general-purpose **LLM**;
* **structured prompts**;
* **company context**;
* an explicit **sales methodology / scorecard**.

Fine-tuning is **not** required in the first version.

Fine-tuning may be considered later **only** after enough high-quality labeled calls exist. That is a future possibility, not a plan with a date.

See DEC-004 and DEC-006 in [DECISIONS.md](DECISIONS.md).

## Why company context is mandatory

A call analysis without knowledge of the company is **limited**.

The same phrase can be a good close for one offer and a compliance violation for another. “Did they mention the 24-hour guarantee?” is meaningless unless we know that guarantee exists.

Company-specific context is an accepted product requirement (DEC-005). How it is stored and retrieved in MVP (short form vs knowledge base vs RAG) is **TBD**.

## Pipeline (preliminary)

```
Audio
  ↓
Transcription
  ↓
Speaker diarization
  ↓
Structured conversation
  ↓
Company context retrieval
  ↓
Sales analysis
  ↓
Structured scores
  ↓
Final report
```

Each arrow may be one or more jobs. Failures should be visible as call status, not silent.

STT and diarization may be one provider call or two. **TBD.**

## Transcription and speakers

Needed for analysis:

* full text;
* who spoke (manager vs client vs unknown / other);
* timestamps when the STT provider supplies them.

If a provider cannot diarize reliably, analysis quality drops. Fallback behavior is **TBD** (manual speaker labels vs “unknown speaker”).

### Conversation metrics (possible)

These are useful if the STT/diarization output supports them. Mark unavailable metrics rather than inventing them.

| Metric | Purpose | Availability |
|---|---|---|
| Manager / client speaking ratio | Talk dominance | Depends on diarization. **TBD** |
| Interruptions | Call control / listening | **TBD** (needs overlap or turn-taking) |
| Long monologues | Presentation vs dialogue | **TBD** |
| Pauses | Awkward silence vs thinking | **TBD** |
| Question count | Discovery quality | Usually possible from transcript |
| Open vs closed questions | Discovery quality | LLM classification; rubric **TBD** |
| Talk speed | Delivery | Needs timestamps. **TBD** |
| Sentiment / emotion | Tone | Only if technically reliable. **TBD**; do not fake precision |

## What we analyze (minimum categories)

These are analysis **categories**, not a frozen scorecard. A company may weight them differently or hide some. See Scorecards below.

* opening
* rapport
* discovery
* needs identification
* questioning quality
* listening
* presentation
* value proposition
* objections
* pricing discussion
* negotiation
* closing
* next step
* call control
* client intent
* buying signals
* risks
* missed opportunities

Additional categories may appear per scorecard. Do not hard-code a single list into the long-term data model as “the only scores.”

## Multi-pass analysis

Do **not** require a multi-agent framework.

Prefer **several specialized structured LLM calls**, then a synthesis pass. This is easier to test, log, and version.

Preliminary passes (names can change):

1. **Sales technique** — discovery, presentation, close, next step.
2. **Customer intent** — interest, buying signals, timeline, authority (if evidence exists).
3. **Objections** — stated and implied; how they were handled.
4. **Company compliance** — scripts, forbidden claims, mandatory questions, offer accuracy.
5. **Conversation quality** — listening, interruptions, monologues, control.
6. **Final synthesis** — overall score, priorities, recommendations, improved phrases.

Passes may be merged in MVP if cost/latency requires it. Splitting is the **architectural preference**, not a mandate to ship six billed calls on day one.

Each pass should return **JSON with a schema**, not free-form prose only.

## Output (preliminary report structure)

* overall score
* section scores
* summary
* strengths
* weaknesses
* critical mistakes
* missed opportunities
* objections found
* buying signals
* next-step quality
* recommendations
* example improved phrases
* suggested alternative handling
* deal probability / intent — **only if** a later decision says we use that estimate

Do not present deal probability as a fact unless the product explicitly accepts that metric and its uncertainty.

Prompt version, model, scorecard version, and context version must be stored with the result so reports are reproducible. See [DATA_MODEL.md](DATA_MODEL.md).

## Company knowledge

Before analysis, the system should load **relevant** knowledge for that company (RAG / context retrieval is the preliminary approach).

Future knowledge types to store:

* company description
* products
* services
* prices
* target audience
* ICP
* USP
* competitors
* client pains
* frequent objections
* scripts
* sales methodology
* mandatory questions
* forbidden statements
* sales goals
* desired next steps
* examples of good calls
* examples of bad calls

MVP may start with a **small structured or free-text context form** instead of a full knowledge base. Full RAG is **Planned** (roadmap Phase 5), not current work.

Retrieval quality (chunking, embeddings, filters) is **TBD**.

Fine-tuning is not a substitute for this knowledge in v1.

## Scorecards

There must **not** be only one universal sales score.

A company should eventually **choose or create** a scorecard.

Each criterion may have:

* weight
* max score
* description
* critical flag
* instructions for the AI

Version scorecards. An old report should remain explainable against the scorecard that produced it.

### Example: Generic sales

* Greeting
* Discovery
* Needs
* Presentation
* Objections
* Closing
* Next step

### Example: Service business

* Identify problem
* Urgency
* Trust
* Explain service
* Handle fear/objection
* Price
* Appointment

### Example: B2B

* Business problem
* Current solution
* Stakeholders
* Budget
* Decision maker
* Timeline
* Value
* Next step

These examples are **illustrative**. They are not an approved catalog of built-in scorecards until a later decision.

Data model must allow custom criteria. See Scorecard / Scorecard Criterion / Analysis Criterion Result in [DATA_MODEL.md](DATA_MODEL.md).

## Evaluation and quality

How we will judge analysis quality (human rater set, golden calls, inter-rater agreement) is **Open question**.

Do not ship scores we cannot explain.

## Safety and product constraints

* Do not invent facts about the company that were not in context or the call.
* If evidence is missing, say so (e.g. “no pricing discussion in transcript”).
* Recording consent and PII retention are **TBD** (legal).
* Provider and prompt versions belong in stored analysis metadata.

## Open questions

* LLM provider (DEC-006).
* STT / diarization provider (DEC-007).
* Whether MVP uses one LLM pass or the preferred multi-pass split.
* Numeric scale (0–10 vs 0–100 vs weighted 0–100).
* Language of prompts vs language of the call (auto-detect **TBD**).
* Human review / override of scores.
