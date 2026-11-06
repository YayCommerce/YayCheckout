<!--
Purpose: the rules for turning a commit range into readme.txt changelog entries for this plugin:
  what earns a bullet, what to drop, how to group and split, how the (Pro) suffix is decided,
  and the house style the 1.1.0 release establishes.
Update when: the changelog's heading or bullet shape changes, the Pro block or template lists
  move, a path stops or starts being able to produce a user-visible change (keep scripts/first-pass.sh
  in step), or a release teaches a new rule about what does or does not earn a bullet.
-->

# Changelog style

Release notes live in one place: the `== Changelog ==` section at the end of `readme.txt`.
There is no CHANGELOG.md, and nothing else in the repo carries release notes.

## The shape

```
== Changelog ==

= 1.1.0 =
* Added: A thing that did not exist before.
* Added: Order Again button on the thank-you page. (Pro)
* Updated: Something that existed and now behaves differently.
* Fixed: Something that was broken and now works.

= 1.0.0 =
* Initial release.
```

- Newest version first. Append above the previous heading, never below it.
- The heading is `= X.Y.Z =` — the version alone. **No date.** The 1.0.0 entry carries none,
  and wordpress.org does not ask for one. Do not add a date to a heading because some other
  plugin does.
- Each bullet is `* `, then its label, then one sentence ending in a full stop.
- **Every bullet carries one of exactly three labels**, spelled `Added:`, `Updated:` or `Fixed:` —
  capitalised, colon, one space, then the sentence. See the next section for which.
- No commit hashes, no PR numbers, no issue links, no contributor names. Those live in the
  commit body and in the PR, which is where a developer looks; `readme.txt` is read by site
  owners in the WordPress updates screen.
- Order within a version: all the `Added:` bullets, then all the `Updated:`, then all the
  `Fixed:`. Within each group, the one a site owner is most likely to notice goes first.
- **The 1.0.0 entry stays unlabelled.** `* Initial release.` is history, and history is never
  rewritten — not to add a label, not for anything.

## Which label

Decide from what the bullet does to a site owner's installation, not from the commit's
Conventional Commits type — a `feat(...)` can be an `Updated:` and a `refactor(...)` that changes
a default is one too.

| Label | The thing… | Examples |
|---|---|---|
| `Added:` | did not exist before | a new block, template, setting, condition, design token, admin screen, integration or compatibility entry |
| `Updated:` | existed, and now behaves or looks different | a changed default, a redesigned panel, a raised minimum or tested-up-to version, a reworked flow, a capability withdrawn |
| `Fixed:` | was broken, and now works | a field that stayed required, a price shown twice, a layout that broke on a theme, a gateway that stopped working |

Rules that keep it mechanical:

- **Three labels, and no others.** Never `New:`, `Changed:`, `Improved:`, `Removed:`,
  `Deprecated:` or `Security:`. A removal is an `Updated:` — the plugin now behaves differently —
  and saying so plainly in the sentence is clearer than a label nobody else uses here.
- **If a bullet needs two labels, it is two bullets.** A change that adds a setting *and* alters
  an existing default is an `Added:` and an `Updated:`; the splitting rule below already covers it.
- **`Added:` wins over `Fixed:` for something that never worked.** A feature that was shipped
  broken and is now usable for the first time reads as `Fixed:` to the author and `Added:` to the
  site owner. Write for the site owner.
- **The label goes before the sentence, the `(Pro)` suffix after it**:
  `* Added: Order Again button on the thank-you page. (Pro)`

## This repo had no house style to copy

Before 1.1.0 the only entry was `* Initial release.` for 1.0.0 — a placeholder, not a style.
The rules above, the three labels included, are the ones this skill establishes. They are a decision, not a
discovery, so they can be changed; change them **here**, in this file, and the skill follows.

## What earns a bullet

> If a site owner would not notice it after updating, it is not a bullet.

That is the whole test. Run [scripts/first-pass.sh](../scripts/first-pass.sh) over the range to
screen the commits first — it sorts them into `DROP`, `PRO?` and `BULLET?` by the paths they
touch. `DROP` is reliable: nothing under those paths can reach a site owner. The other two are
prompts to read the commit **body** and decide here.

**Earns a bullet**
- A new block, template, setting, condition, design token or admin screen.
- A change to how the storefront checkout renders, validates or submits.
- A fix to something a shopper or a merchant could hit: a field that stayed required, a price
  shown twice, a layout that broke on a theme, a gateway that stopped working.
- A compatibility status that changed for a named plugin, theme or gateway.
- A change to the minimum or tested WordPress, WooCommerce or PHP version.
- Anything that changes stored data, or that a merchant has to re-do after updating.

**Does not** — these are the `DROP` paths in the script, and the reason each one is there:
- `.github/`, `.claude/`, `.vscode/` — CI and the agent harness.
- docs, the decision records, the backlog, `CLAUDE.md`, `README.md`.
- `tests/`, `phpcs.xml`, `phpunit.xml` — tests and lint config.
- `scripts/` — build and release tooling, including `scripts/run.sh`,
  `scripts/free-admin-shell.patch`, `scripts/check-pro-boundary.sh` and `scripts/check-harness.sh`.
- `editions/` — the Free adapter is a build input; it ships in neither zip.
- `vendor/`, `vendor-prefixed/`, `assets/dist/`, `languages/` — generated output and the
  admin-shell sync, which hard rule 1 in `CLAUDE.md` forbids hand-editing anyway.
- Each template's `preview.html` and `thank-you-preview.html` — generated from the mock and from
  `design.json`.
- `composer.json`, `composer.lock`, `pnpm-lock.yaml`, `package.json`, `pnpm-workspace.yaml`,
  `scoper.inc.php`, `.gitignore`, `.nvmrc` — dependency and workspace plumbing.

Two more that no path test can catch, so check them by hand:
- **Refactors, renames, type-only changes, dead-code removal, extracted helpers** — no matter how
  large the diff, and no matter that they sit in `includes/`.
- **The previous release's own commit** (`chore(release): …`). The screen reports it as `BULLET?`
  because it edits `yaycheckout.php` and `readme.txt`, which cannot be dropped wholesale — the
  same two files carry real changes. Drop it by hand.
- **A fix to a bug that never shipped.** If it was introduced *and* fixed inside this range, the
  site owner saw neither — drop both. This is the rule most often got wrong: a `fix(...)` whose
  bug came from a `feat(...)` in the same range is not a bullet. The feature is.

## Grouping and splitting

Commits and bullets are not one to one.

- **Several commits, one bullet.** A feature that landed as a `feat` plus three follow-up
  `fix` commits is one capability to a site owner. Write the capability, in its finished
  state. Do not narrate the fixes.
- **One commit, several bullets.** A commit that adds a block *and* changes an existing
  setting's behaviour is two things a site owner notices. Split it.
- **Several commits, no bullet.** A whole branch of refactoring is nothing to a site owner.

Read the commit **bodies**, not only the subjects. This repo writes long bodies that state
the user-visible symptom, what was verified and what was deliberately left alone. The subject
is usually written for a reviewer; the symptom in the body is usually the bullet.

## The (Pro) suffix, decided from the code

`readme.txt` is copied into **both** zips unchanged, so one changelog is read by Free and Pro
site owners alike. A Pro-only bullet with no suffix advertises to a Free site owner something
their copy does not have.

The verdict comes from the authoritative Pro lists in the code, not from the diff:

1. **A block** is Pro exactly when its name is in `BlockRegistry::PRO_BLOCKS`
   (`includes/Blocks/BlockRegistry.php`).
2. **A template** is Pro exactly when its catalogue entry has `'pro' => true`
   (`includes/Controllers/LayoutTemplateCatalogue.php`).
3. **Anything else** is Pro exactly when every file that implements it lives under
   `includes/Pro/`, `packages/blocks/src/pro/` or `templates/layouts/pro/`.

The script's `PRO?` marker says only that a commit touched one of those directories: it is which
commits to check against the three lists above, never the answer.

### Do not reinstate the path-only test

The obvious rule — "a bullet is Pro when every commit behind it touches only Pro paths" — is
wrong, and was removed after being run over the last 25 commits on `dev`, where it fired **zero
times**. Every Pro block also edits the shared block registry to list itself, so Save as PDF and
Order Again would both have shipped unsuffixed in the Free changelog. That is precisely the
mis-advertising the suffix exists to prevent. Use the three lists.

That shared registry edit is also why a Pro block still earns a bullet in the Free changelog at
all: the Free build ships the block locked behind a Pro badge, so a Free site owner does see it
appear — they just cannot use it. Hence the suffix rather than silence.

Suffix spelling: ` (Pro)` after the sentence's full stop, with the label still first —
`* Added: Order Again button on the thank-you page. (Pro)`. Never `[Pro]`, `(pro)`, `Pro:` or a
leading badge, and never in place of the label.

## Zero entries is a valid outcome

If the range holds nothing user-facing, say so:

> Nothing user-facing to release — the range is CI, docs and refactors only.

Then stop and let the operator decide whether to release at all. Do **not** invent a bullet,
and do **not** write `* Minor improvements and bug fixes.` A release whose changelog says
nothing is worse than no release: it trains site owners to ignore the notes.

## Writing the sentences

- Write what changed for the reader, in the plugin's own vocabulary: layout, template, block,
  design, assignment, checkout, shopper, merchant. Not "refactored", "wired up", "hooked".
- Name the thing. "The Coupon form keeps its width on mobile." beats "Fixed a styling issue."
- Present tense for what the plugin now does; past tense only for a fix describing the old
  behaviour, and then only when the symptom needs it.
- One sentence after the label. If it needs two, it is probably two bullets.
- Do not restate the label in the sentence. `* Fixed: The Coupon form keeps its width on mobile.`
  not `* Fixed: Fixed the Coupon form's width on mobile.`
- Do not promise. No "improved performance" without a number, no "better compatibility".
