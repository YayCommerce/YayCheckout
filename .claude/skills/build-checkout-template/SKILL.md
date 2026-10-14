---
name: build-checkout-template
description: Builds a YayCheckout layout template (templates/layouts/<slug>/markup.php and design.json) from one template in the HTML checkout-templates mock, from reading the mock to a commit on a branch. Use when the user says "build template 06", "turn the Minimal steps mock into a template", "add the Accordion one-page layout", or points at the mock HTML, even without the word template. Do not use to tweak one block or to restyle an existing template without the mock.
# Purpose: the workflow for turning one template of the HTML mock into a layout template: extract, map,
#   gaps, markup, register, verify, commit. It owns the mapping format, the extract and design.json scripts,
#   and the pitfalls list learned from templates 01–03. This header covers the whole skill directory.
# Update when: a template fix teaches a new pitfall (references/pitfalls.md), the files a template touches
#   change (references/checklist.md), LayoutTemplateCatalogue or LayoutDesign changes shape, or the mock's
#   bundle format changes (scripts/extract_mock.py).
---

# Build a checkout template from the HTML mock

A layout template is two files. `templates/layouts/<slug>/markup.php` holds block markup exactly as the block editor saves it, with a little PHP. `design.json` holds the layout's design tokens. `LayoutTemplateCatalogue::is_available()` makes the template available once both exist. The mock is a design handoff: an HTML bundle holding eleven numbered checkout templates. It shows what to build. It isn't code to copy, because the result must be blocks the merchant can edit.

The work happens in phases, and two of them end at a checkpoint where you stop and wait for the user. Mapping is the cheapest place to catch a misreading; a wrong mapping found in the editor costs a rebuild. Keep a todo list with one item per phase so the user can see where you are.

## Inputs

- **Mock file.** Use the path the user gives. Otherwise use `~/Downloads/WooCommerce Checkout Templates_v1.1.html` if it exists. Otherwise ask for the path; don't guess.
- **Which template.** A number (`06`) or a label (`Minimal steps`). If the user gave neither, run `scripts/extract_mock.py --mock <path> --list` and ask.
- **Work directory.** Use the session scratchpad if there is one, otherwise `/tmp/build-checkout-template/<slug>/`. The extract and the mapping go there. They aren't committed.

## Phase 0: Preflight

1. Map the template to its slug in `LayoutTemplateCatalogue::catalogue()` (`includes/Controllers/LayoutTemplateCatalogue.php`).
   - **Slug isn't listed:** it's a new slug. Ask the user where it goes in the catalogue, because the position decides Free vs Pro.
   - **`templates/layouts/<slug>/` already has files:** stop and ask whether to rebuild it.
2. Phases 0–3 only read the repo and write to the work directory. Leave git alone until Phase 4.

## Phase 1: Read the mock

```bash
python3 -I .claude/skills/build-checkout-template/scripts/extract_mock.py --mock "<mock>" <id> --out <workdir>
```

This writes two files:

- **`t<id>.html`** is the template's own page. Open it in a browser to see the design; use a screenshot if a browser tool is available.
- **`t<id>.source.html`** is its readable source:
  - inline-styled markup, with the px values, colours and gaps you need;
  - `{{ bindings }}` and `<sc-if>` / `<sc-for>`, which mark dynamic parts;
  - `data-woo-block="…"` attributes, which say which WooCommerce block a region stands for;
  - a `class Component extends DCLogic` at the end, holding the sample data and step labels.

Read the source and list the regions top to bottom, and per column if there are columns. Check whether the design has states: steps, an accordion, a collapsed summary. The logic class lists them.

## Phase 2: Map regions to blocks

List what exists before you map: `ls packages/blocks/src`, plus each `block.json` you need. The closest built template (`templates/layouts/*/markup.php`) shows the blocks in real use, with attributes the editor accepts.

Write `<workdir>/mapping.md` in this shape:

```markdown
# Template <id> · <label> → `<slug>`

Mock: <path>, template <id>. Structure: <one sentence: columns, steps, panels>.

## Regions
| # | Mock region | Block(s) | Attributes and values |
|---|---|---|---|
| 1 | Header: logo + "Step 1 of 3" | core/group (flex, space-between) › core/site-logo or core/site-title, yaycheckout/steps-progress | gap 8px; title 15px/600 |
| … |

## Design tokens (design.json)
Only the tokens the mock sets, with the value and where it came from in the mock. Remember: the page is `colours.background`; a panel's own colour goes on its block.

## Destinations
How the addresses sit for `shipping`, `billing` and `billing_only`.

## Out
Each thing no block or token can do today, and why: for example "card-number form: drawn by the gateway". Mark the ones you think are essential.

## Questions
Only real ambiguities the mock leaves open.
```

Every value in the table comes from the mock's source. A value you invented or guessed is a bug, so write `?` and put it under Questions instead.

**Checkpoint.** Show the user the mapping, the Out list and the questions, then stop. Continue only once they approve or correct it. If you run without a user, as in an eval, stop here.

## Phase 3: Gaps

For each Out item the user calls essential, the missing block feature, token, icon or CSS is its own piece of work. Propose `/yaybrainstorming` or `/yayplan` for it, and build the template without it, with the Out item noted. Template 02 was done this way: its block gaps were specced and built first, and then the template files went in. Don't grow a block inside a template branch.

## Phase 4: Write the markup

First check `git status`, and ask before working over uncommitted changes. Then branch from an up-to-date `dev/edd` as `<prefix>/feat/template-<id>-<slug>`. Use the user's own branch prefix: CLAUDE.local.md or memory may say it; if not, ask.

Read [references/pitfalls.md](references/pitfalls.md) next. Every item in it shipped once and needed a fix.

1. Start `markup.php` from `templates/layouts/classic-two-column/markup.php`:
   - its header docblock, rewritten for this template;
   - `defined( 'ABSPATH' ) || exit;` and the phpcs disable line;
   - the shared preamble: policy URLs, `$yaycheckout_link`, the terms sentence, policy links, `$yaycheckout_destination`;
   - the `$yaycheckout_address_fields` closure, unless the mapping changes the address structure.
2. Write the blocks from the mapping. Copy each block comment's attribute shape from a built template, which is already editor-valid, rather than writing it from memory. PHP is allowed only for:
   - translated text (`esc_html_e`, `yaycheckout` text domain), including inside attribute JSON;
   - the logo-or-title branch;
   - `wc_get_cart_url()`;
   - the conditional policy links;
   - icon guards;
   - the destination branches.
3. Write the design with `scripts/design_json.sh <slug> <workdir>/tokens.json`, where `tokens.json` holds the mapping's tokens. It writes every schema section and key in order and reports any token the schema drops. A drop means the mapping asked for something the schema can't store: fix the value or move it to Out.
4. Round-trip through the editor. This makes the file match what the editor saves.
   1. Create a **new** layout from the template. Its files now exist, so the library offers it. Never reuse an existing draft: autosave writes to it.
   2. Open the layout in the editor and run the validity check in [references/checklist.md](references/checklist.md).
   3. Save once from the editor. Diff `wp post get <id> --field=post_content` against `LayoutTemplateCatalogue::markup( '<slug>' )`.
   4. Every difference is the editor's own form. Port it back into `markup.php`, keeping the PHP, and repeat until the editor saves what the file prints.

## Phase 5: Register

Follow [references/checklist.md](references/checklist.md) § Files.

- **Existing slug:** add the coming-soon test slug change if it applies, and the `docs/architecture.md` sentence.
- **New slug:** also add the catalogue entry, then run `./scripts/run.sh previews` so the picker has its preview.

## Phase 6: Verify and fidelity pass

Run [references/checklist.md](references/checklist.md) § Checks, all six. Then go through [references/pitfalls.md](references/pitfalls.md) against the result. For each difference from the mock, fix it or add it to Out with a reason.

**Checkpoint.** Show the user the preview link or a screenshot next to the mock, the remaining Out list, and the check results, with any failure quoted.

## Phase 7: Commit

Invoking this skill is the user asking for the commit, so commit on the branch once the checks pass:

- Stage files by name: the template files, plus the test and doc changes you made.
- Message: `feat(templates): add template <id>, <Label>`, with a body naming the Out items. End it with the trailer that CLAUDE.md asks for.
- Each fix from the fidelity pass is its own `fix(templates): …` commit.
- Don't push or open a PR unless asked.

If a check is still failing, don't commit over it. Report the failure and ask. If it also fails on `dev/edd`, say so; it predates this work.
