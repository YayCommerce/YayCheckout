# Phase 4: Stepper shape, border colour and size

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Branch:** `trace/feat/order-items-stepper-style`, from `dev/edd` after Phase 3 merges
**Depends on:** Phase 1 Task 1 (controls row), Phase 2 Task 1 (`quantityDisplay: stepper`)
**Delivers:** Styles → Quantity stepper with Shape (Boxed | Round), Border colour and Size; Editorial fashion uses Round as in its mock.
**Exit criterion:** acceptance criterion 5, run as Task 2 Step 3.

The look is a few custom properties with `currentColor` fallbacks, not colour controls per part: the stepper follows the block's text colour, so it suits light and dark panels with nothing set.

### Task 1: Add the stepper style attributes, panel and CSS

**Files:**
- Modify: `packages/blocks/src/order-summary-items/block.json`
- Modify: `packages/blocks/src/order-summary-items/edit.tsx`
- Modify: `packages/blocks/src/order-summary-items/style.css`
- Modify: `includes/Renderer/CheckoutFragments.php` (`print_summary_block()`, `order_items_badge_style()` → `order_items_style_vars()`, `order_items_options()`, `order_items_html()`)

**Interfaces:**
- Produces: attributes `stepperStyle`, `stepperBorderColor`, `stepperSize`; option key `stepper_style`; `order_items_style_vars()`; `itemsStyleVars()`; root modifier `.yaycheckout-order-summary-items--stepper-round`; wrapper properties `--yaycheckout-stepper-border`, `--yaycheckout-stepper-size` (README contract).

- [ ] **Step 1: Attributes and PHP**

`block.json`: `stepperStyle` enum `[ "boxed", "round" ]` default `"boxed"`; `stepperBorderColor` string default `""`; `stepperSize` number, no default.

Rename `order_items_badge_style()` to `order_items_style_vars()` and update its docblock (it now also mirrors `itemsStyleVars()`). Add `stepperBorderColor` → `--yaycheckout-stepper-border` to its colour map (through `css_color()`), and `stepperSize` → `--yaycheckout-stepper-size: <clamped 20–40>px`. `order_items_options()`: `'stepper_style' => $choice( 'stepperStyle', [ 'boxed', 'round' ] )`. `order_items_html()`: add `self::ORDER_ITEMS_CLASS . '--stepper-round'` to `$root` when `stepper_style` is `round` and `quantity_display` is `stepper`.

- [ ] **Step 2: CSS in `style.css`**

Boxed (default) keeps today's look, reading the new properties:
- `.yaycheckout-qty`: `--yaycheckout-qty-border: var(--yaycheckout-stepper-border, color-mix(in srgb, currentColor 25%, transparent))`, `--yaycheckout-qty-size: var(--yaycheckout-stepper-size, 32px)`; `height` and the button `width` use `--yaycheckout-qty-size`; the input's fixed `40px` becomes `calc(var(--yaycheckout-qty-size) * 1.25)`, which is still 40px at the default 32px.

Round, under `.yaycheckout-order-summary-items--stepper-round`, matching the Editorial mock:
- `.yaycheckout-qty`: `--yaycheckout-qty-size: var(--yaycheckout-stepper-size, 24px)`, `gap: 10px`, `align-items: center`, no border, no radius, `overflow: visible`.
- `.yaycheckout-qty__button`: `width` and `height` the size, `border: 1px solid var(--yaycheckout-qty-border)`, `border-radius: 50%`; `:hover:not(:disabled)` background `color-mix(in srgb, currentColor 15%, transparent)`.
- `.yaycheckout-qty__input`: no inline borders, `width: 3ch`, `font-variant-numeric: tabular-nums`.

- [ ] **Step 3: Editor**

Rename `badgeStyle()` to `itemsStyleVars()` adding the two stepper properties with the same clamp. Add a second Styles ToolsPanel **Quantity stepper** (panelId `yaycheckout-order-summary-items-stepper`, className `yaycheckout-tools-panel`), shown while `quantityDisplay === 'stepper'`, built like the badge panel: a ToolsPanelItem **Shape** (ToggleGroupControl or SelectControl, Boxed | Round, `isShownByDefault`, resets to `boxed`), a ColorGradientSettingsDropdown with **Border**, and a ToolsPanelItem **Size** (RangeControl 20–40, `initialPosition` 32 for Boxed and 24 for Round, `allowReset`). `resetAll` sets `stepperStyle: 'boxed', stepperBorderColor: '', stepperSize: undefined`. The canvas root gets the round modifier as in `order_items_html()`.

- [ ] **Step 4: Verify**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutFragments.php`
Expected: build succeeds; lint prints no errors; `git grep -n "order_items_badge_style\|badgeStyle(" -- includes packages` prints nothing.

Editor (fresh layout from Upsell & cross-sell, Items selected):
- Nothing set: the stepper looks as before this phase (one 32px bordered box).
- Shape Round: two separate 24px circles with the number between, no outer box.
- Border colour red: the boxed outline and dividers, or the circles, turn red. Size 40: the stepper grows; Reset all returns to the default.
- Show as Inline: the Quantity stepper panel disappears.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/order-summary-items/block.json`, `packages/blocks/src/order-summary-items/edit.tsx`, `packages/blocks/src/order-summary-items/style.css`, `includes/Renderer/CheckoutFragments.php`
Message: `feat(blocks): add Shape, Border colour and Size to the Items quantity stepper`

### Task 2: Editorial fashion uses the Round stepper

**Files:**
- Modify: `templates/layouts/editorial-fashion/markup.php` (the Items block comment)

**Interfaces:**
- Consumes: Task 1's attributes.

- [ ] **Step 1: Set the attributes**

Add `"stepperStyle":"round","stepperBorderColor":"#ffffff99"` to the Items block: the mock's `rgba(255,255,255,.6)` button border written as hex, because WordPress's `safecss_filter_attr()`, which `get_block_wrapper_attributes()` runs the wrapper style through, drops any `rgb()`/`rgba()` value. Leave `stepperSize` unset: Round's 24px default is the mock's size.

- [ ] **Step 2: Check the template still publishes**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: OK.

- [ ] **Step 3: End-to-end check (acceptance criterion 5)**

Create new layouts from Editorial fashion and Upsell & cross-sell. In the editor: no block-validation warning; Editorial shows white round buttons on the terracotta panel, Upsell the boxed stepper; both on their own row under the name.

Storefront for each (preview token as in Phase 1 Task 1 Step 3, an ordinary product in the cart), at 1280px and at 375px:
Expected: the stepper sits on its own row below the name; Editorial's circles are white-bordered, 24px, the number between them white; Upsell's is the 32px box. Click +: the quantity reads 2 and the totals update; after the refresh the shape, border colour, badge (if on) and links (if on) are unchanged.

Compare Editorial with the mock's item row (`~/Downloads/WooCommerce Checkout Templates_v1.1.html`, template 08).
Expected: the stepper matches in shape, size and spacing.

Delete the throwaway layouts afterwards, with the user's OK.

- [ ] **Step 4: Commit and close the plan**

Set `**Status:** done <date>` in this plan's `README.md` and every phase row to `done`.
Stage: `templates/layouts/editorial-fashion/markup.php`, `docs/plans/2026-10-16-order-summary-items-quantity/README.md`
Message: `feat(templates): give Editorial fashion the round quantity stepper`
