# No string in the admin app or the layout editor can be translated

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`makepot` in the root `package.json` runs `wp i18n make-pot` with `--exclude=node_modules,apps,tests,assets,…`, so neither the SPA sources in `apps/` nor the built bundles in `assets/` are scanned. `apps` has been excluded since `9b620fe`. `packages/` is scanned, but the installed WP-CLI 2.9.0 parses only `.js` and `.jsx` files, so the `.ts` and `.tsx` sources in `packages/blocks/src` yield nothing. No step runs `wp i18n make-json`, and `languages/` holds no JSON files.

Loading is already wired: `RegisterProd` and `RegisterDev` call `wp_set_script_translations()` for the SPA, and each block's `block.json` sets `textdomain`.

`languages/yaycheckout.pot` was last generated on 2026-09-16, in `a1a7da2`, and holds 22 entries. It lacks SPA strings such as "Your layouts" (`apps/admin/src/pages/layouts/LayoutsPage.tsx`) and "Import layout" (`apps/admin/src/pages/tools/import-modal/ImportModal.tsx`). It lacks editor strings such as "Email is always required." (`packages/blocks/src/field/edit.tsx`, `packages/blocks/src/email/edit.tsx`). It is stale for PHP too: "Email address" from `packages/blocks/src/email/render.php` is missing, and of the 13 `block.json` titles only the Checkout block's is there. Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A store whose admin runs in another language sees the YayCheckout admin and the layout editor in English. A translator working from the shipped `.pot` has nothing to translate, and even a `.po` that held the strings would reach no script, since no JSON files are built.

## Suggested fix

Build the `.pot` from code WP-CLI can parse, whose references match the scripts WordPress loads. The built bundles keep their calls in a form WP-CLI reads: `ah.__("Your layouts","yaycheckout")` in `assets/dist/admin/js/main.js`, and `(0,i.__)(…)` in the block bundles. So one option is to stop excluding `assets`, scan `assets/dist` after a build without the source maps, and keep excluding the `.ts` sources. Check that translator comments survive minification. Then add `wp i18n make-json languages --no-purge`, so each JSON file is named after the script that loads it. Say when `makepot` runs: in the release, or as a documented step before it.

## Done when

- [ ] After `pnpm makepot`, `grep -c '^msgid "Your layouts"$' languages/yaycheckout.pot` prints 1.
- [ ] After `pnpm makepot`, `grep -c '^msgid "Email is always required."$' languages/yaycheckout.pot` prints 1.
- [ ] With a yaycheckout-de_DE.po in `languages/` that translates "Your layouts", the documented JSON step run, and the site language set to Deutsch, the Layouts page shows the German heading.
- [ ] With the same setup and a translation of "Email is always required.", the Email address block's inspector in the editor shows the German text.
