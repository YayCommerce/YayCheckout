# A layout owns its whole design, and an empty base size prints 16px

**Purpose:** why each checkout layout carries its whole design in its own meta with no global design underneath, why an empty token is how a layout follows the theme, why the base font size is the one exception, and why custom CSS that reaches every checkout is a setting rather than a design.

**Update when:** never edit it. Supersede it with a new record if a design is shared across layouts again, if tokens get an on/off switch again, if another empty token stops following the theme, or if the site-wide custom CSS moves out of the settings.

## Status

Superseded by [2026-10-13-empty-base-size-prints-13px.md](2026-10-13-empty-base-size-prints-13px.md).

## Context

- Two designs used to combine on every checkout: the global option `yaycheckout_design`, edited on the SPA's Design page, and a layout's meta `_yaycheckout_design`, whose sections were either a full set of tokens or `null` to follow the global one. Each section also had an `enabled` switch, and the global custom CSS printed before the layout's.
- Layouts are created from templates whose `design.json` sets every section, so a layout made from a template, Blank, Reset or the setup wizard already ignored the global tokens; only the global custom CSS still reached it.
- Two places to style a checkout, with a `null` state and an `enabled` switch on top, meant three ways a colour could end up unset, and the editor sidebar had to explain all of them. The plugin had not shipped, and only dev and test sites held a global design.
- Following the theme's font size broke the checkout's proportions. Themes set body text anywhere from 13px to 22px; at 22px every field and button grew with it, and the input size steps, which are in em, all came out large. Checkout design systems size text at 16px, which is also the size at which iOS Safari does not zoom into a focused field.
- Options turned down:
  - Keeping `Models\Design` as a rules-only class beside `LayoutDesign`: two design classes, where only one has a reason to exist.
  - Keeping `enabled` in the meta, always true and hidden: a field that never changes, which the sidebar, import and docs would keep explaining.
  - Keeping `null` sections as "Theme default", with a Theme default / Custom switch per section: the merchant chose to have each section always custom, with an empty value printing nothing.
  - Migrating the global design into each layout and into the new setting: no merchant data depended on it.
  - Dropping site-wide custom CSS with no replacement, making it Pro-only, or putting it in Settings → General: merchants need CSS that reaches every checkout, custom CSS has always been Free, and Advanced is where the other technical options live.
  - Storing 16 only in the template, so new layouts start at 16px while an empty base size still follows the theme: layouts saved with an empty base size would keep the theme's size, and the merchant asked for every layout to default to 16px.

## Decision

- `Models\LayoutDesign` is the only design model, stored as `Layout::META_DESIGN`. It always holds every section of `LayoutDesign::schema()` (colours, typography, inputs) with every token, plus the layout's custom CSS. There is no `enabled` key and no `null` section.
- Every token may be empty: `''` for a colour or an enum, `null` for a size or radius. An empty token prints nothing, so the theme shows through, with one exception: an empty `typography.base_size` prints `DesignStyles::DEFAULT_BASE_SIZE` (16px), so a layout never takes the theme's font size. `0` is a value, not empty.
- Reads are forgiving: `LayoutDesign::from_meta()` reads a missing section, a `null` section, an unknown key or an invalid token as empty. Writes from the editor are strict: `LayoutDesign::rest_schema()` refuses a wrong-typed token with a 400.
- `Renderer\DesignStyles` prints one layout's design, then the site-wide custom CSS, then the layout's custom CSS. Each CSS is checked on print and dropped alone when it fails. The editor canvas prints the tokens and neither CSS.
- The site-wide custom CSS is the setting `advanced.custom_css` (`Models\Settings`, option `yaycheckout_settings`), edited in Settings → Advanced and checked with `LayoutDesign::sanitize_custom_css()`. It is not exported with a layout.
- There is no global design option, model, repository, REST route or admin page. Nothing reads `yaycheckout_design`. Uninstall's `yaycheckout_*` sweep removes a leftover row.

## Consequences

- One model changes when a token is added: its entry in `LayoutDesign::schema()`, its line in `DesignStyles::vars()` and the copy in `packages/blocks/src/shared/design-css.ts`, which must print the same text. `DEFAULT_BASE_SIZE` is copied the same way, and the sidebar's Base size control shows it while the token is empty.
- A layout's look depends only on its own meta, the site CSS and the theme. Duplicate, export and import carry the whole design.
- Changing every layout's colours at once now means editing each layout, or using the site-wide custom CSS.
- An old or partial layout, or an import file from before the global design was removed, shows the theme until the merchant sets values, at 16px. It never fails to load.
- Every layout saved with an empty base size changes from the theme's font size to 16px. A merchant who wants the theme's size sets it in the layout or in the site-wide custom CSS.
- Exporting a layout to another store leaves the site CSS behind.
