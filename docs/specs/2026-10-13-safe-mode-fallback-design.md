# Safe Mode Fallback Design

**Status:** accepted 2026-10-13
**Related:** None.

## Goal

A shopper never meets a broken checkout. With safe mode on, when the layout picked for the request has a problem that can stop an order, the storefront serves WooCommerce's native checkout instead and the merchant is told why. With safe mode off, the layout still renders and the merchant still sees the warning.

## Context

- Today `general.safe_mode` (`Models\Settings::schema()`, default `false`) is a kill switch: on, `Renderer\CheckoutPage::resolve()` returns null and the storefront is always WooCommerce's checkout, whatever the layout.
- `CheckoutPage::resolve()` is private and runs once per request through the memoised `layout()`. Every storefront hook (`CheckoutRequiredFields`, `CheckoutExtraFields`, `CheckoutFragments`, `CheckoutLogin`, `CartItemQuantity`, `LiveValues`, …) and every block `render.php` stands down when `layout()` is null, so null is the one switch for the whole request, AJAX and the order submit included.
- Every request picks its layout afresh with no pin (`docs/architecture.md` § Storefront). A fallback decision must therefore come out the same on the render request and the submit request, or the order is validated against a form the shopper never saw.
- `templates/checkout-page.php` already renders the layout into an output buffer before `wp_head()`, so blocks can enqueue while rendering.
- `Services\LayoutMarkupReader::publish_error()` refuses publish and activate today for: no Checkout block, extra field without Key, malformed Key, duplicate Key. A layout with no Place order and no Payment block passes: `place-order` and `payment` only declare `ancestor: yaycheckout/checkout`.
- `Support\CompatibilityCatalogue` holds gateway and plugin entries (keyed by slug, statuses `verified`, `community`, `unverified`, plus `block-only` for gateways). `Repositories\CompatibilityCatalogueRepository::get()` reads the transient `yaycheckout_compatibility_catalogue` and, when it is missing, fetches the remote catalogue with a 5 s timeout.
- `Helpers\GatewayCompatibilityHelper` already finds block-only payment methods (`block_registered_ids()`, private). `Helpers\StoreEnvironmentHelper` reports `store_type` (block or classic checkout) and `safe_mode` to `GET /compatibility`; the SPA shows `safe_mode` in the Store card's Rendering row (`apps/admin/src/pages/compatibility/components/StoreSection.tsx`).
- `Helpers\PluginHeaderHelper::describe()` reads an installed plugin's name and version.
- No build has shipped (no migration needed for the changed default).

## Scope

**In:**
- New meaning of safe mode: fall back only when a problem is found. Default `true`. Free and Pro alike.
- **A, markup:** `publish_error()` also requires a `yaycheckout/place-order` and a `yaycheckout/payment` block; the same rules run on every storefront request.
- **C, render failure:** a `\Throwable` while rendering, a render without `id="place_order"`, or a fatal during render trips a circuit breaker stored in an option.
- **Plugin conflict:** a catalogue plugin entry with status `conflict`, optionally limited to versions below `fixed_in`, that is active.
- **Unsupported payment:** no enabled gateway usable in the YayCheckout form while the native checkout has one.
- A filter so other code adds store-level problems.
- Reporting: Compatibility page Rendering row and a wp-admin notice.

**Out:**
- Problems that appear after save because the environment changed (a block no longer registered, a downgrade): the block renders nothing and nothing breaks.
- JavaScript errors in the shopper's browser.
- Gateway availability per cart or country (it changes while the shopper fills the form).
- Dequeuing assets that blocks enqueued before a render failure.
- A kill switch: a merchant who wants WooCommerce's checkout deactivates the layout.
- `conflict` entries in the bundled catalogue: the remote catalogue carries them.
- Dismissible notices, email alerts, logs of past failures.
- Warning about a store with no payment method enabled at all.

## Acceptance criteria

1. Given a layout whose markup has a Checkout block but no Place order block, when the merchant publishes or activates it, then the request fails with *"Add a Place order block to this layout before activating it."*; the same for a missing Payment block with *"Add a Payment block to this layout before activating it."*
2. Given safe mode on and an active layout whose markup fails `publish_error()` (stored before the rule existed, or by Quick Edit), when a shopper opens checkout, then WooCommerce's checkout renders and the Compatibility Rendering row reads `Native checkout — <publish_error message>`.
3. Given safe mode on and a block whose `render.php` throws, when a shopper opens checkout, then the same request shows WooCommerce's checkout, `yaycheckout_render_failure` holds `{layout_id, hash, reason, time}` with the exception message, and the next requests (`update_order_review` and the order submit included) also get WooCommerce's checkout.
4. Given 3, when the merchant saves a change to that layout's content, then the next checkout request renders the layout again.
5. Given safe mode on and a render whose output has no `id="place_order"`, then the request falls back as in 3 with reason *"The layout rendered without a Place order button."*
6. Given safe mode off and a block that throws, then the exception propagates as today and `yaycheckout_render_failure` is written.
7. Given safe mode on and a catalogue plugin entry `{ "status": "conflict" }` for an active plugin's slug, then WooCommerce's checkout renders with reason *"<Plugin name> conflicts with YayCheckout."*; with `"fixed_in": "2.0.0"` and the plugin at `2.0.0` or later, the layout renders.
8. Given safe mode on, the store's checkout page uses the Checkout block, and the only enabled payment method is block-only, then WooCommerce's checkout renders with reason *"No enabled payment method works in the YayCheckout checkout."*
9. Given no enabled payment method at all, then the layout renders (safe mode on or off) and no problem is reported: the native checkout could not take payment either, and WooCommerce already says so.
10. Given a callback on `yaycheckout_safe_mode_problem` returning a non-empty string, then that string is treated like any store-level problem.
11. Given any problem and safe mode off, then the layout renders, the Rendering row reads `YayCheckout layout — <reason>`, and the wp-admin notice shows.
12. Given a preview link, then the preview layout renders regardless of safe mode, problems and the breaker, and an exception during a preview render propagates without writing `yaycheckout_render_failure`.
13. Given a fresh install, then Settings › General shows Safe mode on.
14. Given no problem, then no notice shows and the Rendering row reads `YayCheckout layout`.
15. End-to-end verification: on yaycheckout.test with safe mode on, add `throw new \Exception( 'boom' );` at the top of `packages/blocks/src/order-summary/render.php`, run `pnpm build:blocks`, open `/checkout/` as a guest: WooCommerce's checkout shows; place an order with Cash on delivery: it succeeds; wp-admin shows the notice with *boom*; Compatibility › Rendering reads `Native checkout — boom`. Remove the throw, save a content change to the layout (or re-save it in the editor), rebuild: `/checkout/` shows the layout again and the notice is gone.

## Constraints

- Product PHP runs on PHP 7.4 (`CLAUDE.md` hard rule 4 and `.claude/rules/php-standards.md`).
- No remote request on the storefront: the storefront reads the catalogue only through `CompatibilityCatalogueRepository::cached()`.
- Every check in `resolve()` reads store configuration only (options, active plugins, enabled gateways, the catalogue transient), never the cart, the shopper's input or anything that changes while the form is filled.
- `yaycheckout_render_failure` is written only when its `layout_id`, `hash` or `reason` changes.
- Free names Pro nowhere new (`CLAUDE.md` hard rule 2).
- Smallest change: no refactor of code the feature does not need.
- Wire stays snake_case; the strict zod schemas change in the same commit as the PHP shape.

## Approach

**Chosen:** check every request, one rules class.

**Architecture:** `Renderer\CheckoutHealth` owns every rule. `CheckoutPage::resolve()` asks it after picking a layout and, with safe mode on, returns null when it reports a problem or when the circuit breaker holds that layout. Because the rules read store configuration only, the render request and the submit request reach the same answer. Render failures, which only the render request can see, are caught in `CheckoutPage::page_template()` and recorded in an option that `resolve()` reads, so later requests agree with the one that failed. The admin calls the same class, so the storefront and the report never disagree.

**Components:**
- `includes/Renderer/CheckoutHealth.php` (new): `store_problem(): string` runs `conflicting_plugin()` then `unsupported_payment()` and passes the first non-empty reason (or `''`) through `apply_filters( 'yaycheckout_safe_mode_problem', $problem )`. `problem( Layout $layout ): string` returns `LayoutMarkupReader::publish_error( $layout->content )` or else `store_problem()`. `reported_problem(): string` is what the admin shows: a still-valid render failure's reason, else `store_problem()`. A new rule is one private method and one line in `store_problem()`.
- `includes/Renderer/CheckoutPage.php`: `OPTION_RENDER_FAILURE = 'yaycheckout_render_failure'`; `resolve()` adds the safe-mode check after `active()`; `page_template( string $template )` renders through a new private `render()` inside `try/catch ( \Throwable )`, checks for `id="place_order"`, registers `record_fatal()` with `register_shutdown_function`, and on failure with safe mode on cleans the buffers it opened, sets `$this->layout = null`, removes its `wp_enqueue_scripts` action and returns `$template`; new `html(): string`; private `record_failure()` and static `has_failed( Layout )` (layout id and `md5( $layout->content )` match the option).
- `templates/checkout-page.php`: the buffered render moves to `CheckoutPage::render()`; the template prints `CheckoutPage::get_instance()->html()`.
- `includes/Services/LayoutMarkupReader.php`: `publish_error()` gains the Place order and Payment rules after the Checkout rule, with constants for `yaycheckout/place-order` and `yaycheckout/payment`.
- `includes/Support/CompatibilityCatalogue.php`: `STATUS_CONFLICT = 'conflict'` in `PLUGIN_STATUSES` and `GATEWAY_STATUSES`; `entry_defaults()` gains `fixed_in`.
- `includes/Repositories/CompatibilityCatalogueRepository.php`: `cached(): array` returns the transient, or `CompatibilityCatalogue::bundled()`, never fetching.
- `includes/Helpers/GatewayCompatibilityHelper.php`: `block_registered_ids()` becomes public.
- `includes/Helpers/StoreEnvironmentHelper.php`: adds `checkout_problem` (`CheckoutHealth::reported_problem()`).
- `includes/Engine/Admin/SafeModeNotice.php` (new): `admin_notices` warning for users with `LayoutPostType::CAPABILITY` when `reported_problem()` is non-empty; text by safe mode (on: *"Shoppers are getting the WooCommerce checkout: <reason>"*; off: *"Your checkout layout may not work: <reason>"*), linking to the Compatibility page. Built where the other `Engine\Admin` classes are.
- `includes/Models/Settings.php`: `safe_mode` default `true`.
- `apps/admin/src/lib/schema/compatibility.schema.ts`, `apps/admin/src/lib/api/compatibility.api.ts`: `conflict` in the status enums, `fixed_in` → `fixedIn` on entries, `checkout_problem` → `checkoutProblem` on store.
- `apps/admin/src/pages/compatibility/components/StoreSection.tsx`: Rendering row per the table in UI.
- `apps/admin/src/pages/settings/components/GeneralTab.tsx`: new description.

**Rules in detail:**
- `conflicting_plugin()`: slugs (directory name, or file name for a single-file plugin) of `get_option( 'active_plugins' )` and, on multisite, the keys of `get_site_option( 'active_sitewide_plugins' )`. A plugin entry with status `conflict` matches its slug when `fixed_in` is null, or when `version_compare( <installed>, fixed_in, '<' )`; the installed version comes from `PluginHeaderHelper::describe()` and is read only for entries with `fixed_in`. Reason: *"%s conflicts with YayCheckout."* with the plugin's name (entry `title`, else the header name).
- `unsupported_payment()`: *usable here* = enabled classic gateways (`'yes' === $gateway->enabled`) whose catalogue gateway status is not `conflict`. *Usable natively* = enabled classic gateways, plus `block_registered_ids()` not among the classic gateways when `store_type` is block checkout. Problem when *usable here* is empty and *usable natively* is not. Reason: *"No enabled payment method works in the YayCheckout checkout."*

**Data and storage:**
- Option `yaycheckout_render_failure`: `{ layout_id: int, hash: string (md5 of post_content), reason: string, time: int }`, autoloaded. Removed by `uninstall.php` (it deletes every `yaycheckout_*` option).
- Catalogue entries gain `fixed_in: ?string`; statuses gain `conflict`. Contract version stays `1`: older builds drop `conflict` entries through `normalize()`'s unknown-status rule.
- `general.safe_mode` default `true`.

**Integration points:**
- New filter `yaycheckout_safe_mode_problem` (`string $problem`): return a non-empty reason to report a store-level problem.
- `wp` (existing, `CheckoutPage::maybe_take_over`), `template_include` at `PHP_INT_MAX` (existing, now does the render), `admin_notices` (new).
- `GET /compatibility` gains `store.checkout_problem`.

**UI:**

| Safe mode | Problem | Rendering row |
|---|---|---|
| on | yes | `Native checkout — <reason>` |
| off | yes | `YayCheckout layout — <reason>` |
| either | no | `YayCheckout layout` |

Settings › General › Safe mode description: *"If the active layout has a problem that could break checkout, shoppers get the native WooCommerce checkout instead. Problems show on the Compatibility page."*

**Edge cases and failure modes:**
- Exception mid-render: buffers above the level recorded before `render()` are discarded; assets already enqueued by blocks stay (accepted).
- Uncatchable fatal (memory, timeout): that request is lost; `record_fatal()` checks `error_get_last()` for `E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR` while a `rendering` flag is set, so later requests fall back.
- A render failure for a layout no longer active, or whose content changed, is ignored by `resolve()` and by `reported_problem()`.
- Design or rules edits that leave `post_content` unchanged do not reset the breaker; a content save does.
- Catalogue transient missing on the storefront: the bundled catalogue is used, which has no `conflict` entries.
- A third-party `is_active()` that throws inside `block_registered_ids()` is already caught there.
- Pro with several active layouts: A and C apply to the layout `pick()` returned; plugin and payment rules apply to all.
- Preview: `resolve()` returns the preview layout before any safe-mode check; `page_template()` rethrows during a preview without recording.

**Decision record:** None. `docs/architecture.md` (§ Storefront and § Compatibility scan) and `.claude/rules/php-standards.md` (Storefront: `CheckoutPage` writing `yaycheckout_render_failure` becomes the fourth exception to "Renderer classes only read") are updated in the implementing PR.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Precompute a "broken" flag on plugin activation, gateway save and catalogue refresh | Many invalidation hooks, and misses plugin updates through FTP or WP-CLI. |
| Catch everything at render through the breaker | The breaker resets on layout content, so it would not clear when the merchant removes a conflicting plugin or enables a gateway. |
| Keep a separate kill switch setting | One more setting; deactivating the layout already does it. |
| Render what rendered when safe mode is off and render fails | A half-rendered page without the form is worse than today's error. |
| Check gateway availability for the cart | Changes while the shopper fills the form, so render and submit could pick different checkouts. |

## Rollout

No shipped build: `safe_mode` defaults to `true` with no migration. Release note: safe mode now falls back only when a problem is found; to serve WooCommerce's checkout outright, deactivate the layout. Conflict entries go to the remote catalogue after release.

## Open questions

None.
