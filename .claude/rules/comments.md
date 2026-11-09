---
paths:
  - "includes/**/*.php"
  - "packages/**/*.php"
  - "templates/**/*.php"
  - "YayCheckoutPluginAdapter.php"
  - "uninstall.php"
  - "yaycheckout.php"
  - "apps/admin/**"
  - "packages/blocks/**"
---

<!--
Purpose: what a comment in this repo is for, and when not to touch one.
Ownership: anything below the `your rules` marker at the foot is this repo's and is
  never regenerated; everything above it comes from the kit.
Update when: the house comment policy changes. It is the same across the portfolio, so it
  changes in the kit, not here.
-->

- **A comment carries a why the code cannot show** — a constraint, a deliberate deviation, a
  platform behaviour worked around. A comment that restates the code rots the moment the code
  moves, and the next reader deletes the line above it too.
- **A comment on code you are not changing stays as it is.** Rewording one makes the diff
  claim a change that did not happen; reviewers who learn to skip comment lines also skip the
  ones that matter. Rewrite a comment only when it is wrong, and say so in the body.
- **No comment cites a plan, a phase, a document path or section, a feature ID or an
  unnamed "decision".** The pointer breaks silently when that file moves. If the reason is
  cross-cutting, the comment states the reason itself.
- **A to-do names its topic** — `TODO(<topic>): what, and why not now`. A bare `TODO` is
  invisible to every search anyone will run for it.
- **Every suppression gives its reason** after `--`: `phpcs:ignore`, `eslint-disable`. A
  suppression with no reason is indistinguishable from a mistake, so the next person keeps it.
- **A translatable string with a placeholder gets `/* translators: … */` directly above the
  call.** Translators see the string and nothing else.

<!-- --- your rules --- -->
