# Block Visibility Conditions Design

**Status:** accepted 2026-10-13
**Related:** template 04 ("Accordion one-page") in `~/Downloads/WooCommerce Checkout Templates_v1.1.html`: its pinned bar shows Continue on steps 1–3 and Place Order on step 4, which no block setting can express today.

## Goal

A merchant can make any block in a checkout layout show or hide depending on the open step, the cart (needs shipping, coupon applied, subtotal) and whether the shopper is logged in. The storefront applies it on load and keeps it current as the shopper moves between steps or the cart changes.

## Context

- **Block settings added by YayCheckout.** `packages/blocks/src/shared/layout-panel.tsx` `addLayoutPanel()` registers an attribute on listed block types with `blocks.registerBlockType`, in every editor. It shows a sidebar panel through `editor.BlockEdit`, only in the layout editor (`useIsLayoutEditor()`).
  - Hide When Empty (`shared/hide-when-empty.tsx` + `Renderer\HideWhenEmpty`) and Pin to Bottom (`Renderer\PinToBottom`) use this pattern.
  - On the storefront, a `render_block_*` filter decorates the block's first tag with `WP_HTML_Tag_Processor`.
- **Steps.**
  - `packages/blocks/src/step/render.php` prints `data-step` (1-based) and `data-state` (`current` | `complete` | `locked`). Step 1 starts `current`.
  - `packages/blocks/src/steps/view.ts` changes state and dispatches `yaycheckout:step-change` (bubbling `CustomEvent`) on every state change.
  - `Renderer\CheckoutStepsProgress::layout_titles()` lists the first Steps block's step titles.
- **Live cart values.**
  - `Renderer\LiveValues` prints a JSON element (`LiveValues::VALUES_ID`) in the footer and re-sends it as a `woocommerce_update_order_review_fragments` fragment.
  - `LiveValues::values()` computes `cart_subtotal` as the cart displays it (with tax when `display_prices_including_tax()`).
  - `live-value/view` is a built storefront script registered by `Blocks\BlockRegistry` (`built_asset( 'live-value/view', true )`).
- **Editor data.** `window.yayCheckoutBlocks` is printed by `BlockRegistry` (`isPro`, `postType`, `upgradeUrl`) and read only through `packages/blocks/src/shared/editor-data.ts`.
- **Hiding.** `assets/css/checkout-base.css` already has to beat themes' `display` rules for hidden elements. Hide When Empty uses a class for this, not the `hidden` attribute.

## Scope

**In:**
- **Setting:** `yaycheckoutVisibility` on every block type inside a layout, except `yaycheckout/checkout`, `yaycheckout/steps` and `yaycheckout/step`.
- **Action:** *Show when* or *Hide when*, with a list of conditions that must all match.
- **Conditions:**
  - Step is N, or Step is last
  - Needs shipping
  - Coupon applied
  - Subtotal at least / below an amount
  - Logged in
- **Server evaluation:** at render, so the page prints in the right state.
- **Live re-evaluation:** in the browser, on step changes and after every `update_order_review`.
- **Editor:**
  - the Visibility panel;
  - a "Conditional" marker on blocks that have a setting;
  - a Visibility preview sidebar that dims the blocks the chosen inputs would hide.
- **Plans:** Free and Pro alike.

**Out:**
- "Cart contains product / category" (deferred by the user).
- User role, or any shopper condition other than logged in.
- "Any of" matching, per-condition negation and nested groups. All conditions must match, and the action supplies the negation.
- **Required fields inside a hidden block:** they keep WooCommerce's rules. Making them optional, or not posting them, is the merchant's responsibility, as the user decided.
- Hiding a Step or the Steps block. Step navigation can't skip a step.
- Converting the subtotal amount across currencies for multi-currency plugins.
- Template 04 itself (its own plan consumes this design).

## Acceptance criteria

1. **Step, hide on last:** given a 4-step layout with a Step Button set to *Hide when* [Step is last], the button is visible on steps 1, 2 and 3 and hidden on step 4. This holds after Continue, and after reopening an earlier step with Edit, when the button shows again.
2. **Step, show on last:** given Place Order set to *Show when* [Step is last] in the same layout, it carries `yaycheckout-hidden-by-visibility` in the page source on load, and becomes visible when step 4 opens.
3. **Coupon:** given a paragraph set to *Show when* [Coupon applied], applying a valid coupon on the checkout shows it without a reload, and removing the coupon hides it again.
4. **Subtotal:** given *Show when* [Subtotal at least 50], a cart subtotal of 49.99 hides the block and 50.00 shows it. A quantity change that crosses 50 flips it after `update_order_review`, with no reload. *below 50* is the exact inverse at the same values.
5. **Needs shipping:** given *Hide when* [Needs shipping], a cart with only virtual products shows the block, and adding a physical product hides it after `update_order_review`.
6. **Logged in:** given *Hide when* [Logged in], a guest sees the block and a logged-in customer does not.
7. **Combined rules:** given *Show when* [Logged in, Coupon applied], the block shows only when both hold.
8. **Malformed setting:** a malformed setting shows the block and raises no PHP notice. Malformed means an unknown `type`, a `step` of `0`, a negative `amount`, an `op` other than `gte`/`lt`, or `action` other than `show`/`hide`.
9. **No setting:** a layout with no block using the setting prints no `#yaycheckout-visibility-state` element and loads no visibility script.
10. **No Steps block:** a step condition never matches. The Visibility panel shows the notice "This layout has no Steps block, so step conditions never match."
11. **Editor, panel:** the Visibility panel appears for a selected block in the layout editor and not in the post or site editor. It does not appear for Checkout, Steps or Step. Saving and reopening the layout keeps the setting.
12. **Editor, preview:** with the Visibility preview sidebar open and Step set to "Last step", the Step Button from criterion 1 is dimmed (`opacity: .35`, dashed outline) and Place Order from criterion 2 is not. Both stay selectable. Closing the sidebar removes all dimming. The post is not marked dirty.
13. **End-to-end:**
    1. Create a layout from template 01. Wrap its Place Order and a new Step Button in a Group pinned "All screens". Set the Step Button to *Hide when* [Step is last] and Place Order to *Show when* [Step is last].
    2. Add a Steps block with 4 steps around the form sections.
    3. Preview it on the storefront with a product in the cart, at 390px and 1280px wide.
    4. Expected: the bar shows the Step Button on steps 1–3 and Place Order only on step 4, and an order places with a test gateway (Cash on delivery) on an active copy of the layout.

## Constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Never hand-edit generated output (`assets/dist/`). Build with `pnpm build:blocks`.
- A TypeScript copy of PHP names its owner (`/** Mirrors BlockVisibility::… in PHP */`) and changes in the same commit as the PHP. PHP is the owner.
- Storefront hooks stand down when `CheckoutPage::get_instance()->layout()` is null, so stock WooCommerce checkout stays untouched.
- Text a merchant types is not involved. Every new string is translatable with the `yaycheckout` text domain.
- Free and Pro both ship it. Nothing may reference `YayCheckout\Pro`.
- Branch from `dev/edd` as `trace/<type>/<topic>`, Conventional Commits, a PR into `dev/edd`.

## Approach

**Chosen:** A, server first plus a live script.

**Architecture:**
- PHP evaluates each block's setting when the layout renders, treating step 1 as open, and prints failing blocks with a hiding class. Every conditional block carries its cleaned setting in a data attribute.
- A small storefront script re-evaluates those blocks on `yaycheckout:step-change`, and on WooCommerce's `updated_checkout` using a state JSON element that `update_order_review` refreshes, as live values do. So there is no flash on load, and cart changes apply without a reload.
- The editor uses the same TypeScript evaluator for its preview, so editor, script and server agree.

**Components:**
- `includes/Renderer/BlockVisibility.php`: the owner of the rules. It is a `SingletonTrait` singleton built where `HideWhenEmpty` is built.
  - Constants:
    - `ATTRIBUTE = 'yaycheckoutVisibility'`
    - `DATA_ATTR = 'data-yaycheckout-visibility'`
    - `HIDDEN_CLASS = 'yaycheckout-hidden-by-visibility'`
    - `STATE_ID = 'yaycheckout-visibility-state'`
    - `EXCLUDED = [ 'yaycheckout/checkout', 'yaycheckout/steps', 'yaycheckout/step' ]`
    - `SCRIPT = 'yaycheckout-visibility'`
  - `public static function normalize( $value ): ?array`: the cleaned setting, or `null` when any part is invalid.
  - `public static function state(): array`: `needs_shipping` bool, `has_coupon` bool, `subtotal` float, `logged_in` bool.
  - `public static function is_visible( array $setting, array $state, int $step, int $step_count ): bool`.
  - `public function filter_block( $block_content, $block ): string`: the `render_block` filter.
  - `public function print_state(): void`: on `wp_footer`.
  - `public function fragments( $fragments )`: on `woocommerce_update_order_review_fragments`.
- `packages/blocks/src/shared/visibility-rules.ts`: `isVisible( setting, state, step, stepCount ): boolean` and `normalize( value ): VisibilitySetting | null`, both mirroring the PHP. It also holds the types `VisibilitySetting`, `VisibilityRule` and `VisibilityState`.
- `packages/blocks/src/visibility/index.tsx`: the editor part.
  - It registers the attribute on every block name except `EXCLUDED`.
  - It adds the Visibility panel (`editor.BlockEdit`, layout editor only) and the canvas marker (`editor.BlockListBlock` adds `is-yaycheckout-conditional`).
  - It adds the Visibility preview `PluginSidebar`.
- `packages/blocks/src/visibility/view.ts`: the storefront script. It finds `[data-yaycheckout-visibility]` blocks and toggles `HIDDEN_CLASS` by `isVisible()`.
- `packages/blocks/src/visibility/editor.css`: the marker and the dimming style.
- `includes/Blocks/BlockRegistry.php`:
  - registers `visibility/index` for the layout editor (as `live-value/index`);
  - registers `visibility/view` as `BlockVisibility::SCRIPT`, enqueued by `BlockVisibility` only when a conditional block rendered;
  - adds `currencySymbol` (`html_entity_decode( get_woocommerce_currency_symbol() )`) to `window.yayCheckoutBlocks`.
- `packages/blocks/src/shared/editor-data.ts`: adds `currencySymbol: string` to the editor data type.
- `assets/css/checkout-base.css`: adds `.yaycheckout-hidden-by-visibility { display: none !important; }`.

**Data and storage:**
- The setting is stored in block markup only, in the layout's post content.
- Shape:
  ```json
  { "action": "show" | "hide", "rules": [ Rule, … ] }
  ```
- Rules:
  - `{ "type": "step", "step": <int ≥ 1> | "last" }`
  - `{ "type": "needs_shipping" }`
  - `{ "type": "has_coupon" }`
  - `{ "type": "subtotal", "op": "gte" | "lt", "amount": <number ≥ 0> }`
  - `{ "type": "logged_in" }`
- Each `type` appears at most once.
- An empty `rules` array, or no attribute, means no setting: the block is untouched and gets no data attribute.
- No option, meta, migration or uninstall step.

**Integration points:**
- **`render_block` (all blocks), priority 10, in `BlockVisibility::filter_block()`:**
  - stands down without a layout, for an excluded block name, or when `normalize()` returns `null`;
  - adds `DATA_ATTR` (JSON of the cleaned setting) to the first tag;
  - adds `HIDDEN_CLASS` when `is_visible( $setting, self::state(), 1, $step_count )` is false.

  `$step_count` is `count( CheckoutStepsProgress::layout_titles() )`, read once per request. Content that prints nothing is returned unchanged.
- **Step semantics:**
  - `step: N` matches while step N is open.
  - `step: "last"` matches while the step numbered `step_count` is open.
  - With `step_count` 0, both never match.
  - "Open" means `data-state="current"`. When no step is current (all complete), the last step counts as open.
- **Rule semantics:**
  - `needs_shipping` is `WC()->cart->needs_shipping()`.
  - `has_coupon` is `! empty( WC()->cart->get_applied_coupons() )`.
  - `subtotal` compares against `LiveValues::values()`'s `cart_subtotal` amount, with `gte` as `>=` and `lt` as `<`, both on values rounded to `wc_get_price_decimals()`.
  - `logged_in` is `is_user_logged_in()`.
  - Without a cart, the cart rules are false.
- **Visibility:** visible when `action` is `show` and every rule matches, or when `action` is `hide` and not every rule matches.
- **`wp_footer`:** prints `<script type="application/json" id="yaycheckout-visibility-state">` holding `state()`, only when `filter_block()` decorated at least one block.
- **`woocommerce_update_order_review_fragments`, priority 20:** `#yaycheckout-visibility-state` holds the fresh element, only when the served layout's content contains `yaycheckoutVisibility`.
- **Storefront script:** listens for `yaycheckout:step-change` on `document`, and `updated_checkout` on `document.body` via jQuery when present. It re-reads the state element on `updated_checkout`.

**UI:**
- **Visibility panel:** in the block inspector, in the layout editor only.
  - A SelectControl "Visibility": *Always* (removes the attribute), *Show when…* or *Hide when…*.
  - **Rule rows:** each row has a type SelectControl (Step, Needs shipping, Coupon applied, Subtotal, Logged in) listing only types not already used, and a remove button. Parameters:
    - **Step:** a SelectControl of "1. <title>" … "N. <title>" from the first Steps block in the editor, plus "Last step".
    - **Subtotal:** a SelectControl *at least* (`gte`) or *below* (`lt`), plus a NumberControl (min 0, step 0.01) suffixed with `currencySymbol`.
  - An "Add condition" button.
  - A one-line summary, e.g. "Hidden on the last step" or "Shown when logged in and a coupon is applied".
  - **Notices:**
    - "This layout has no Steps block, so step conditions never match." when a Step rule exists and the layout has no Steps block.
    - "Fields inside stay required while this block is hidden." when the block contains a Billing, Shipping, Field, Email, Order Note or Extra Field block.
- **Canvas marker:** a block with a setting gets `is-yaycheckout-conditional`, which shows a small "Conditional" tag while hovered or selected.
- **Visibility preview:** a `PluginSidebar`, eye icon, pinned in the header beside Layout design.
  - Controls: Step (Off, 1…N, Last step), Logged in, Needs shipping, Coupon applied (toggles), Subtotal (NumberControl).
  - While the sidebar is open and Step is not Off or any toggle is set, each conditional block whose `isVisible()` is false for those inputs gets `is-yaycheckout-preview-hidden` (`opacity: .35`, 1px dashed outline). Blocks stay selectable.
  - Preview values live in component state, never in the post, and closing the sidebar clears them.

**Edge cases and failure modes:**
- **Excluded block with a setting** (pasted by hand): ignored by the server. The panel never offers it.
- **Copied block:** carries its setting, which is intended.
- **Live script fails to load:** the page stays as the server printed it (step-1 state).
- **Preview link:** evaluates exactly as the live storefront.
- **Hide When Empty on the same container:** both classes apply independently, and either hides it.
- **Pinned Group whose children are all hidden:** the bar keeps its own padding and background, which is the merchant's layout choice.
- **A hidden Place Order on a single-step layout with "Show when last step":** the only step is step 1 and also last, so it shows.
- **Multi-currency plugins:** the subtotal compares in the cart's currency. This is a known limit.
- **Logged-in status:** fixed for the page load. WooCommerce's checkout login reloads the page.

**Decision record:** `docs/decisions/2026-10-13-block-visibility-in-block-markup.md`. Visibility lives as a block attribute evaluated by a PHP owner with a TypeScript mirror for live updates. This is costly to undo once layouts store it.

## Alternatives considered

| Option | Why rejected |
|---|---|
| B: server only, conditional blocks re-rendered as `update_order_review` fragments | Fragments are whole sections, arbitrary blocks would need a new fragment path, and step changes still need the browser. |
| C: cart and shopper decided once at page load, steps by CSS `:has()` | Applying a coupon or changing the address would not update visibility until a reload, exactly where Coupon applied and Needs shipping matter. |
| Make fields inside a hidden block optional and unposted | The user chose to leave that to the merchant. It needs server and browser to agree on every hidden field at order time. |
| Restrict the setting to containers and buttons | The user chose every block. Wrapping in a Group just to gate it adds markup. |
| "Any of" matching, or one condition per block | The user chose all-must-match. Any-of is harder to explain, and one-per-block forces nesting. |
| Pro only | Steps and Pin to bottom ship in Free, and the setting is small. |

## Rollout

- **Existing layouts:** none use the attribute, so nothing changes and no script loads.
- **Release note:** add "Show or hide any block by step, cart or login (block sidebar → Visibility)."

## Open questions

None.
