# Pro blocks ship only in the Pro zip, and Free previews the templates built from them as locked

**Purpose:** why multi-step checkout (the step blocks) is Pro, how a Pro block is kept out of the Free zip, what Free shows in its place, and why Free's template picker previews Pro templates without shipping them.

**Update when:** never edit it. To change the decision, write a record that supersedes it.

## Status

Proposed, 2026-10-16. Supersedes [2026-10-15-pro-blocks-ship-only-in-pro.md](2026-10-15-pro-blocks-ship-only-in-pro.md).

## Context

- [The build-time Free/Pro split](2026-10-05-build-time-free-pro-split.md) withholds Pro features in PHP, and says every Pro feature needs a seam: a hook core fires, or a filter core applies with a working default.
- Multi-step checkout is six blocks (Steps, Step, Next Step Button, Previous Step Button, Go to Step, Steps Progress), each with an editor script, a storefront script and a `render.php`, plus the templates built from them.
- The first ask: Free has no step block code at all, so a merchant who wants steps must upgrade, and Free's inserter still lists the step blocks as Pro. The superseded record also hid the Pro templates from Free, because their markup is Pro blocks Free can't render.
- The new ask: Free's template picker shows the Pro templates as locked cards, each with its preview and a Pro badge, so the preview sells the upgrade. A template's `preview.html` is the mock's page with its scripts stripped, shown in a sandboxed iframe that can't run scripts. It holds no block code and needs no Pro PHP to display.
- Options turned down:
  - hiding the blocks from Free's inserter while still shipping them: the code would still be in Free, and pasted or downgraded markup would keep working;
  - checking `Plan::is_pro()` in each step `render.php`: the code would still ship, and every new Pro block would need the same check;
  - shipping a Pro template's `markup.php` and `design.json` to Free behind a lock: Free can't render the markup, and the lock would be the only thing between Free and a Pro layout;
  - a screenshot per Pro template instead of its `preview.html`: a second generated file per template, and a still image where the other cards scroll.

## Decision

- A Pro block lives in `packages/blocks/src/pro/<block>/` and builds into `assets/dist/blocks/pro/<block>/`. Core's `BlockRegistry` registers only `assets/dist/blocks/*/`, and Pro adds the `pro/` directories through the `yaycheckout_built_blocks` filter. PHP that only a Pro block's `render.php` calls lives in `includes/Pro/`. A Free block's `render.php` must not name `YayCheckout\Pro`, and `scripts/check-pro-boundary.sh` checks it.
- A template that uses a Pro block is marked `pro` in `LayoutTemplateCatalogue::catalogue()` and lives in `templates/layouts/pro/<slug>/`. `LayoutTemplateCatalogue::templates()` lists it in both plans with `pro: true`. In Free it is `available: false`, and `assert_available()` refuses it with "This template requires YayCheckout Pro." Every Free template is usable in Free.
- Free's picker shows a Pro template as a locked card: its preview, a Pro badge and an Upgrade button, never selectable. The card carries only the name and the preview URL, the same as any upsell (hard rule 3).
- `release --plan=free` deletes `includes/Pro/` and `assets/dist/blocks/pro/`, and everything in `templates/layouts/pro/` except each template's `preview.html`. It then checks that the zip has no other file under those paths. Pro's zip must have all three.
- A Pro block the site hasn't registered is locked (`BlockRegistry::locked_blocks()`): in Free, every Pro block. The editor registers each locked block in JavaScript only, as a stub carrying just the name, labels and attributes: it is listed in the inserter under its own YayCheckout Pro category, shows an upgrade card, and keeps a downgraded layout's inner blocks so the merchant can move them out.
- `LayoutMarkupReader::publish_error()` refuses a live layout holding a locked block, so a layout holding a Pro block can't go live in Free. Any other unregistered YayCheckout block, such as one from a newer version's export, is not refused.
- Code shared with Free blocks may keep small references to Pro blocks by name (the Visibility step rule, `Renderer\LayoutSteps`, placement lists). They stand down when the blocks are absent: with no steps, step rules are skipped, and the Visibility panel doesn't offer them.

## Consequences

- Free carries no step behaviour, and the next Pro block follows the same path: a folder under `src/pro/`, its PHP under `includes/Pro/`, and its name in `BlockRegistry::PRO_BLOCKS` and `packages/blocks/src/shared/pro-blocks.ts`.
- `BlockRegistry::PRO_BLOCKS` and `packages/blocks/src/shared/pro-blocks.ts` are two copies of the same list; `scripts/check-pro-boundary.sh` (run by `composer lint`) fails when they and the folders under `packages/blocks/src/pro/` differ.
- The Free zip grows by one static `preview.html` per Pro template. A Pro template with no preview yet shows its name in the locked card.
- The SPA decides that a card is locked from the build (`isPro`) and the template's `pro` flag. PHP still owns the refusal, so a Free site that posts a Pro slug gets an error, not a layout.
- A Pro to Free downgrade keeps the layout's content: the storefront prints an unregistered Steps or Step as its inner blocks, so the fields stay, but the layout can't be published again until its Pro blocks are removed.
- A dev tree always contains Pro, so Free's stubs, publish rule and locked template cards can be seen only in a Free zip, or with `includes/Pro/ProServiceProvider.php` absent.
