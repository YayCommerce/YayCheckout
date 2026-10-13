# Steps Progress Colors Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements section below is the contract.

**Status:** done 2026-10-13
**Goal:** Three per-block colour settings for Steps Progress (Active, Inactive, Disabled text) in its Styles tab, applied the same in all five styles in the editor and on the storefront; the "Step N of M" count follows the block's Text colour.
**Spec:** none, planned from the request
**Architecture:** The Order Summary Items badge pattern: plain string attributes set by a `PanelColorSettings` in the Styles tab → `--yaycheckout-steps-progress-*` variables on the block wrapper (editor: `useBlockProps` style; storefront: `CheckoutStepsProgress::markup()`) → `style.css` reads each variable with today's colour as the fallback. Plain attributes rather than `style.yaycheckout.*` keys (the Shipping Methods and Payment pattern), because three fixed colours need no per-layout key sets or ToolsPanel.
**Touches:** `packages/blocks/src/steps-progress/style.css`, `block.json`, `edit.tsx`; `includes/Renderer/CheckoutStepsProgress.php`; `includes/Renderer/CheckoutFragments.php` (shared: `css_color()` goes from private to public; its callers in that class are unchanged).
**Tests:** none. Per CLAUDE.md's test rule, a wrong progress colour can't silently break a checkout; each task has a manual check.

## Requirements

- **Goal:** a merchant can recolour the Steps Progress block without touching the layout's primary colour.
- **Out of scope:** the Steps block's own headers and vertical stepper (template 11, Material stepper); the white number and check inside an active marker; the white fill of a locked circle; the focus outline; per-variant colour sets; the Custom CSS escape hatch.
- **Acceptance criteria:**
  1. The Steps Progress Styles tab has a **Progress colors** panel with three pickers: **Active**, **Inactive**, **Disabled text**. Each can be cleared.
  2. **Active** (default: the layout's primary colour) paints the current and completed circles, the completed connector line, the segment fill, the progress bar fill and the current dot.
  3. **Inactive** (default: today's greys `#d1d5db`, `#e0e0e0`, `#ccc`) paints the connector lines, the locked circle ring, the locked dot, the dots' hairlines, the empty segment track and the bar track.
  4. **Disabled text** (default: today's greys `#9ca3af`, `#6b7280`, `#757575`, `#949494`) paints the locked circle's number, locked step titles, and the Dots style's locked titles: the steps the shopper hasn't reached. A locked step the shopper can reopen keeps the inherited title colour, as today.
  5. The "Step N of M" count (Progress bar and Text only styles) and the Dots style's completed step titles follow the block's own **Text** colour (Styles → Color) when one is set, and stays today's `#757575` otherwise. Disabled text does not touch them.
  6. With no colour set, every style looks exactly as it does on `dev/edd`.
  7. The editor canvas and the storefront show the same colours, and a theme palette pick and a custom hex both work.
  8. End to end: on a throwaway layout with Steps and Steps Progress, set Active `#e11d48`, Inactive `#fde68a`, Disabled text `#0f766e`, Text `#7c3aed`, and save. The storefront preview shows those colours in all five styles, and they persist through moving to step 2 and back.
- **Decided with the user:** which colours (after checking templates 02, 06, 09 and 11 in `~/Downloads/WooCommerce Checkout Templates_v1.1.html`): Active + Inactive + Disabled text. The "Step N of M" count follows the block's Text colour, not Disabled text. "Disabled text" was renamed **Disabled text** for clarity; the Dots style's completed titles moved out of it to the Text colour so the name stays accurate. Keep it as simple as possible. After implementation the pickers were renamed for what they paint: Active → **Fill**, Inactive → **Track**, Disabled text → **Upcoming text** (attributes `fillColor`, `trackColor`, `upcomingTextColor`; variables `--yaycheckout-steps-progress-fill`, `-track`, `-upcoming-text`), and each picker is `clearable`.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- WordPress floor 6.5.
- The feature is in Free; nothing goes under `includes/Pro/`.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- Every new CSS variable falls back to the exact value the rule uses today.
- Run commands from the repo root on Node 22 (`nvm use`).
- Branch from `dev/edd` as `trace/feat/steps-progress-colors`; Conventional Commits; land through a PR into `dev/edd`.
- Editor checks run on a **new throwaway draft layout** created for this work, never an existing one: Gutenberg autosaves drafts into the post. Delete it at the end only with the user's OK.

## Review focus

- No colour set must mean no visual change in any of the five styles, including the segments gradient, which carries both Active and Inactive in one `background` (Task 1 Step 2).
- The bar style already prints `style="--yaycheckout-steps-progress-value:…"` and `view.ts` rewrites that one property with `setProperty`; the colour variables must sit in the same `style` attribute and survive a step change (Task 3 Step 2).
- A value that isn't a colour (hand-edited markup such as `red;}body{display:none`) must print nothing, not break out of the `style` attribute (Task 3 Step 2).
- The reopenable-step rule (`[data-state="locked"]:has(> button:enabled)`) must still beat Disabled text (Task 3 Step 2).
- Setting the block's Text colour must recolour the count but not the locked titles, which stay on Disabled text (Task 2 Step 4, Task 3 Step 2).

## Manual test setup (used by every verification step)

- Site: `http://yaydevs.local`, checkout `?page_id=9`. wp-cli needs Local's socket: `php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/DmdPSy05S/mysql/mysqld.sock" /opt/homebrew/bin/wp …`.
- Create a draft layout named "Steps progress colors test" with a Steps block of three Step blocks and a Steps Progress block above it. Note its post ID as `<id>`.
- Preview link: `wp eval 'print_r( \YayCheckout\Helpers\PreviewToken::mint( <id>, 120 ) );'` (it returns an array; use `token`), then open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` with a product in the cart (`?add-to-cart=29`).

---

### Task 1: Read the colour variables in `style.css`

**Files:**
- Modify: `packages/blocks/src/steps-progress/style.css`

**Interfaces:**
- Consumes: nothing.
- Produces: `--yaycheckout-steps-progress-fill`, `--yaycheckout-steps-progress-track`, `--yaycheckout-steps-progress-upcoming-text`, read from the `.yaycheckout-steps-progress` wrapper. Tasks 2 and 3 set them.

- [ ] **Step 1: Wrap each colour in its variable, keeping today's value as the fallback**

`X` is the value the rule has today, kept verbatim as the fallback: `var(--yaycheckout-steps-progress-<name>, X)`.

| Variable | Rules (selector → property) |
|---|---|
| `-active` | `__item[data-state="complete"]:not(:last-child)::before` background; `__marker` background; segments `__item::before` gradient's two primary stops; bar `__fill` background |
| `-inactive` | `__item:not(:last-child)::before` background (`#d1d5db`); locked `__marker` box-shadow colour (`#d1d5db`); dots `__item:not(:last-child)::after` background; dots locked `__marker` background; segments `__item::before` gradient's trailing `#e0e0e0`; segments locked `__marker` box-shadow colour (`#ccc`); bar `__track` background |
| `-disabled` | locked `__marker` color (`#9ca3af`); locked `__title` color (`#6b7280`); dots locked `__title` color (`#949494`); segments locked `__marker` and `__title` color (`#757575`) |

Add one rule so the count follows the block's Text colour: `.yaycheckout-steps-progress.has-text-color :is(.yaycheckout-steps-progress__count, .yaycheckout-steps-progress__item[data-state="complete"] .yaycheckout-steps-progress__title) { color: inherit; }` (the count, and the Dots style's completed titles; core adds `has-text-color` to the wrapper in the editor and on the storefront whenever Text is set; this selector outranks `.is-variant-bar .yaycheckout-steps-progress__count` and `.is-variant-dots …[data-state="complete"] .yaycheckout-steps-progress__title`).

Leave unchanged: the bar and text `__count` colour `#757575`, the dots completed `__title` colour `#757575`, the locked marker's `#fff` background, every `color: #fff`, the focus outline, and the `:has(> button:enabled)` rule.

- [ ] **Step 2: Verify it is a no-op**

Run: `pnpm build:blocks`
Expected: `webpack … compiled successfully`.
Open the throwaway layout's preview, switch the block through all five styles in the editor (save each), and compare against the same layout on `dev/edd`'s build.
Expected: identical in every style; DevTools on `.yaycheckout-steps-progress__marker` shows `var(--yaycheckout-steps-progress-fill, var(--yaycheckout-primary-color, #3858e9))` resolving to the primary colour.

- [ ] **Step 3: Commit**

Stage: `packages/blocks/src/steps-progress/style.css`
Message: `refactor(steps-progress): read colours from CSS variables`

### Task 2: Attributes and the editor panel

**Files:**
- Modify: `packages/blocks/src/steps-progress/block.json` (`attributes`)
- Modify: `packages/blocks/src/steps-progress/edit.tsx`

**Interfaces:**
- Consumes: Task 1's variables.
- Produces: attributes `fillColor`, `trackColor`, `upcomingTextColor`: `{ "type": "string", "default": "" }`, holding a colour value as `PanelColorSettings` returns it. Task 3 reads them.

- [ ] **Step 1: Add the three attributes to `block.json`**

- [ ] **Step 2: Add `progressStyle( attributes: Attributes ): Record< string, string >` in `edit.tsx`**

Maps each non-empty attribute to its variable (`fillColor` → `--yaycheckout-steps-progress-fill`, `trackColor` → `-inactive`, `upcomingTextColor` → `-disabled`), as `badgeStyle()` does in `order-summary-items/edit.tsx`. Doc comment: `Mirrors CheckoutStepsProgress::color_style().` Pass it as `style` to the existing `useBlockProps()` call, which every branch (preview, notice, list) shares.

- [ ] **Step 3: Add the panel**

In the main return, beside the existing `<InspectorControls>`: `<InspectorControls group="styles">` holding a `PanelColorSettings` titled `__( 'Progress colors', 'yaycheckout' )`, `initialOpen={ false }`, with three settings labelled `Active`, `Inactive`, `Disabled text`, each `onChange: ( value?: string ) => setAttributes( { <attr>: value ?? '' } )`.

- [ ] **Step 4: Verify in the editor**

Run: `pnpm build:blocks`
Open: the throwaway layout in the editor, select Steps Progress, Styles tab.
Action: set Active `#e11d48`, Inactive `#fde68a`, Disabled text `#0f766e`, and Styles → Color → Text `#7c3aed`; switch through the five styles.
Expected: the panel shows the three pickers; the canvas recolours immediately in every style (step 1 current, others locked); in Progress bar and Text only the count is `#7c3aed`, and clearing Text brings it back to `#757575`; clearing a picker brings back today's colour; a theme palette swatch also applies.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/steps-progress/block.json`, `packages/blocks/src/steps-progress/edit.tsx`
Message: `feat(steps-progress): add Active, Inactive and Disabled text colours in the editor`

### Task 3: Print the colours on the storefront

**Files:**
- Modify: `includes/Renderer/CheckoutFragments.php` (`css_color()`: `private` → `public`, nothing else)
- Modify: `includes/Renderer/CheckoutStepsProgress.php` (`markup()`, new `color_style()`)

**Interfaces:**
- Consumes: Task 2's attributes; `CheckoutFragments::css_color( $value ): string` (accepts a hex, a safe CSS colour token or `var:preset|color|<slug>`; returns `''` for anything else).
- Produces: `private static function color_style( array $attributes ): string` in `CheckoutStepsProgress`, returning `--yaycheckout-steps-progress-fill:<c>;…` for each set attribute, or `''`.

- [ ] **Step 1: Implement `color_style()` and use it in `markup()`**

Each attribute goes through `CheckoutFragments::css_color()`; empty results are skipped. In `markup()`, `$args['style']` becomes the colour declarations followed by the bar's `--yaycheckout-steps-progress-value` declaration when the variant is `bar`; omit `style` when both are empty. Doc comment: `Mirrors progressStyle() in steps-progress/edit.tsx.`

- [ ] **Step 2: Verify on the storefront**

Run: `pnpm build:blocks`, then `composer lint -- includes/Renderer/CheckoutStepsProgress.php includes/Renderer/CheckoutFragments.php`
Expected: build succeeds; lint clean.
Open: the preview link, with the colours from Task 2 saved.
Expected, for each of the five styles (change and save in the editor, reload the preview):
- the colours match the editor canvas (acceptance criteria 2–5);
- bar: after Continue to step 2, the fill is at 2/3 width and still `#e11d48`, and the wrapper's `style` holds both the colour variables and the updated `--yaycheckout-steps-progress-value`;
- bar and text: the count is `#7c3aed` (the block's Text colour), not `#0f766e`;
- circles: after reaching step 2, step 1's title (reopenable) keeps the inherited colour, not `#0f766e`.
Then: `wp post get <id> --field=post_content | sed 's/"fillColor":"#e11d48"/"fillColor":"red;}body{display:none"/' | wp post update <id> -`, reload the preview, and afterwards set Active back in the editor.
Expected: no `--yaycheckout-steps-progress-fill` in the wrapper's `style`; the page renders normally.

- [ ] **Step 3: Commit**

Stage: `includes/Renderer/CheckoutStepsProgress.php`, `includes/Renderer/CheckoutFragments.php`
Message: `feat(steps-progress): print the progress colours on the storefront`

- [ ] **Step 4: Final check (acceptance criterion 8)**

Run acceptance criterion 8 end to end on the throwaway layout, then ask the user before deleting it.
Expected: every bullet of criterion 8 holds.
