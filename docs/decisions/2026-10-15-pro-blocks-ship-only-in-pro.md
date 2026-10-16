# Pro blocks and the templates built from them ship only in the Pro zip

**Purpose:** why multi-step checkout (the step blocks) is Pro, how a Pro block is kept out of the Free zip, and what Free shows in its place.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

Superseded by [2026-10-16-pro-template-previews-in-free.md](2026-10-16-pro-template-previews-in-free.md).

## Context

- [The build-time Free/Pro split](2026-10-05-build-time-free-pro-split.md) withholds Pro features in PHP, and says every Pro feature needs a seam: a hook core fires, or a filter core applies with a working default. Until now every Pro feature was PHP (REST routes, assignment, export and import), and every block shipped in both editions.
- Multi-step checkout is six blocks (Steps, Step, Next Step Button, Previous Step Button, Go to Step, Steps Progress), each with an editor script, a storefront script and a `render.php`, plus the templates built from them. The only gate was the template lock, `FREE_UNLOCKED_COUNT`, which happened to cover every step template while leaving the blocks themselves free in the inserter.
- The ask: Free has no step block code at all, so a merchant who wants steps must upgrade; Free's inserter still lists the step blocks as Pro; and Free's template list shows only the templates it can build, all usable.
- Options turned down:
  - hiding the blocks from Free's inserter while still shipping them: the code would still be in Free, and pasted or downgraded markup would keep working;
  - checking `Plan::is_pro()` in each step `render.php`: the code would still ship, and every new Pro block would need the same check;
  - keeping the step templates listed in Free as locked teasers: their markup is Pro blocks Free can't render.

## Decision

- A Pro block lives in `packages/blocks/src/pro/<block>/` and builds into `assets/dist/blocks/pro/<block>/`. Core's `BlockRegistry` registers only `assets/dist/blocks/*/`, and Pro adds the `pro/` directories through the `yaycheckout_built_blocks` filter. PHP that only a Pro block's `render.php` calls lives in `includes/Pro/`. A Free block's `render.php` must not name `YayCheckout\Pro`, and `scripts/check-pro-boundary.sh` checks it.
- A template that uses a Pro block is marked `pro` in `LayoutTemplateCatalogue::catalogue()` and lives in `templates/layouts/pro/<slug>/`. Free neither lists nor ships it, and every other template is usable in Free: there is no locked template.
- `release --plan=free` deletes `assets/dist/blocks/pro/` and `templates/layouts/pro/` with `includes/Pro/`, and checks that the zip has none of them. Pro's zip must have all three.
- A Pro block the site hasn't registered is locked (`BlockRegistry::locked_blocks()`): in Free, every Pro block. The editor registers each locked block in JavaScript only, as a stub carrying just the name, labels and attributes: it is listed in the inserter under its own YayCheckout Pro category, shows an upgrade card, and keeps a downgraded layout's inner blocks so the merchant can move them out.
- `LayoutMarkupReader::publish_error()` refuses a live layout holding a locked block, so a layout holding a Pro block can't go live in Free. Any other unregistered YayCheckout block, such as one from a newer version's export, is not refused.
- Code shared with Free blocks may keep small references to Pro blocks by name (the Visibility step rule, `Renderer\LayoutSteps`, placement lists). They stand down when the blocks are absent: with no steps, step rules are skipped, and the Visibility panel doesn't offer them.

## Consequences

- Free carries no step behaviour, and the next Pro block follows the same path: a folder under `src/pro/`, its PHP under `includes/Pro/`, and its name in `BlockRegistry::PRO_BLOCKS` and `packages/blocks/src/shared/pro-blocks.ts`.
- `BlockRegistry::PRO_BLOCKS` and `packages/blocks/src/shared/pro-blocks.ts` are two copies of the same list; `scripts/check-pro-boundary.sh` (run by `composer lint`) fails when they and the folders under `packages/blocks/src/pro/` differ.
- A Pro to Free downgrade keeps the layout's content: the storefront prints an unregistered Steps or Step as its inner blocks, so the fields stay, but the layout can't be published again until its Pro blocks are removed.
- A dev tree always contains Pro, so Free's stubs, publish rule and template list can be seen only in a Free zip, or with `includes/Pro/ProServiceProvider.php` absent.
