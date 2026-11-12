---
area: e2e
updated: 2026-11-12
updated_ref: 66dc911
covers: [tests/e2e/]
---
# Browser tests (e2e)

> Update when: a spec, fixture, the seed, `tests/e2e/.wp-env.json` or a flow in the table below changes.

Playwright against a throwaway wp-env site that runs this checkout as `wp-content/plugins/yaycheckout`. The contract (when a flow earns a spec, how to triage a failure) is the yay kit's `reference/e2e.md`.

## Run it

| Step | Command |
|---|---|
| Install (once per checkout) | `yayh run e2e-install` |
| Build first | `YAYCHECKOUT_PLAN=pro yayh run build`. The site loads `assets/dist/`, and an unflagged build is Free, which hides the Pro admin UI. |
| Start the site | `yayh e2e env start` (main checkout: http://localhost:9680; each worktree gets its own port) |
| Run all | `yayh e2e run` |
| Run one flow | `yayh e2e run --grep @flow-checkout-coupon` |
| WP-CLI in the site | `yayh e2e cli wp <command>` |
| Wipe the site | `yayh e2e env reset` |

The specs run one at a time (`workers: 1`) because they share one site.

## The site

- **`tests/e2e/.wp-env.json` `config`:**
  - `YAYCHECKOUT_IS_DEVELOPMENT=false`, so the admin page loads the built SPA instead of the Vite server.
  - `DISABLE_WP_CRON` and `WP_HTTP_BLOCK_EXTERNAL`. The site never calls the licence server, which would drop the placeholder key, or any other outside service.
  - These apply to the tests site too (`testsEnvironment: true`), which the integration layer uses.
- **Seed (`bin/setup-env.sh`).** It runs after every start and reset:
  - the customer user, a US store, COD on, and the simple product `e2e-simple-product` at 10;
  - a flat rate of 5 on the "rest of the world" zone, so an order totals 15.00;
  - `yaycheckout_setup_done=1`, so the setup wizard modal doesn't cover the SPA;
  - a placeholder Pro licence: `yaycheckout_license_key` plus `yaycheckout_license_info` `{"license":"valid","expires":"lifetime"}`. Without a key, AdminShell sends the YayCheckout page to "Activate Licenses".
- **Users:** `admin` / `password` (default storage state) and `customer` / `password` (`customerPage`). Shopper specs run as a guest with `test.use( { storageState: { cookies: [], origins: [] } } )`.
- **Edition:** the site runs the source tree, so Pro is on (`Plan::is_pro()`). Free isn't covered here; `/yay:release`'s UI check runs the Free zip.

## Writing specs

- Tag every test `@flow-<slug>` (from the table), or `@P-<n>` / `@B-<n>` for a plan or bug.
- **Layouts:** `fixtures/layouts.ts`. `createLayout( title, template )` makes a fresh draft through `LayoutRepository`, and `activateLayout( id )` makes it live. `removeLayout( id )` deactivates, archives and deletes it, in the order the repository needs. `simpleProductId()` gives the seeded product's id. Every spec creates its own layout and removes it, so nothing depends on another spec's data.
- **Never write `yaycheckout_active_layout` directly** (hard rule 4). Activate through `activateLayout()`.

## Traps

- **wp-login race.** The login page's focus script can clear or move the first fill. `fixtures/auth.setup.ts` keeps filling until both fields hold their values.
- **Editor overlays.** The plugin's editor tour and core's welcome guide open on a fresh user. `specs/layout-editor-save.spec.ts` dismisses them with `page.addLocatorHandler( …, { noWaitAfter: true } )`. Without `noWaitAfter`, the handler waits for an element that never hides.
- **Editor canvas.** It is `iframe[title="Editor canvas"]`, not `iframe[name="editor-canvas"]`. Reach blocks by role, for example `getByRole( 'document', { name: 'Block: Place Order' } )`.
- **Settings.** `yaycheckout_settings` needs both arguments for `Settings::from_option()`. To break or set it from WP-CLI, write the whole option as JSON (`--format=json`), then delete it afterwards.
- **pnpm 11.** `e2e-install` passes `--config.strict-dep-builds=false`. `--ignore-workspace` skips the root `allowBuilds`, and wp-env's `fs-ext-extra-prebuilt` would otherwise fail the install.
- **Two sessions, one wp-env.** Starting it from two checkouts of the same path at once collides on container names and can corrupt the WooCommerce zip in `~/.wp-env/`. Start it once.

## Flows

Coverage: a spec file and its tag, `candidate` (browser-only, `/yay:e2e "<flow>"` writes it), `unit: …` or `manual: …`.

| flow | actor | priority | coverage | source |
|---|---|---|---|---|
| settings-save: toggle a setting, save, keep it after a reload | admin | critical | `specs/settings-save.spec.ts` @flow-settings-save | `apps/admin/src/pages/settings/components/GeneralTab.tsx:28` |
| layouts-activate: activate a draft from the list, then deactivate it | admin | critical | `specs/layouts-activate.spec.ts` @flow-layouts-activate | `apps/admin/src/pages/layouts/LayoutsPage.tsx:870` |
| layout-editor-save: edit a block in the embedded editor and save the draft | admin | critical | `specs/layout-editor-save.spec.ts` @flow-layout-editor-save | `packages/blocks/src/checkout/layout-actions.tsx:283` |
| checkout-place-order: the checkout prints the active layout and a guest COD order goes through | guest | critical | `specs/checkout-place-order.spec.ts` @flow-checkout-place-order | `packages/blocks/src/checkout/render.php:77` |
| checkout-coupon: the Coupon form applies and removes a coupon and the totals follow | guest | critical | `specs/checkout-coupon.spec.ts` @flow-checkout-coupon | `packages/blocks/src/coupon-form/render.php:61` |
| multi-step-checkout (Pro): steps block until valid, go back, end in an order | guest | critical | `specs/multi-step-checkout.spec.ts` @flow-multi-step-checkout | `packages/blocks/src/pro/step/render.php:60` |
| layouts-create-from-template: Add layout, name, template, open the editor | admin | critical | candidate | `apps/admin/src/pages/layouts/LayoutsPage.tsx:460` |
| publish-guard: the editor refuses to save or activate an invalid layout | admin | critical | candidate (rules: `LayoutMarkupReader::publish_error`) | `packages/blocks/src/checkout/publish-guard.ts:21` |
| activate-from-editor: Activate in the editor header, modal, snackbar | admin | critical | candidate | `packages/blocks/src/checkout/layout-actions.tsx:272` |
| design-token-persist: change Primary in Layout design, see it on the storefront | admin | important | candidate (CSS output: `unit` via `design-css.test.ts`) | `packages/blocks/src/checkout/layout-design.tsx:74` |
| insert-block: add a block from the inserter into Checkout | admin | important | candidate | `packages/blocks/src/checkout/inserter-root.ts:12` |
| switch-view: editor Checkout / Guest / Thank you (Pro) views | admin | important | candidate | `packages/blocks/src/checkout/layout-view.tsx:139` |
| pro-steps-add-step: add a Step to a Steps block (Pro) | admin | important | candidate | `packages/blocks/src/pro/steps/edit.tsx:42` |
| settings-advanced: preview-link lifetime select and custom CSS | admin | important | candidate | `apps/admin/src/pages/settings/components/AdvancedTab.tsx:55` |
| setup-wizard-first-run: the first-run wizard creates a draft | admin | important | candidate | `apps/admin/src/components/setup-wizard/SetupWizard.tsx:80` |
| layouts-row-menu: rename, duplicate, archive, restore, delete | admin | important | candidate | `apps/admin/src/pages/layouts/LayoutsPage.tsx:885` |
| layouts-bulk: bulk archive and restore | admin | important | candidate | `apps/admin/src/pages/layouts/LayoutBulkBar.tsx:46` |
| layouts-assignment-rules (Pro): rules drawer saves rules | admin | important | candidate (rule shape: `unit` `LayoutTest`, `condition-types.test.ts`) | `apps/admin/src/pages/layouts/assignment/AssignmentDrawer.tsx:218` |
| tools-export-import (Pro): export a layout to .json, import it as a draft | admin | important | candidate | `apps/admin/src/pages/tools/components/ExportCard.tsx:60` |
| preview-link: a token renders a non-active layout, ordering disabled | guest | important | candidate (token: `unit` `PreviewTokenTest`) | `includes/Renderer/PreviewRequest.php:125` |
| quantity-stepper: the stepper changes quantity and totals | guest | important | candidate (needs `quantityDisplay: stepper` in the layout) | `includes/Renderer/CheckoutFragments.php:1158` |
| login-block: the checkout login dialog logs a customer in | guest | important | candidate (needs `woocommerce_enable_checkout_login_reminder=yes`) | `packages/blocks/src/login/render.php` |
| safe-mode-standdown: safe mode serves WooCommerce's checkout | guest | important | candidate (decision: `unit: none yet`) | `includes/Renderer/CheckoutPage.php:152` |
| extra-field-validation: a required extra field blocks the order | guest | important | candidate (collection: `unit` `ExtraFieldSubmissionTest`) | `includes/Renderer/CheckoutExtraFields.php:88` |
| pro-thank-you-page: the layout's thank-you view replaces order-received | guest | important | candidate | `includes/Pro/Renderer/ThankYouPage.php:164` |
| pro-assignment-storefront: rules pick the layout, cart rules pin and repick once | guest / customer | important | candidate (pick: `unit: none yet`, `LayoutAssignment` in `tests/Integration/`) | `includes/Renderer/CheckoutPage.php:98` |
| settings-unsaved-guard: switching tabs with unsaved edits asks first | admin | minor | candidate | `apps/admin/src/pages/settings/SettingsPage.tsx:64` |
| setup-wizard-skip: Skip setup closes the wizard for good | admin | minor | candidate | `apps/admin/src/components/setup-wizard/SetupWizard.tsx:46` |
| layouts-priority (Pro): reorder active layouts | admin | minor | candidate | `apps/admin/src/pages/layouts/priority/PrioritySheet.tsx:135` |
| tools-reset-layout: reset a layout to its template with typed RESET | admin | minor | candidate | `apps/admin/src/pages/tools/components/ResetModal.tsx:98` |
| compatibility-report: report renders, Re-scan | admin | minor | candidate (detectors: `unit: none yet`) | `apps/admin/src/pages/compatibility/CompatibilityPage.tsx` |
| block-visibility: set a visibility rule on a block | admin | minor | candidate (rules: `unit` `visibility-rules.test.ts`) | `packages/blocks/src/visibility/index.tsx:407` |
| preview-from-editor: Preview opens the saved layout in a new tab | admin | minor | candidate | `packages/blocks/src/checkout/layout-actions.tsx:262` |
| pro-order-again (Pro): Order again refills the cart | customer | minor | candidate | `includes/Pro/Renderer/OrderAgain.php:119` |
| express-pay: wallet buttons adopted into the Express Pay block | guest | important | manual: needs Stripe or PayPal with test keys | `includes/Renderer/ExpressPay.php` |
| plugin-block: YayBoost order bump and bars | guest | minor | manual: needs YayBoost installed and licensed | `includes/Helpers/PluginBlockCatalogue.php` |
| save-pdf-button (Pro): print the thank-you page | customer | minor | manual: opens the browser's print dialog | `packages/blocks/src/pro/save-pdf-button/render.php` |
| licence activation: activate a real key on the Licenses page | admin | minor | manual: needs the yaycommerce.com licence server | `vendor-prefixed/src/License/License.php:58` |
