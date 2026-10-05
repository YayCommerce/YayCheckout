# Harness Renewal Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief.

**Status:** active
**Goal:** replace YayCheckout's old agent and developer harness with the structure YayLoyalty uses, and leave no code comment or link pointing at the old one.
**Spec:** none, planned from the request
**Architecture:** YayLoyalty already replaced the same old harness (ClaudeKit-era docs, `code-corrector` and its owner table) between 2026-10-01 and 2026-10-05. This plan takes its shape and its checks, and writes each doc from a fresh read of this repo's code instead of porting the old text, which restated the code and drifted.
**Touches:** `CLAUDE.md`, `README.md`, `.gitignore`, `.nvmrc`, `package.json`, `.claude/**`, `docs/**`, `scripts/run.sh` (adds `backlog`), new `scripts/check-harness.sh`, and comment lines in ten PHP files and one TSX file.
**Tests:** none

## Requirements

- **Goal:** a short `CLAUDE.md` index with path-scoped rules, docs reached by "read when…" links, a committed `.claude/settings.json` with a guard hook, decision records, a backlog, the five shared `yay*` skills, a README as the front door, and `scripts/check-harness.sh` to keep it honest.
- **Out of scope:** behaviour changes, fixing checks that already fail, and the PHPUnit harness in `tests/`. Problems found on the way go to `docs/backlog/`.
- **Acceptance criteria:**
  1. `git ls-files` shows none of `docs/code-standards.md`, `docs/system-architecture.md`, `docs/codebase-summary.md`, `docs/journals/`, `.claude/skills/code-corrector/`.
  2. No code comment cites a doc path, a `§` or a feature ID.
  3. The baseline below is unchanged: same counts and exit codes.
  4. `./scripts/check-harness.sh` prints `Harness checks passed.` and exits 0.
- **Decided with the user (2026-10-05):**
  - One branch, one commit per phase, one PR into `dev/edd`. YayLoyalty's stacked per-phase PRs conflicted on every merge.
  - Decision records: the practice, the harness record, and backfills for the Free/Pro split, admin-shell scoping, a layout as one post, and ISO 8601 UTC wire timestamps.
  - `CLAUDE.md` keeps YayLoyalty's Tests rule: a test only where it guards real risk, and only after asking.
  - The local `plans/` directory is excluded in `.git/info/exclude`, not in `.gitignore`.

## Global constraints

- No behaviour change: Task 2's code edits are comments only, checked by comparing token streams with comments stripped.
- "Green" means no regression against the baseline taken in Task 1. Checks that already fail are recorded in `docs/known-issues.md`, not fixed.
- The old docs are read only at the tag `harness-v1-final`, as leads to check against the code.
- Docs cite code by path and symbol, never by line number. Code never cites a doc, a `§` or a feature ID.
- Every doc, rule, record and skill opens with **Purpose** and **Update when**.
- The shared skills are copied whole from YayCommerce/YayLoyalty and never edited here.
- pnpm needs Node 22 (`nvm use 22`). Any pnpm command may run `pnpm install`, and with it `composer install`.

## Review focus

- A rewritten comment that loses the reason its pointer stood for: each one must still say why, in the comment itself (Task 2).
- A rule glob that matches nothing, or a rule whose frontmatter fails to parse and so loads in every session (Task 6).
- A doc claim the code doesn't back: every architecture and conventions claim names a path and symbol a reviewer can open (Task 4).

---

### Task 1: Branch, baseline, tag, plan

- [x] **Step 1:** `git switch -c trace/docs/harness-renewal dev/edd`; `/plans/` added to `.git/info/exclude`.
- [x] **Step 2:** Baseline, counts and exit codes only, on `dev/edd` @ `cd6604a` with Node 22:
  - `composer lint`: exit 0, Pro boundary intact.
  - `composer test`: exit 0, "No tests executed!".
  - `pnpm --filter @yaycheckout/admin-page lint`: **exit 1**, 4 eslint warnings, Prettier issues in 3 files.
  - `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`: exit 0.
  - `pnpm build:blocks`: exit 0.
  - `bash -n` on both scripts: exit 0.
- [x] **Step 3:** `git tag harness-v1-final cd6604a` (local; the user pushes it).
- [x] **Step 4: Commit**

Stage: `docs/plans/2026-10-05-harness-renewal.md` (with `git add -f`: the old `plans/` ignore rule still hides it)
Message: `docs(harness): plan the harness renewal`

### Task 2: Remove the old harness and every reference to it

**Files:**
- Delete: `docs/code-standards.md`, `docs/system-architecture.md`, `docs/codebase-summary.md`, `docs/journals/`, `.claude/skills/code-corrector/`
- Modify: `CLAUDE.md` (stub until Task 3), `README.md` (doc links), `.gitignore` (AI block out, personal Claude files in), `package.json` (`makepot` excludes)
- Modify, comments only: `includes/Blocks/BlockRegistry.php`, `includes/Enums/ConditionType.php`, `includes/Helpers/CheckoutHookScanHelper.php`, `includes/Models/Design.php`, `includes/Renderer/CheckoutPage.php`, `includes/Renderer/CheckoutRequiredFields.php`, `includes/Renderer/PreviewRequest.php`, `includes/Services/LayoutAssignment.php`, `includes/Services/RequiredFieldOverlay.php`, `apps/admin/src/components/shared/template-picker/template-thumbnail-sketches.tsx`

- [x] **Step 1:** Delete the files and edit the config.
- [x] **Step 2:** Rewrite each comment so it states its own why in a line or less, after checking the claim in the code. Drop a pointer outright where the comment already says why.
- [x] **Step 3: Verify**

Run: `git grep -nE 'code-standards|system-architecture|codebase-summary|code-corrector|journals' -- . ':!docs/plans/'` and `git grep -n '§' -- includes apps packages templates`
Expected: no output
Run: the PHP `token_get_all` and TS printer comparisons against `origin/dev/edd`
Expected: no file reported as changed code
Run: `git check-ignore .claude/settings.json docs/plans/x.md`
Expected: no output

- [x] **Step 4: Commit.** Message: `chore(harness): remove the old harness and the comments that cite it`

### Task 3: `CLAUDE.md`, `.nvmrc`, engines

- [ ] **Step 1:** Write `CLAUDE.md`, under 100 lines: what, Commands (each one run, failing ones linked to known issues), Hard rules (each traceable to code), Tests, Git, Plans and specs, Where to look. Plain links with "read when…" triggers, no `@` imports.
- [ ] **Step 2:** `.nvmrc` with `22`; `"engines": {"node": ">=22"}` in the root `package.json`.
- [ ] **Step 3: Commit.** Message: `docs(harness): add CLAUDE.md`

### Task 4: Architecture, conventions, known issues, backlog

- [ ] **Step 1:** `docs/architecture.md`: how the pieces fit, from the code, at most 250 lines.
- [ ] **Step 2:** `docs/conventions.md`: rules the code can't show, each as Rule, Why and In code. The old owner table is not rebuilt: a TypeScript copy of PHP names its owner in a comment.
- [ ] **Step 3:** `docs/known-issues.md`: each entry reproduced, as Symptom, Cause, Workaround, Remove when. The admin lint failure from the baseline is one.
- [ ] **Step 4:** `docs/backlog/`: problems found during the audit that this plan doesn't fix, in `yaybacklog`'s item format.
- [ ] **Step 5: Commit.** Message: `docs(harness): add architecture, conventions, known issues and backlog`

### Task 5: Decision records

- [ ] **Step 1:** The decision-record practice and the harness record, restated for this repo.
- [ ] **Step 2:** Backfills, each `proposed` until the user confirms its reasons: the Free/Pro build-time split, admin-shell scoped with php-scoper, a layout as one post, ISO 8601 UTC wire timestamps.
- [ ] **Step 3: Commit.** Message: `docs(harness): add decision records`

### Task 6: Rules, settings, guard hook

- [ ] **Step 1:** `.claude/rules/` `php-standards.md`, `react-admin.md`, `blocks.md`, `release.md`, each with quoted `paths` globs that match tracked files.
- [ ] **Step 2:** `.claude/settings.json`: an allowlist for the routine checks, the PreToolUse guard, `includeGitInstructions: false`.
- [ ] **Step 3:** `.claude/hooks/guard-generated-paths.sh`, blocking `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/` and `release/`.
- [ ] **Step 4: Verify**

Run: the guard with a `file_path` in each generated directory, one spelled `includes/../vendor/x.php`, and `includes/x.php`
Expected: exit 2 for each generated path, exit 0 for `includes/x.php`

- [ ] **Step 5: Commit.** Message: `docs(harness): add rules, settings and the generated-path guard`

### Task 7: Shared skills, `run.sh backlog`, README

- [ ] **Step 1:** Copy `yaybrainstorming`, `yayplan`, `yayplancheck`, `yaytidy` and `yaybacklog` unchanged from YayCommerce/YayLoyalty `dev/edd`.
- [ ] **Step 2:** `./scripts/run.sh backlog`, bash 3.2-safe.
- [ ] **Step 3:** Rewrite `README.md` as the front door: requirements, onboarding, daily workflow, agent tooling, keeping the harness current.
- [ ] **Step 4: Commit.** Message: `docs(harness): add the shared skills, the backlog command and the README`

### Task 8: Harness check and final validation

- [ ] **Step 1:** `scripts/check-harness.sh`, adapted from YayLoyalty's.
- [ ] **Step 2: Verify**

Run: `./scripts/check-harness.sh`
Expected: `Harness checks passed.`, exit 0
Run: the baseline again
Expected: identical counts and exit codes

- [ ] **Step 3:** Set this plan's Status to `done` with the date.
- [ ] **Step 4: Commit.** Message: `docs(harness): add the harness check`
