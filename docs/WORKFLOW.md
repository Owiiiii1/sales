# Sales Analyzer — Team Workflow

## Roles

| Role | Who |
|---|---|
| Project Manager and Tester | The user |
| Technical Lead | ChatGPT |
| Programmer | Cursor |

## Cycle

1. Technical Lead writes a concrete task (scope, constraints, done criteria).
2. Cursor implements it.
3. Cursor tests what can be tested in this environment.
4. Cursor updates project documentation under `docs/` when the change affects product, architecture, status, or decisions.
5. Cursor updates **`REPORT.md`** as the report for **this** completed stage (it may be overwritten each stage).
6. Cursor commits and **pushes `main`** to GitHub.
7. Cursor replies to the user with exactly: `готово`
8. The user tells the Technical Lead: `готово`
9. Technical Lead reads GitHub (`REPORT.md` and the diff), compares code to the report, accepts or sends the phase back, then writes the next task.

## Source of truth

**GitHub `main`.**

Not the Cursor chat.
Not unpushed local files.

If it is not on `https://github.com/Owiiiii1/sales.git` `main`, it is not done.

## REPORT.md vs docs/

| File | Role |
|---|---|
| `REPORT.md` | **Current stage report** for the Technical Lead. Overwritten each phase. |
| `docs/*` | **Durable** project knowledge (product, architecture, decisions, roadmap, status). |

Do not put lasting product definition only in `REPORT.md`.

Do not use `REPORT.md` as a substitute for [DECISIONS.md](DECISIONS.md) or [STATUS.md](STATUS.md).

## What Cursor must verify before `готово`

* Task constraints respected (especially “do not change X”).
* Secrets not committed (`.env`, passwords, API keys, tokens, `APP_KEY`).
* Isolation: no edits outside `/var/www/sales` unless the task explicitly requires this project’s nginx/DB only.
* `REPORT.md` matches what was actually done.
* Push succeeded.

## Isolation (server)

Other projects live on the same server. Sales Analyzer work stays in:

* `/var/www/sales`
* database `sales`
* nginx site `sales.owlsolutions.net`

Do not change other vhosts, other databases, global PHP/Node/Composer/MySQL versions without an explicit need recorded in the task.

After nginx edits: `sudo nginx -t`, then `sudo systemctl reload nginx` (not `restart` unless required).

## Documentation rules

* Use `TBD`, `Open question`, `Planned` when something is not decided.
* Do not mark Open items as Accepted.
* When a decision is made, add `DEC-xxx` in [DECISIONS.md](DECISIONS.md) and adjust STATUS/ROADMAP if needed.

## Git

* Branch: `main`
* Remote: `https://github.com/Owiiiii1/sales.git`
* Commit messages: say **why**, not a file dump.

This workflow is accepted as DEC-009.
