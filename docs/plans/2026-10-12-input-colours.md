# Input Colours Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking.

**Status:** active
**Goal:** Two new layout design tokens, input background and input text colour, set in the Layout Design sidebar's Inputs panel and drawn the same in the editor canvas and on the storefront.
**Spec:** none. Planned from the request and the answers below.
**Architecture:** A design token is one entry in `LayoutDesign::schema()`, then `DesignStyles::vars()` and its copy in `design-css.ts` (docs/conventions.md § "Settings and design are declared once"). The REST schema, meta default, sanitize callback, import projection and editor sidebar all read `schema()`, so no other copy of the rules changes. `checkout-base.css` reads the two new properties, falling back to what it reads today.
**Touches:** `includes/Models/LayoutDesign.php`, `includes/Renderer/DesignStyles.php`, `packages/blocks/src/shared/design-css.ts`, `assets/css/checkout-base.css`, `packages/blocks/src/checkout/layout-design.tsx`, `docs/architecture.md`. Shared code: `packages/blocks/src/shared/design-css.ts` is also the SPA's `@blocks-shared`, but the SPA does not import `designCss` (`git grep designCss apps/` is empty). A type-check is still part of verification.
**Tests:** none. CLAUDE.md's test rule names no design-token logic, and this is not a bug fix. Verification is manual.

## Requirements

1. `inputs.background` (type `color`) and `inputs.text` (type `color`), both defaulting to `''`. Each takes `''`, a `#rrggbb` hex or a theme colour preset `var(--wp--preset--color--…)`. Neither takes a gradient.
2. When set, the background fills every input box: `.input-text`, `select`, `textarea`, the coupon code and the select2 box. When it is empty, the boxes keep today's `var(--yaycheckout-color-background, transparent)`.
3. When set, the text colour applies to the typed text in those boxes, to the placeholder (which keeps its 0.55 opacity) and to select2's shown value. When it is empty, the boxes keep today's `inherit`. The default border (`--yaycheckout-border-color`, mixed from `currentColor`) follows the input text colour. An explicit `colours.border` still wins.
4. select2's dropdown (appended to `body`) uses the input background and text colour, falling back to today's `var(--yaycheckout-color-background, #fff)` and `var(--yaycheckout-color-text, inherit)`.
5. In the Layout Design sidebar's **Inputs** panel, a "Custom colours" picker group (the same control as the Colours panel: theme swatches, custom colour, clear) is shown above Style, Size and Radius, with the items **Background** and **Text**.
6. Stored layouts, templates and imports without the new keys read them as `''`, so they print exactly what they print today.
7. The editor canvas reprints the new tokens live as the merchant picks them, with the same CSS text PHP prints for the same tokens.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types (also none of the extras in `.claude/rules/php-standards.md`).
- The feature is in Free; nothing goes under `includes/Pro/`.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks`.
- `design-css.ts` must print the same text as `DesignStyles::css()` without custom CSS for the same tokens: same names, same order. Change both in the same commit.
- Every changed CSS declaration falls back to the exact value it uses today.
- Branch from `dev/edd` as `trace/feat/input-colours`. Conventional Commits, with CLAUDE.md's `Co-Authored-By` trailer.
- Editor checks run on a fresh throwaway layout or a published one, never on an existing draft, because autosave writes into drafts.

## Task 1: Tokens, printing and storefront CSS

Delivers the two tokens end to end on the storefront and in the canvas. There is no sidebar control yet, so a value is set through wp-cli.

**Interfaces**
- `LayoutDesign::schema()['inputs']` gains, in this order after `radius`: `'background' => [ 'type' => 'color', 'default' => '' ]` and `'text' => [ 'type' => 'color', 'default' => '' ]`.
- New custom properties: `--yaycheckout-input-background` (from `inputs.background`) and `--yaycheckout-input-color` (from `inputs.text`). Each is printed only when its token is not `''`.

Steps:
- [x] 0. Copy this plan to `docs/plans/2026-10-12-input-colours.md`. Branch `trace/feat/input-colours` from `dev/edd`.
- [x] 1. `includes/Models/LayoutDesign.php`: add the two `inputs` entries above. Update the `schema()` docblock's sentence "the background may also be a gradient" to "`colours.background` may also be a gradient", because `inputs.background` cannot be one.
- [x] 2. `includes/Renderer/DesignStyles.php` `vars()`: after the `input-padding-inline` block, append `input-background` => `$inputs['background']` and then `input-color` => `$inputs['text']`, each only when it is not `''`. Change the padding rule so that an underline input with a background keeps 12px side padding, because a filled box with 0 padding puts the text against its edge: `input-padding-inline` is `'0'` only when the style is `underline` and `$inputs['background']` is `''`; otherwise it is `'12px'`. That variable is still printed only when `style` is not `''`.
- [x] 3. `packages/blocks/src/shared/design-css.ts` `vars()`: the same two pushes and the same padding condition, in the same order (`String( inputs.background ?? '' )`, `String( inputs.text ?? '' )`).
- [x] 4. `assets/css/checkout-base.css`:
  - In the input rule at line ~268, `background-color` becomes `var(--yaycheckout-input-background, var(--yaycheckout-color-background, transparent))` and `color` becomes `var(--yaycheckout-input-color, inherit)`. Placeholder and select2's rendered value already use `inherit`, so they follow.
  - In the `body.yaycheckout-page .select2-dropdown` rule at line ~319, `background-color` becomes `var(--yaycheckout-input-background, var(--yaycheckout-color-background, #fff))` and `color` becomes `var(--yaycheckout-input-color, var(--yaycheckout-color-text, inherit))`.
  - Update the comment above the input rule to name the input background and text colour tokens.
- [x] 5. `docs/architecture.md` (Rendering paragraph, the gradient sentence): add that inputs read `--yaycheckout-input-background` first and only then `--yaycheckout-color-background`.
- [x] 6. Verify, PHP: `composer lint -- includes/Models/LayoutDesign.php includes/Renderer/DesignStyles.php`. Expected: no errors.
- [x] 7. Verify, printing. Use wp-cli with the site socket (memory `local-site-testing`) and run:
  `wp eval 'use YayCheckout\Models\LayoutDesign; use YayCheckout\Renderer\DesignStyles; $d = LayoutDesign::from_meta( [ "inputs" => [ "style" => "underline", "size" => "", "radius" => null, "background" => "#fafafa", "text" => "var(--wp--preset--color--contrast)" ] ] ); echo ( new DesignStyles() )->css( $d, "", false ); echo json_encode( LayoutDesign::from_meta( [ "inputs" => [ "background" => "linear-gradient(red, blue)" ] ] )->sections["inputs"] );'`
  Expected:
  - The `:root` block ends with `--yaycheckout-input-radius: 0px;`, `--yaycheckout-input-border-width: 0 0 1px;`, `--yaycheckout-input-padding-inline: 12px;`, `--yaycheckout-input-background: #fafafa;` and `--yaycheckout-input-color: var(--wp--preset--color--contrast);`. No page rule is printed.
  - The JSON has `"background":""`, because the gradient was refused.
  - `from_meta( [] )` prints `''`, with no `input-background` or `input-color` line.
- [x] 8. Verify, storefront: on a published throwaway layout, set the meta with `wp post meta update <id> <Layout::META_DESIGN> '<json>' --format=json`, using the token values from step 7 with style `boxed`. Open its preview, `?page_id=9&yaycheckout_preview=<PreviewToken::mint(id, 30)>`. Expected:
  - Every text input, select, textarea, the coupon code and the country select2 box has a `#fafafa` background and the contrast-coloured text.
  - The placeholder is the same colour, faded.
  - The open country dropdown matches.
  - With both tokens cleared again, the computed `background-color` and `color` of `#billing_first_name` equal those on `dev/edd`.
- [x] 9. Verify the build: `pnpm build:blocks` succeeds, and `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` reports no new errors compared with `dev/edd`.
- [x] 10. Commit `includes/Models/LayoutDesign.php`, `includes/Renderer/DesignStyles.php`, `packages/blocks/src/shared/design-css.ts`, `assets/css/checkout-base.css`, `docs/architecture.md` and `docs/plans/2026-10-12-input-colours.md` with the message `feat(design): add input background and text colour tokens`.

## Task 2: Inputs panel colour pickers

Consumes Task 1's `schema()['inputs']` entries. The sidebar reads the schema from PHP (`BlockRegistry` → `editorData.design.schema`), so `normalize()` and `sectionTokens()` already carry the new keys, and `SectionPanel` already skips colour rules in its token loop.

Steps:
- [ ] 1. `packages/blocks/src/checkout/layout-design.tsx`:
  - `SectionPanel` renders `<ColourSettings>` whenever the section's rules contain a colour rule (`Object.values( design.schema[ section ] ).some( isColourRule )`), instead of only for `'colours' === section`. It stays first in the panel.
  - `ColourSettings` takes a `panelId: string` prop and passes it to both `ToolsPanel` and `ColorGradientSettingsDropdown`, in place of the shared `SIDEBAR`. `SectionPanel` passes `` `${ SIDEBAR }-${ section }` ``, so the two panels' items don't register with each other.
  - `TOKEN_LABELS` already has `background: 'Background'` and `text: 'Text'`, so no new strings are needed.
- [ ] 2. Verify: run `pnpm build:blocks`, then open a fresh throwaway layout in the editor and open the Layout Design sidebar → Inputs. Expected:
  - "Custom colours" with Background and Text appears above Style, Size and Radius, and the Colours panel is unchanged.
  - Picking a theme swatch for Background recolours the canvas inputs at once.
  - Its stored value is `var(--wp--preset--color--<slug>)`: read it with `wp.data.select('core/editor').getEditedPostAttribute('meta')`.
  - The control offers no gradient tab.
  - Clear sets the value back to `''`, and the canvas inputs return to the page background.
  - Reset all in the Inputs colour group clears only those two tokens.
  - After Save and a reload, the values persist, and the storefront preview shows the same colours.
  - Choosing Underline still hides Radius, while the colour group stays.
- [ ] 3. Verify: the Colours panel's own Reset all still clears only Primary, Text, Background and Border.
- [ ] 4. Commit `packages/blocks/src/checkout/layout-design.tsx` with the message `feat(blocks): pick input background and text colour in the Inputs panel`.

## Review focus

- **Mirror drift:** `DesignStyles::vars()` and `design-css.ts` must agree on names, order and the new padding condition, or the canvas differs from the storefront. Task 1 step 7 checks PHP; Task 2 step 2 checks that the canvas and the preview agree.
- **Existing layouts:** a stored design lacks the two keys. `from_meta()` and the editor's `normalize()` fill in `''`, and the REST schema's `required` is met because the editor always saves the full shape. Task 1 step 8 (cleared tokens match `dev/edd`) guards against a visual change for existing layouts.
- **Underline with a background:** the padding change is the only behaviour change to an existing token's output, and it applies only when a background is set (Task 1 step 7).
- **Contrast:** a dark input background with no input text colour leaves page-coloured text in the box. This is accepted: the merchant sets both, and no automatic contrast is added.
