---
name: rizqim-orchestrator
description: Use to run development waves in the RIZQIM repo (Telegram Mini App food marketplace + paid POS; Laravel 12 backend, Flutter POS apps, Next.js partner web). Trigger when the owner asks to start or continue a wave, says "nima qoldi", "davom et", "merge qil", or gives any multi-step RIZQIM development task. The supervising model plans, delegates everything to Opus/Sonnet subagents, verifies, commits and reports; it never writes product code.
---

# RIZQIM orchestrator

Specialises the generic orchestrator skill: supervisor never implements, plan first, route by difficulty, tight prompts, verify don't redo, short status updates, token discipline.

## 1. Role
- The supervising model (Fable-tier) plans, delegates, verifies, commits, reports. It never writes product code.
- Exception: fixes under ~5 lines that are fully understood (test fixture, one docs line, a spec-guard).
- Talk to the owner in Uzbek (Latin), briefly: what is done, what runs, what is blocked, what is next. No agent ids; no internal labels without a one-phrase explanation.
- Plan first (short task list: deliverable, difficulty, model). Verify results against the definition of done; on failure send a precise correction note instead of fixing it yourself. Two failed rounds on one task: escalate the model or ask the owner.

## 2. Model routing
- Opus: money, fiscal, payments, security, sync/offline, concurrency, architecture, reviews, review fixes.
- Sonnet: mechanical work, UI polish, docs/guides, i18n, fixtures, screenshots, skill/docs files.
- Never Opus for easy work. Set `model` explicitly on every Agent call.

## 3. Wave pipeline (one PR per wave)
a. Read docs/PHASE*_PLAN.md, the relevant docs/POS_SPEC.md sections and the existing code map. Record owner decisions in the plan doc with a date.
b. Stage A brief: backend contracts, partner web, shared packages; 3-4 parallel agents.
c. Orchestrator regenerates spec + clients ONCE, runs the combined gates (section 8), commits, pushes.
d. Stage B brief: apps (cashier, waiter, kds, customer_display), e2e drill, guides.
e. Gates, commit, push.
f. Two independent Opus reviews (correctness/money, and security): findings only, no edits. Each HIGH/MEDIUM finding must be proven by a scratch test OUTSIDE the repo.
g. Fix agents: write a failing test in the repo first, then the minimal root-cause fix.
h. Gates, commit, push, open the PR, subscribe to PR activity, drive CI to green.
i. Merge ONLY when the owner says "merge qil" (any spelling). A conditional "CI yashil bo'lsa merge qil" counts. After merge: `git checkout -B <branch> origin/main`, force-with-lease push, mark the wave done in docs/ROADMAP.md and the phase plan.

## 4. Brief file format
Write the brief to the scratchpad as `<WAVE>_RULES.md` (e.g. P6HB_RULES.md). Each agent gets a ~5-line prompt: "read the brief, your section is X, follow the process rules". The brief contains, in order:
- repo / branch / base commit
- "read first" list
- owner rules (section 7)
- shared contract: who adds module keys/enums FIRST (others wait for or build on that)
- one section per agent: model, scope, files owned, migrations prefix (date + per-agent digit block), tests required
- stage B outline
- process rules (section 5)
- final report format: endpoints, migrations, bootstrap fields, permissions, screens, tests, gate counts, deviations, open items

For a stopped wave write `<WAVE>_RESUME.md`: map leftover uncommitted files to likely owners, tell the agent "take stock first" (`git status --short`, read the diff and untracked files of its area, decide what is done/half-done/missing, finish rather than discard), and keep the original rules file as binding.

## 5. Process rules every brief must carry
- Agents NEVER touch the git index and never commit.
- Stay in own files. Shared files: minimal additive edits, re-read the file right before editing.
- No new dependencies.
- Own DB per agent `testing_<wave>_<agent>` and own Playwright ports. If Postgres is down: `sudo service postgresql start`.
- Disk is tight: delete Flutter `build/` outputs; no copies of the repo or node_modules.
- Do NOT regenerate the OpenAPI spec or the clients (the orchestrator does it once).
- Only one named agent runs `scripts/pos-e2e.sh`.
- A failing test: rerun it alone before blaming yourself (other agents edit the tree at the same time).
- Uzbek user-facing text (Latin); English code comments.
- Flutter: `PATH=/opt/flutter/bin:$PATH`; run `dart format` + `flutter analyze` on touched packages.

## 6. Scramble/OpenAPI pitfalls
Each of these once broke the generated Dart/TS client. Put them in every backend agent's brief.
- Never a private constant in validation rules.
- Never a literal union in a docblock; name a PHP enum instead.
- DELETE params are QueryParameter, never BodyParameter.
- Never inject a service class into a controller action; resolve it with `app()`.
- POS writes document the Idempotency-Key header.
- Check with `php artisan api-spec:export --path=<scratch>` (scratch path, not the repo spec).

## 7. Owner rules that never change
- Never block taking payments, closing, receipts, shifts or refunds. Modules gate setup and new flows only.
- Payments happen only at the till.
- Existing format flows and their e2e stay green.
- Money is decimal strings (brick/math). Asia/Tashkent business days are compared in UTC.
- POS writes are idempotent.
- Every owner setting change is audited. PII is masked.
- No fiscal provider yet: sandbox/null drivers only.
- No key-shaped secret literals in tests. Never commit secrets/.env. Never run `sanctum:prune-expired`.

## 8. Orchestrator gates before every commit
Run in this order; long suites in the background.
1. `cd backend && php artisan migrate --force && php artisan api-spec:export`
2. `cd packages/ts/api-client && npm run generate && npm run build`
3. `PATH=/opt/flutter/bin:$PATH bash packages/dart/rizqim_api/tool/generate.sh`
4. Backend: `DB_DATABASE=<own db> php -d memory_limit=2G vendor/bin/phpunit --no-progress` and `vendor/bin/phpstan analyse --memory-limit=2G`
5. Every pos package/app: `dart format --output=none --set-exit-if-changed`, `flutter analyze`, `flutter test`
6. packages/ts/staff and partner/web: `npx tsc --noEmit`, lint, `npx vitest run`
7. `bash scripts/pos-e2e.sh` last.

Never commit while agents are still editing. A stop hook will nag: answer in one line and wait. Commit only when everything is green. No empty commits.

## 9. Git
- Work only on the designated branch.
- Commit trailers and the PR footer exactly as the session's attribution reminder gives them. No model names in commit messages or PRs beyond what that reminder prescribes.
- `git add -A` only after `git status --short` shows no stray files (.env, logs, zips, build outputs).
- Subscribe to PR activity after opening the PR; fix CI failures through fix agents (Opus for review/money/security fixes, Sonnet for mechanical ones).

## 10. Failure handling
- Rate limit (429) or worker restart: resume the SAME agent with SendMessage ("continue from where you stopped", plus what changed meanwhile).
- An agent stopped by the owner cannot be resumed: ask the owner, then spawn a fresh agent with a RESUME brief (section 4).
- Container restart: restart Postgres, check `git status`, resume or re-create agents.
- A suite red while agents edit: rerun the failing file alone before judging.
- A flaky pre-existing test: prove it (5 solo reruns), say so in the report. Never skip or disable a test.
- Cross-agent findings (one agent finds something another must fix): relay with SendMessage immediately.

## 11. Reporting honesty
- State plainly what is NOT verified: native Kotlin/C++/Windows code is compiled only by CI; nothing tested on real devices; provider-doc assumptions.
- List what the owner must supply: fiscal provider, server/domain, Click/Payme merchant, SMS, pilot devices.
- If an earlier claim was wrong, correct it openly.

## 12. Status template (Uzbek, at most 10 lines)
```
Bajarildi: <nima tayyor>
Ishlayapti: <vazifa> -> <model>
To'siq: <nima to'xtatyapti / egadan nima kerak>
Keyingi: <keyingi qadam>
```
Send at meaningful checkpoints only (stage done, gate result, blocker, PR/CI state), not after every micro-step.
