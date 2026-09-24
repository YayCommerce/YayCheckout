---
name: source-of-truth-check
description: Find code in a diff that re-declares a rule another place already owns (storage keys, settings defaults, design bounds, publish rules, extra-field Keys, helpers, comments restating a rule), in the same language or across PHP↔TS, plus readability slips in added lines. Reports first, fixes only on approval. Use before committing a feature, or when asked to dedup, find duplicates, check source of truth, or find redundant code.
argument-hint: "[path | --base <branch> | --all]"
---

# Source-of-truth check

The rule owners live in `docs/code-standards.md` § Single source of truth. Read them from there every run; never keep a copy here.

**Do not edit any file until step 7's approval.** Report only.

## 1. Scope

- No argument: everything the current branch adds on top of `dev/edd` — added lines from `git diff dev/edd...HEAD` and `git diff HEAD` (committed and uncommitted), plus every line of new files from `git ls-files --others --exclude-standard` (the diff omits untracked files). On `dev/edd` itself this is just the uncommitted work.
- `--base <branch>`: the same, against another base (`git diff <branch>...HEAD`).
- A path: every line in that file or directory.
- `--all`: walk each row of the owner table and grep the repo for copies of its rule; skip step 3.

Skip `vendor*/`, `node_modules/`, `assets/dist/`, `plans/`, and `.claude/`.

## 2. Load the rules

Read `docs/code-standards.md` § Single source of truth (owner table) and § Readable code.

## 3. Collect candidates from the scoped lines

- String literals that name stored or wired things: meta keys, option names, block names, REST routes, setting keys.
- Numbers or arrays that look like a bound, default or allow-list (`max: 24`, `'default' => 60`, `[15, 30, 60]`).
- Rule logic: validation, allow-lists, status or "may publish" checks, regexes.
- New functions or helpers.
- Comments or docs that explain a rule (why a Key looks the way it does, what publishing needs).

## 4. Find the owner

For each candidate:

1. Match it to a table row by what it decides.
2. `grep -rn` the literal, the function's distinctive expression, or the rule's wording across `includes/ apps/admin/src packages/blocks/src assets/js docs/ uninstall.php`. Match on literals, distinctive expressions and phrases, never on common identifiers like `id` or `key`.
3. A new function whose body computes what an existing exported function computes is a duplicate of that function, whatever its name.

## 5. Classify

- **Owner copy** — the candidate re-declares what the owner decides instead of calling or importing it. Finding.
- **Allowed mirror** — the file is listed as a mirror for that row. Check it agrees with the owner; a mismatch is drift. A new setting or token added consistently to both owner and mirror is not a finding.
- **Unrelated** — drop it.

A rule with no table row but two deciders in the code is still a finding; mark it `(no row)`.

## 6. Report

Use exactly this format. Omit empty sections. Nothing found → `No duplicates.`

```
Duplicates
- path:line → duplicates <Owner> (<kind>) → fix: <call owner | import constant | delete + pointer>
Mirror drift
- path:line ↔ <owner>: <what differs>
Readability (added lines only)
- path:line → <rule from § Readable code>
```

Readability applies only to lines the diff added, and only the four rules in § Readable code. With a path or `--all`, skip it: existing code is not held to those caps.

## 7. Fix on approval

Ask with `AskUserQuestion`: fix all / pick which / none. Then edit, and verify only what changed, one command at a time:

- PHP changed: `composer lint`, then `composer test`.
- TS in `apps/admin` changed: `npx --no-install eslint <files>` and `npx --no-install tsc --noEmit` from `apps/admin`.

Use `npx`, not `pnpm`: corepack's pnpm is broken locally.

## 8. New owner

For a `(no row)` finding, propose the row (rule · owner · allowed mirrors). Add it to the table in `docs/code-standards.md` only after the user approves.
