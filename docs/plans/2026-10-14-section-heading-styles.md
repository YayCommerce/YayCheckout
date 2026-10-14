# Section Heading Level, Title and Description Styles Implementation Plan

**Status:** active
**Goal:** Billing, Shipping, Shipping Methods and Payment get a heading-level choice (H1–H6, default H3) and Styles-tab panels for their title and description: text colour plus core's typography controls. Billing and Shipping also get a Description field.
**Branch:** work on `dev/edd` as asked; no new branch. Nothing is committed unless you ask. CLAUDE.md says changes reach `dev/edd` only through a PR, so say whether to commit there or open a PR when the work is done.
**Tests:** none. These are block options and CSS, and none of it is logic the CLAUDE.md test rule lists. Exact manual checks are below.

## Context

All four blocks print a hard-coded `<h3>` (`packages/blocks/src/{billing,shipping,payment,shipping-methods}/render.php`). The merchant can't fix the outline (for example H2 under a page H1) or style the title and description apart from the block's text colour. Payment and Shipping Methods print `p.yaycheckout-section-description`; Billing and Shipping have no description.

Order Summary Breakdown and Total already do per-part styles: `SummaryPartPanels` (`packages/blocks/src/shared/summary-part-styles.tsx`) stores `{ typography, color }` under `style.yaycheckout.<part>`. In the editor `compileCSS()` turns it into CSS. On the storefront `SummaryPartStyles::wrapper_class()` (`includes/Renderer/SummaryPartStyles.php`) queues the same rules with `wp_style_engine_get_styles()` under a unique wrapper class. This plan reuses that model for `title` and `description`.

## Stored shape

- New attribute `headingLevel` (number, default `3`) on all four `block.json`; `description` (string, default `""`) on Billing and Shipping.
- Styles go in the existing `style` attribute: `style.yaycheckout.title` and `style.yaycheckout.description`, each `{ typography: {...}, color: { text } }`. They don't clash with Payment and Shipping Methods' own `style.yaycheckout.*` keys (`borderWidth`, `radio`, …). Billing and Shipping have no `style` attribute in `block.json`, but core adds one through their color and spacing supports.
- Stored layouts have none of these keys, so each `render.php` falls back to H3, no description and no extra rules. The markup stays the same, apart from the new class on the heading.

## Tasks

### 1. Shared editor panels: split the generic part from `summary-part-styles.tsx`

- New `packages/blocks/src/shared/text-part-styles.tsx`: move the ToolsPanel body of `SummaryPartPanels` there, plus `presetList`, `fromPreset` and `partStyle`. It becomes `TextPartPanels( { parts: { key, label }[], panelIdPrefix, style, onChange } )`, and `textPartCSS( style, part, selector )` returns `compileCSS( partStyle( style, part ), { selector } )`.
- `summary-part-styles.tsx` keeps its exports (`SummaryPartPanels`, `summaryPartCSS`) as thin wrappers over these, so Breakdown and Total don't change.
- New `packages/blocks/src/shared/section-heading.tsx`:
  - `HEADING_LEVELS = [1,2,3,4,5,6]` and `headingLevel( value )`, which clamps to 1–6 with default 3. Comment: `Mirrors SectionTextStyles::heading_tag() in PHP`.
  - `SectionHeadingToolbar`: `<BlockControls group="block"><HeadingLevelDropdown options={HEADING_LEVELS} value onChange /></BlockControls>`, shown only while Show title is on.
  - `SectionTextPanels( { style, onChange, showTitle, showDescription } )`: `TextPartPanels` with parts Title and Description. A hidden part keeps its stored values.
  - `sectionTextCSS( clientId, style )`: the editor CSS for `#block-<id> > .yaycheckout-section-title` and `#block-<id> > .yaycheckout-section-description`.
  - `SectionTitle` and `SectionDescription`: the canvas heading `h{level}.yaycheckout-section-title` (Payment and Shipping Methods also keep `yaycheckout-section-preview__heading`) and the description `<p>`.

### 2. Storefront rules in PHP

New `includes/Renderer/SectionTextStyles.php` (PHP 7.4, `final class`, modelled on `SummaryPartStyles`):
- `heading_tag( array $attributes ): string` returns `'h' . clamp( (int) headingLevel, 1, 6 )`, and `h3` when the value is missing or not numeric.
- `wrapper_class( array $attributes, string $block_name ): string`. For each of `title => '.yaycheckout-section-title'` and `description => '.yaycheckout-section-description'`, it queues `wp_style_engine_get_styles( array_intersect_key( $part, SummaryPartStyles::KEYS ), [ 'selector' => '.yaycheckout-checkout .<unique>.' . wp_get_block_default_classname( $block_name ) . ' > <part selector>', 'context' => 'block-supports' ] )`. It returns the unique class, or `''` when nothing was queued. The selector outranks `checkout-base.css`'s `.yaycheckout-checkout .yaycheckout-section-description` and `DesignStyles::HEADING_SELECTOR`.

### 3. Billing and Shipping

- `block.json`: add the `headingLevel` and `description` attributes.
- `shared/address-section-edit.tsx`: add `headingLevel`, `description` and `style` to `AddressSectionAttributes`. Under Title, add a Description `TextControl`, shown whatever Show title is, like Payment. Render `SectionHeadingToolbar` and `<InspectorControls group="styles"><SectionTextPanels …/>`, use `SectionTitle` and `SectionDescription` on the canvas, and append `sectionTextCSS()` to the existing `<style>`. `billing/edit.tsx` and `shipping/edit.tsx` only widen their attribute interfaces.
- `billing/render.php` and `shipping/render.php`: print the heading through `SectionTextStyles::heading_tag()` with `class="yaycheckout-section-title"`. The description comes from `LayoutMarkupReader::text( $attributes, 'description' )`, trimmed, and prints as `<p class="yaycheckout-section-description">` when it isn't empty. Append `SectionTextStyles::wrapper_class()` to `$yaycheckout_class`.

### 4. Payment and Shipping Methods

- `block.json`: add `headingLevel`.
- `edit.tsx`: take `clientId`. Swap the hard-coded `<h3>` and `<p>` for `SectionTitle` and `SectionDescription`. Add `SectionHeadingToolbar`. Render `SectionTextPanels` above the existing `StylePanels` in the styles group, and add `<style>{ sectionTextCSS(...) }</style>` inside the wrapper.
- `render.php`: the heading as in Task 3. Add `SectionTextStyles::wrapper_class()` to `$yaycheckout_wrapper_base['class']` before `BlockEmptiness::wrapper()`.

### 5. Docs and CSS

- `assets/css/checkout-base.css`: change the description comment to name all four sections plus Order Summary. Leave the rule alone.
- `docs/conventions.md` "In code" list: add `shared/section-heading.tsx` (`SectionTextStyles::heading_tag()` / `wrapper_class()`).
- Save this plan as `docs/plans/2026-10-14-section-heading-styles.md`.

## Review focus

- **Description opacity:** `checkout-base.css` gives the description `opacity: 0.75`, so a merchant's chosen colour shows dimmed. Accept this, or have the description rule print `opacity: 1` whenever a colour is set (a hand-added declaration next to the style-engine output). The plan as written accepts it.
- **Fragments:** Payment and Shipping Methods' `print_*` fragments replace only the list, not the wrapper. The unique class sits on the wrapper, so it survives `update_order_review`.
- **Billing/Shipping block text colour:** the whole wrapper still takes the block's colour; the Title and Description colours override it only for their element.

## Verification

1. `pnpm build:blocks` succeeds. `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` passes, because `shared/` is also the SPA's `@blocks-shared`.
2. `composer lint -- includes/Renderer/SectionTextStyles.php packages/blocks/src/{billing,shipping,payment,shipping-methods}/render.php` reports no errors. Read the new PHP for PHP 7.4 syntax.
3. Editor: create a **new** draft layout (never edit an existing draft, because autosave writes it). For each of the four blocks:
   - Set H2 in the toolbar; the canvas heading becomes `<h2>`.
   - In Styles → Title, set a colour and a font size; the canvas updates.
   - In Styles → Description, set a colour and an appearance; the description updates (on Billing and Shipping, type a description first).
   - Reload the editor; the values are kept.
4. Storefront: preview the layout at `?page_id=9`. Each heading prints the chosen tag with `yaycheckout-section-title`, and the colours and typography apply. After changing the shipping country (an AJAX refresh), the Payment and Shipping Methods styles still apply.
5. An existing published layout, with no new attributes, renders `<h3>` and looks exactly as before.
