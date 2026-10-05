# System Architecture

How YayCheckout is put together, and why. `code-standards.md` covers conventions; this covers the decisions those conventions exist to serve.

## Shape

```
WordPress admin                          Storefront
  │                                        │
  ├─ React SPA (apps/admin)                └─ published layout → blocks render
  │    ├─ core wp/v2  ──► post_content
  │    └─ yaycheckout/v1 ──► publish, list
  │
  └─ Block editor (packages/blocks) ──► post_content
```

Three build outputs, all under `assets/dist/`: the admin SPA (Vite), the editor blocks (`@wordpress/scripts`), and PHP that is shipped as-is.

## Layout editor: embedded Gutenberg iframe

Edit on a layout row opens core's `post.php` editor in a modal iframe with `?yc-embed=1`. `Engine\Admin\LayoutEditorEmbed` sees the flag, adds a body class and prints CSS that hides the admin bar, admin menu, footer and the editor's own back button, and removes the admin-bar offset so the editor skeleton fills the frame. It also tags the page's REST calls with an `X-YayCheckout-Embed` header, and `Controllers/LayoutCoreRestController::delete_item_permissions_check()` refuses delete for that header or for the embed page request itself (the editor preloads its permissions server-side). Gutenberg reads that answer to decide whether to render Move to trash, so the embed has none: after a trash core hard-navigates the frame to `edit.php`, a screen the modal cannot host. Nothing else about Gutenberg or saving changes — drafts still go through core's `wp/v2` — and closing the modal is the only exit, so the list refetches when it closes.

## A layout is one post

`post_content` holds the block markup; `post_status` says whether shoppers may see it. There is no second copy: publishing is a status change, the same one the editor's Publish button and cron's `publish_future_post` make. `LayoutRepository::publish()` sets `publish` through `wp_update_post()` and, for a layout dated ahead, moves the date to now (`wp_insert_post()` would otherwise turn `publish` straight back into `future`).

Every storage key is a constant on `Models\Layout` so a mistyped key is a fatal, not a silent read of nothing.

## Storage

No custom tables in 1.x.

| Key | Kind | Written by |
|---|---|---|
| `post_content` | post field | core REST (the editor) |
| `yaycheckout_active_layout` | option | `set_active()` / `deactivate()` — an ordered id set; Free replaces it, Pro appends, and `active()` serves the last id |
| `yaycheckout_settings` | option | `SettingsRepository::save()` — the sanitized settings array |
| `yaycheckout_settings_updated_at` | option | `SettingsRepository::save()` — ISO 8601 UTC timestamp of last save |
| `yaycheckout_reviewed` | option | `SettingsRepository::mark_reviewed()` — boolean, suppresses the review prompt |
| `yaycheckout_setup_done` | option | `SettingsRepository::mark_setup_done()` — boolean, suppresses the setup wizard |
| `yaycheckout_design` | option | `DesignRepository::save()` — `{ tokens, preset_id, custom_css, updated_at, presets }` where built-in presets live in code, merchant presets are stored |

Preview links store nothing: the token is its own record (see Preview links).

## Settings and Design are schemas, not defaults arrays

`Models\Settings::schema()` and `Design::schema()` are the single descriptions — section/key, type, default, allowed enum values. Reading projects the stored array through the schema: missing or stale keys return their default, invalid values use the default. Writing expects already-sanitized data (from `sanitize()`), which validates types/enums and strips unknown keys. This design means the admin SPA cannot crash on missing or invalid stored values — invalid data reads as defaults instead. Built-in design presets (`default`, `dark`, `b2b`) live in code and are never stored; merchant presets travel in the same option as the applied tokens and custom CSS. `Design::defaults()` is the Default preset; other presets override only the keys that differ, merged via `array_replace_recursive()`. Applying a preset copies its tokens; a deleted preset leaves tokens as-is — `preset_id` is a hint, not a bind.

Colours, typography, spacing, buttons and inputs each carry an `enabled` flag, off by default. Off is "theme default": the storefront prints nothing for that section, and since the checkout blocks emit stock WooCommerce markup (`woocommerce_form_field()`, `button alt`), the theme's own styles apply and follow a theme switch — nothing reads or copies the theme's values. Turning a section off keeps its values for when it is turned back on. "Reset to theme" saves the Default preset (every section off, values at their defaults); custom CSS is kept.

## Search and pagination

Layout lists are queried server-side via `LayoutQuery` — an immutable value object that carries `status`, `search` term, and `page` number.

Search matches `post_title` only. Post content is block markup, not user-facing text, so WP's default title + excerpt + content search would match block attribute names and markup rather than the layout's name. A filter (`match_search_to_title`) narrows WP's `s` parameter to the title, preserving the AND-of-terms shape merchants expect from the WordPress admin.

Page size is fixed server-side (`LayoutQuery::PER_PAGE = 10`), never client-chosen. Backend does not clamp out-of-range pages — it returns the requested page's items (empty if the page is out of bounds) with the real total count (`total_pages` on the wire, `totalPages` in the app). The SPA clamps in a `useEffect` when the data shows the current page is now beyond it.

## Admin SPA layout list

The layouts page (`LayoutsPage.tsx`) uses two independent queries: `GET /layouts` (one page, filtered by status and search) and `GET /layouts/counts` (tab totals). Separate queries keep counts on a stable cache key while the list rotates through status/search/page combinations.

Search is debounced 300ms client-side. When search or tab changes, both `search` and `pageIndex` reset in the same state update — not in a separate effect — to avoid a fetch for a page that may not exist under the new filter. List data uses `keepPreviousData` so the page never drops back to its first-load skeleton, but the previous rows are shown differently depending on where they came from: a same-tab page or search change keeps them on screen dimmed with the pager mounted, while a tab switch hides them behind a row skeleton because they belong to the other tab. The page tracks the view whose rows last settled to tell the two apart. The first-load skeleton is gated on `isPending` from both queries, which fires only once — later tab, page and search changes flip `isFetching` instead — and the gate covers the body too, because counts usually lands first and the real tabs would otherwise render under the skeleton. A counts failure replaces the whole page (the tabs cannot render without it); a list failure is passed into the body so the tabs stay mounted and the user can switch away or back to retry. When a refetch returns fewer `totalPages` than the SPA is on (the layout being edited was trashed in the embedded editor, or another admin removed layouts before a stale refetch), a `useEffect` clamps `pageIndex` back to the valid range — floored at page 1, since `totalPages` is `0` for an empty result and the API rejects `page=0`.

## Two REST surfaces, one auth rule

The block editor saves drafts through **core's** `wp/v2/yay_checkout_layout` — autosave and revisions come free. Operations core cannot express are ours, under `yaycheckout/v1`.

Both require `LayoutPostType::CAPABILITY` (`manage_woocommerce`). Core maps every capability (`edit_post`, `read_post`, `delete_post`, `create_posts`, …) to it at registration with `map_meta_cap => false`; our controllers check it in `permission_callback`. One rule, declared in one place, no `map_meta_cap` filter.

Reads need one more step. `WP_REST_Posts_Controller::check_read_permission()` returns true for any post whose status is `publish` before it consults the post type's capabilities, so core alone would answer a logged-out `GET wp/v2/yay_checkout_layout/{id}` (and the collection) with the layout's title, author id, dates and draft markup. `Controllers/LayoutCoreRestController` is registered as the post type's `rest_controller_class` and requires `LayoutPostType::CAPABILITY` in `check_read_permission()` and `get_items_permissions_check()`, so both surfaces answer 401 to the same requests. Revisions and autosaves already go through `edit_post`; `/wp/v2/search` indexes `public` post types only. `publish` itself stays: `private` would fight the editor's Publish button, `edit.php` and cron's `publish_future_post`.

The wire `status` **is** `post_status` (`draft`, `pending`, `future`, `publish`, `trash`), owned by `Enums/LayoutStatus` and mirrored by the SPA's zod enum. There is no friendlier vocabulary to translate, and nothing sits beside the status: `publish` in `post_status` is the whole of "published". `published_at` on the wire is `post_date_gmt` while the status is `publish`, null otherwise.

### Scheduled layouts

Scheduling is core's: the editor sets `future` and a `post_date`, and WP cron's `publish_future_post` flips the status when the date arrives. Two plugin rules sit on top:

- **Cron only changes the status, which is all publishing is.** A second cron run finds the status already `publish` and does nothing. Cron runs on traffic unless the host triggers it externally, so a quiet store publishes late — a WordPress property, not ours.
- **Publishing a scheduled layout overrides the schedule.** Both `/publish` and `/activate` (whose modal reads "Publish & activate") go through `publish()`, which moves the date to now whenever `post_date_gmt` is ahead — a pending layout can carry a future date too: `wp_insert_post()` would otherwise turn `publish` back into `future`, leaving a layout live for shoppers while the row still read "Scheduled". Core's own transition handling clears the cron event. `activate()` publishes unless `post_status` is already `publish` — the same rule the modal uses — so a rescheduled or pending layout goes live as it is.
- **Activating an archived layout is refused.** Publishing would silently restore it from the trash and, on Free, push the layout count past the limit. Restoring is the merchant's decision, so `activate()` throws rather than deciding for them.
- **Trashing or deleting the active layout is refused.** The mirror rule: `Engine/LayoutActiveGuard` denies the `delete_post` capability for a layout in the active set (so the editor hides Move to trash, `wp/v2` and wp-admin answer 403) and short-circuits `pre_trash_post` / `pre_delete_post` for direct calls that check no capability. Deactivate first; the store never silently loses its checkout. The SPA's Archive action is the exception by design: `ArchiveModal` warns, then `LayoutRepository::archive()` drops the id from the active set before calling `wp_trash_post()`, so the guard lets it through. `wp_trash_post()` stamps `_wp_trash_meta_time`, which lets cron purge the row after `EMPTY_TRASH_DAYS`; `Engine/LayoutTrashSync` deletes that stamp on `trashed_post` for every path, so archived means kept everywhere.
- **Moving the active layout back to draft or pending deactivates it.** The same guard listens to `transition_post_status`: a status shoppers may not see drops the layout from the active set, so the store falls back rather than serving a layout its own row calls Draft. `future` keeps it active — the current content serves until the scheduled date replaces it.

## Storefront

Renderer takeover happens on every request for the checkout page (`is_page( checkout )`, endpoints such as order-received and order-pay included) when safe mode is off and `CheckoutPage::resolve()` returns a layout. Otherwise WooCommerce is untouched. There is no check that the layout can complete an order (an Order review block); a root-only layout renders an empty form.

**Which layout:** `LayoutRepository::active( $context )` answers. Free keeps one active layout and serves it whatever its rules. On Pro (`Plan::is_pro()`) it hands the active set, most recently activated first, to `Services\LayoutAssignment::pick()`, which walks it in that order and serves the first layout that applies to the shopper: a layout without rules applies to everyone, so it ends the walk, and a layout with rules applies when one of its groups has every condition true (groups ORed, conditions ANDed). Nothing applying leaves WooCommerce's own checkout. The order stands in for merchant-set priority, which is not built. The conditions read the user's roles (a guest has none), whether they are logged in (`is_user_logged_in()`, not "has no role": a user of another network site has none here), and `wp_is_mobile()` (tablets count as mobile); `CheckoutPage` reads these facts. Every request picks afresh, the page load, `update_order_review`, the order submit and pages that build checkout fields alike, with no pin between them. That is safe only because every condition reads a fact that holds for the life of a form: the required-field overlay and extra fields validate against the served layout, so a fact the shopper can change mid-form (billing country, cart contents) would swap the layout under them and is deliberately not a condition. Logging in on the checkout page reloads it. An account created at the order submit logs the shopper in only after WooCommerce has read the checkout fields, so that request keeps the layout it began with (memoised per request), and WooCommerce reloads the checkout if the order then fails. The one gap: a merchant activating another layout while a shopper is mid-form changes what their submit validates against, as editing the served layout does. Several layouts without rules, or rules that overlap, are not validated: the first in the order wins.

**Mechanics:** `template_include` @ `PHP_INT_MAX` (after block themes) swaps in `templates/checkout-page.php`: the theme's header and footer around a `div.woocommerce` wrapper, `WC()->cart->calculate_totals()` so shipping packages exist, then `do_blocks( $layout->content )`. Classic themes print the whole document through `get_header()` / `get_footer()`; block themes have no `header.php`, so the template prints its own shell and the theme's header/footer template parts via `block_header_area()` / `block_footer_area()`. The layout, header and footer are rendered into buffers before `wp_head()`, as core's `template-canvas.php` does: blocks enqueue script modules while rendering, and block themes print the import map in the head, so anything rendered after it (the navigation block's `view` module, WooCommerce's customer-account block) would have its `@wordpress/interactivity` import unmapped and fail to load. WooCommerce's checkout shortcode and `form-checkout.php` are not used: the root `checkout` block *is* the form — its wrapper is `<form name="checkout" class="checkout woocommerce-checkout">`, preceded by WooCommerce's must-be-logged-in guard. Everything else on the page is a block, so the hooks WooCommerce's stock form fires (`woocommerce_before/after_checkout_form`, coupon and login forms, notices on the non-JS path) and the account-creation fields will arrive as blocks; until then they are absent from the page. Scripts `wc-checkout`, `wc-country-select`, `wc-address-i18n` are enqueued unconditionally since the checkout page no longer goes through the theme's page template. `CheckoutPage::enqueue_scripts()` (`wp_enqueue_scripts` @ 20, attached only on takeover) also prints the Design page as inline style `yaycheckout/style/checkout` (`Renderer\DesignStyles`): tokens become `--yaycheckout-*` custom properties on `:root`, but only for sections the merchant turned on: `color-{primary,text,background,border}` (colours), `font-size-base` plus `font-family` when a font is picked (typography), `spacing-scale` (spacing), `button-radius` (buttons), `input-radius` and `input-height` from `DesignStyles::INPUT_HEIGHTS` (inputs). With every section off nothing but the custom CSS is printed. No block reads the variables yet, the editor canvas gets none of them, and the picked font is named, not loaded.

**Validation:** When a layout is served, its required flags replace WooCommerce's. `CheckoutRequiredFields` filters `woocommerce_checkout_fields` at priority 9999 (after locale and third-party callbacks) and hands the billing/shipping fieldsets to `RequiredFieldOverlay::apply()`: a field is required exactly when its Field block says so, a field the layout leaves out becomes optional, and email (`billing_email`, the Contact block, or any field of type `email`) is always required because it is the only way to reach the customer. The filter is not gated on the checkout page: the checkout POST validates at `wp_loaded`, before `is_page()` works. A field the layout renders but WooCommerce dropped from its registry (the Customizer's "Hidden" setting) is added back with the block's own validate rules, or else those a stock field of that key carries, otherwise WooCommerce would neither validate nor save it. Label suffixes come from each block's `requiredMark` / `optionalText` (empty keeps WooCommerce's `*` and `(optional)`), rendered by `RequiredIndicator`: `swap_in()` on `woocommerce_form_field` (checkout only, since account forms share the hook), `html()` in block templates that print their own label, and `script_payload()` inlined for `assets/js/checkout-field-requirements.js`, which re-applies flags and suffixes after WooCommerce's `address-i18n.js` resets them on country change. With no layout served, all of this stands down and stock WooCommerce rules apply.

**Blocks and WooCommerce:** The `order-review` block wraps a cart guard (`WC()->cart instanceof WC_Cart`) and fires `woocommerce_checkout_before/after_order_review` and `woocommerce_checkout_order_review` inside `#order_review`, empty on non-storefront requests. The `shipping` block prints WooCommerce's `ship_to_different_address` checkbox (copied from `form-shipping.php`) and wraps fields in `div.shipping_address`; it renders nothing when the cart needs no shipping or ships to billing only. Both `billing` and `shipping` blocks carry class `woocommerce-billing/shipping-fields`, and field blocks carry `address-field` (for country-select.js) and `update_totals_on_change` (for checkout.js handlers).

The `shipping-methods` block prints WooCommerce shipping rates wrapped in a div with class `yaycheckout-shipping-methods__rates` for AJAX fragment replacement. The `payment` block prints gateway radios (via `payment-method.php` template) wrapped in `div#payment.woocommerce-checkout-payment` for AJAX replacement. Both are registered in `CheckoutFragments` so `woocommerce_update_order_review_fragments` swaps them when those blocks are present in the layout. Extra fields posted on checkout are read from the served layout by `LayoutMarkupReader::extra_fields()`, checked by `ExtraFieldSubmission::collect()`, and stored by `CheckoutExtraFields` on `woocommerce_checkout_create_order` — before WooCommerce saves the new order, so one write covers it — as order meta (one key per field, plus a snapshot of label/display for the order screens). WooCommerce reuses a pending or failed order when the shopper retries with the same cart, so each write first removes the Keys the previous snapshot listed. A Key must match `LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN` (letters, digits, underscores, no leading underscore): PHP rewrites spaces and dots in posted names, and a leading underscore reaches WooCommerce's internal order keys, where `update_meta_data()` calls the matching setter (`_billing_email` → `set_billing_email()`). Publish and the editor lock refuse other Keys, and the reader treats them as missing so an already-saved one renders and stores nothing. A choice field's options come from `LayoutMarkupReader::options()`, which cleans each value as a posted value is cleaned, both when `extra-field/render.php` prints them and when a submission is checked, so the page can only offer values validation accepts.

**Seeded layouts:** `LayoutRepository::create()` seeds contact, billing, shipping, and order-review blocks via `default_markup()`, which mirrors the editor's field picker (`packages/blocks/src/shared/address-fields.ts`): contact + name, country, address-1, city, state, postcode for both billing and shipping (no company, address-2, or phone). Layouts created before this renderer existed are root-only and render as an empty form.

### Preview links

A preview is the same renderer with a different layout. `POST /layouts/{id}/preview-link` mints `base64url("v1|blog|id|exp") . "." . HMAC-SHA256` (`Helpers\PreviewToken`) keyed by `hash_hmac('sha256', 'yaycheckout-preview|' . blog_id, wp_salt('auth'))` — purpose- and site-bound so an auth-salt reuse elsewhere cannot forge one — and returns the checkout URL with `?yaycheckout_preview=TOKEN`. The lifetime (`advanced.preview_link_lifetime`) is read at mint time; changing it never shortens an issued link. Anyone with the link, no login. Per-link revocation is not built; the upgrade path is a per-layout secret mixed into the key.

`Renderer\PreviewRequest` reads the token once at `init`, and on `wp` @5 (before `CheckoutPage` at 10) applies the rules:

- **410, never fall-through.** A presented token that does not resolve to a layout (bad MAC, expired, wrong blog, deleted) on the checkout page is `wp_die( 410 )`. Falling back to the active layout would let a stale link quietly show the wrong thing.
- **Preview wins over safe mode.** `CheckoutPage::resolve()` returns the previewed layout before the safe-mode check: safe mode protects shoppers, a preview is the merchant looking. The repository stays ignorant of request state: `CheckoutPage` calls `find()` for a preview and passes shopper facts to `active()`.
- **Scope.** Hooks attach only when the request is the checkout page or a wc-ajax call; the same token on any other URL does nothing (no cart stuffing from arbitrary URLs). Ordering is refused and the button disabled whenever the arg is *present*, valid or not, so a token expiring while the page is open cannot turn the sample cart into a real order on the next `update_order_review`. `woocommerce_checkout_process` attaches at `init` because the non-JS form POST is processed at `wp_loaded`, before `wp`.
- **Transport.** The token lives in `$_GET` only. `woocommerce_ajax_get_endpoint` and `woocommerce_get_checkout_url` are filtered so `wc_ajax_url` (every `update_order_review` / `checkout` call) and the form `action` carry it — a hidden input never reaches `update_order_review`'s `post_data`. The filter appends by hand: `add_query_arg()` re-encodes WooCommerce's `%%endpoint%%` placeholder to `%25%25`.
- **Sample cart.** On the checkout page (not order-pay/received) with an empty cart, the two newest `publish` + `instock` + purchasable simple products are added with cart-item data `yaycheckout_preview => true`; WC notices are restored around the adds so a refusal never shows. `woocommerce_checkout_redirect_empty_cart` and `woocommerce_checkout_update_order_review_expired` are off so a store with no products still renders the layout (the banner says so). A non-empty cart is left alone. On every request **without** the token, `woocommerce_cart_loaded_from_session` @5 removes marked items and recalculates, so a shopper who followed a preview link never checks out with sample items.
- **No ordering.** `woocommerce_order_button_html` is a disabled button; `woocommerce_checkout_process` adds an error notice, so both the AJAX and non-JS paths refuse before an order exists. `nocache_headers()` on every preview response.

The template prints a `woocommerce-info` banner above the layout (title, status, minutes left) when the request is a preview.

## Blocks are dynamic

`save()` returns `null`; all markup comes from `render.php`. Published layouts are stored as markup and re-rendered on every request, so a renamed attribute is absorbed by a default in the render callback. Static save output would instead need a block deprecation for every attribute rename, and existing merchant content would show as invalid.

`block.json` has one home: `packages/blocks/src/{block}/`. `@wordpress/scripts` copies it and `render.php` into `assets/dist/blocks/{block}/`, and `BlockRegistry` registers every built directory it finds (in `BlockRegistry::ORDER`, the order a shopper meets the blocks, which is the inserter's order; a block missing from `ORDER` still registers, after the listed ones) — so script dependencies come from the generated `index.asset.php` rather than being hand-listed in PHP. Placement rules live in each `block.json` (`parent`: section, extra-field and slot blocks go directly inside Checkout; `ancestor`: Field only under Billing/Shipping; `supports.multiple: false`: one of each section and one Checkout). Which editor offers the blocks is `BlockRegistry::allowed_blocks()` on `allowed_block_types_all`: `yaycheckout/*` blocks only in the layout editor, and WooCommerce's `woocommerce/checkout` never in it (a second checkout form inside the layout's). The block editor no longer applies the `blocks.canInsertBlockType` JS filter, so no insert rule lives in JavaScript.

Blocks in use: `checkout` (root, `multiple: false`), `contact` (email field), `billing` and `shipping` (address sections; shipping carries the cart guard), `field` (text/select/country/state/etc.; the one block with style supports — colour, typography, border, margin/padding — which `get_block_wrapper_attributes()` applies to the `form-row` wrapper; the editor preview's label inherits font size and weight so typography changes show on the canvas), `order-review` (cart total plus WooCommerce's order-review hooks), `extra-field` (merchant-defined field with its own `fieldKey` and `required`; the Key is the posted name and order meta key, so it is required and unique per layout — a block without one renders nothing; posted values checked by `ExtraFieldSubmission` and stored as order meta by `CheckoutExtraFields`), `shipping-methods` (WooCommerce shipping rates), `express-pay` (payment shortcuts), `payment` (gateway radios), `order-summary`, `place-order` (submit button), `slot` (named empty place: `do_action( 'yaycheckout_slot_{name}' )`, for Pro and other plugins to inject into).

`Services\LayoutMarkupReader` flattens a layout's blocks into one document-ordered list (so groups and columns never matter) and answers one question per method: `slots()`, `fields()`, `extra_fields()`, `order_modes()` (always `[]` until a block declares one). Reads never throw. `publish_error()` holds the publish rules — a `checkout` block, and every `extra-field` keyed and unique. The editor's `checkout/publish-guard.ts` applies the same rules as a save lock with a warning notice (the extra-field inspector also flags its own Key), and the server refuses on them in `LayoutRepository::publish()`, in `activate()` even for an already-published layout, and in `LayoutCoreRestController::prepare_item_for_database()` for any `wp/v2` save that would leave the layout published or scheduled (drafts still save), so the lock is not the only guard; `slots()` lists the sections the layout renders (`contact`, `billing`, `shipping`, `order_review`, `shipping_methods`, `express_pay`, …, from `SECTION_SLOTS`) plus each named `slot` block, which is how `CheckoutFragments` knows which fragments to swap.

## Free and Pro

One codebase, two artifacts.

| | Folder | `includes/Pro/` | Layouts |
|---|---|---|---|
| Free | `yaycheckout/` | absent | 3 |
| Pro | `yaycheckout-pro/` | present | unlimited |

The split is **asymmetric on purpose**:

- **PHP is structurally excluded.** The Free zip has no premium code — not disabled code, absent code. The server is the only thing an attacker cannot edit, so enforcement lives here.
- **The SPA blurs.** Pro components ship in the Free bundle behind `ProGate` and are obscured, giving merchants a preview of what upgrading buys. This is marketing, not security, and is only acceptable because the Free build's PHP has no matching endpoint — an unblurred panel calls something that does not exist.

Anything that would render real customer data or call a real endpoint belongs behind `ProOnly`, not `ProGate`.

Plan is **derived, not declared**: `Plan::is_pro()` reports whether `ProServiceProvider` actually booted. A build-time constant could claim Pro while the files are absent, and the first Pro call would fatal.

Plan and license are separate axes. Plan is which build is installed — build-time, immutable. License state is valid/expired/unactivated — runtime, mutable. An expired Pro license is still the Pro plan.

Pro installs to its own directory rather than replacing Free: if Free is ever listed on wordpress.org, an update for slug `yaycheckout` would otherwise overwrite a paid install. Both being active at once is handled — the plugin deactivates a second copy of itself.

### Enforcing the layout limit

Free allows three layouts, Pro is unlimited. The rule is checked at three sites that all read `Plan::layout_limit_reached()` so it cannot drift: `LayoutRepository::create()` (the admin app's `POST /layouts`), `LayoutRepository::duplicate()`, and `LayoutCapGuard`, which filters `rest_pre_insert_yay_checkout_layout` because the post type is also on **core's** `wp/v2` and any shop manager can POST there directly.

That filter also fires on **updates**, so it returns early when the prepared post already carries an ID. Without that, editing any layout would start failing the moment a merchant reached the limit — a worse bug than the one being fixed.

Enforcement is server-side because gating only the admin app would be bypassed by posting to `wp/v2` directly, which any shop manager can do.

### The boundary rule

> No file outside `includes/Pro/` may name a `YayCheckout\Pro\*` class.

One guarded `require` in `YayCheckout::boot_pro()` is the single permitted reference; Pro attaches to core through hooks from there. `scripts/check-pro-boundary.sh` runs as part of `composer lint` and fails on a second reference.

The check is the load-bearing part, not the directory. A sibling plugin has a `Premium/` folder that cannot actually be removed, because its container hard-imports those classes across two thousand lines. Nothing but a mechanical check prevents that drift.

## Writes and the object cache

`BaseRestController` offers two wrappers:

- `exec_write_wp` — WordPress API writes. No SQL transaction.
- `exec_write` — custom tables. Wraps in `START TRANSACTION`.

WordPress writes go through the object cache, so a `ROLLBACK` reverts MySQL while leaving a persistent cache holding the rolled-back data. That bug is invisible on a host without a persistent object cache and intermittent on one with it. Ordering writes safely is the substitute for a transaction.

## Logging

Two loggers, deliberately separate:

- `Helpers\Logger` writes to WooCommerce's log under the `yaycheckout` source. Every 500 returned by `BaseRestController` records the route, exception class, file, line and trace.
- `apps/admin/src/lib/logger.ts` wraps the browser console with a `[YayCheckout]` prefix. Query, mutation and route-render failures go through it.

Both follow the same rule: log **faults**, not expected outcomes. A plan limit or unusable markup is a 4xx the merchant caused, and recording those buries the failures that matter.

The client logger keeps `error` and `warn` in production, since a console screenshot is often the fastest route to a cause; `info` and `debug` are compiled out.

## Class loading

The plugin registers its own `spl_autoload_register` mapping `YayCheckout\Foo\Bar` to `includes/Foo/Bar.php`, separate from Composer's classmap, which covers only the scoped admin shell and the adapter.

It uses `require_once` rather than `require`. A malformed class name — a doubled backslash from another plugin's `class_exists()`, say — maps to the same file through a different string, and a plain `require` then redeclared the class and took down the entire site.

## Where the plan enters the build

| Surface | Mechanism |
|---|---|
| PHP | `includes/Pro/` staged or stripped |
| SPA | `YAYCHECKOUT_PLAN` → `__YAYCHECKOUT_PLAN__` via `vite.config.ts` |
| Artifact | zip and folder name |

`vite.config.ts` resolves the plan so there is one source: the dev server defaults to `pro` (working on the Pro product should show the full UI), builds default to `free` (an unflagged build cannot ship premium UI), and `YAYCHECKOUT_PLAN` overrides both.

`cmd_release` copies a fixed file list, so both a missing file and a leaked one fail silently in a dev tree that always contains everything. It therefore verifies the archive it just produced and refuses to report success on a mismatch.

## Compatibility

One REST read (`GET /compatibility`) composes three live detections and one catalogue; nothing is stored beyond the catalogue transient.

- **Store** — `StoreEnvironmentHelper` reads WC/WP versions, the active theme, and `CartCheckoutUtils::is_checkout_block_default()` for the store type (WooCommerce's own check, block and classic themes alike). `safe_mode` comes from `SettingsRepository`, so the SPA invalidates the report when settings save.
- **Gateways** — `GatewayCompatibilityHelper` lists every enabled classic gateway, then every active Checkout-block payment method with no classic gateway under the same id, flagged `block-only`: the gateway a shopper would find missing from the classic form YayCheckout renders. The diff runs against *all* classic gateways, enabled or not, because a block integration's `is_active()` defaults to true. A catalogue status always wins over detection, so a plugin that keeps a stub classic gateway can still be marked `block-only` remotely.
- **Plugins touching checkout** — `CheckoutHookScanHelper` walks a fixed list of WooCommerce checkout hooks in `$wp_filter`, reflects each callback to its file, and maps the file to a plugin, mu-plugin or theme by directory (symlinks resolved through core's `$wp_plugin_paths`). WooCommerce and YayCheckout itself are excluded; generic hooks like `template_include` are deliberately not scanned because every page builder filters them. **Ceiling:** the scan runs during a REST request, so hooks a plugin registers only on `wp` / `template_redirect` are invisible, and a callback inherited from a parent class in another plugin is attributed to the declaring file. Accepted: detection is a hint that a plugin is in play, not proof that it is not.
- **Catalogue** — `CompatibilityCatalogueRepository` returns the remote JSON at `YAYCHECKOUT_COMPATIBILITY_CATALOGUE_URL` when it can be fetched (cached 12h), else the bundled list in `Support\CompatibilityCatalogue` (cached 1h). The negative cache exists so a dead endpoint costs one 5-second attempt per hour, not per page view; Re-scan drops the transient. `normalize()` is the only gate the remote document passes through: unknown statuses drop the entry, non-string fields become null, `docs_url` must be an absolute http(s) URL because the SPA renders it as a link. Remote notes are untranslated English; bundled ones go through `__()`.

Statuses: `verified` (tested by us), `community` (merchant reports — a hint, never counted as a warning), `unverified` (detected, not on the list), `block-only` (gateways only). The SPA derives warnings from `block-only` and `unverified` alone.

## Deliberately not built

Design tokens on the editor canvas, `_yaycheckout_tokens_override`, the `yay_checkout_pattern` library CPT, multi-layout export files (the envelope takes a list; export and import handle one layout each), self-test, the Compatibility report modal and header warning chip, a storefront-side hook scan (the REST-time scan's ceiling above), telemetry (the opt-in is stored, nothing is sent), per-link preview revocation, core-block canvas whitelist (which core blocks render where), custom keys on WooCommerce address fields, the 1.3 analytics table, and `yay_cart_layout` / `yay_thankyou_layout`.
