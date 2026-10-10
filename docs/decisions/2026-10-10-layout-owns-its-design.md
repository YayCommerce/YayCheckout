# A layout owns its whole design; there is no global design

**Purpose:** why each checkout layout carries its whole design in its own meta with no global design underneath, why an empty token is how a layout follows the theme, and why custom CSS that reaches every checkout is a setting rather than a design.

**Update when:** never edit it. Supersede it with a new record if a design is shared across layouts again, if tokens get an on/off switch again, or if the site-wide custom CSS moves out of the settings.

## Status

Accepted, 2026-10-10, by Trace Bui.

## Context

- Two designs combined on every checkout. The global design was the option `yaycheckout_design`, edited on the SPA's Design page. A layout's design was the meta `_yaycheckout_design`, whose sections were either a full set of tokens or `null` to follow the global one. Each section also had an `enabled` switch. On the storefront, the global custom CSS printed before the layout's.
- Layouts are now created from templates whose `design.json` sets every section. So a layout made from a template, Blank, Reset or the setup wizard already ignored the global tokens, and only the global custom CSS still reached it.
- Two places to style a checkout, with a `null` state and an `enabled` switch on top, meant three ways a colour could end up unset. The editor sidebar had to explain all of them.
- The plugin had not shipped. Only dev and test sites held a global design.
- Options turned down:
  - Keeping `Models\Design` as a rules-only class beside `LayoutDesign`: two design classes, where only one has a reason to exist.
  - Keeping `enabled` in the meta, always true and hidden: a field that never changes, which the sidebar, import and docs would keep explaining.
  - Keeping `null` sections as "Theme default", with a Theme default / Custom switch per section: the merchant chose to have each section always custom, with an empty value printing nothing.
  - Migrating the global design into each layout and into the new setting: no merchant data depended on it.
  - Dropping site-wide custom CSS with no replacement, making it Pro-only, or putting it in Settings → General: merchants need CSS that reaches every checkout, custom CSS has always been Free, and Advanced is where the other technical options live.

## Decision

- `Models\LayoutDesign` is the only design model, stored as `Layout::META_DESIGN`. It always holds every section of `LayoutDesign::schema()` (colours, typography, inputs) with every token, plus the layout's custom CSS. There is no `enabled` key and no `null` section.
- Every token may be empty: `''` for a colour or an enum, `null` for a size or radius. An empty token prints nothing, so the theme shows through. `0` is a value, not empty.
- Reads are forgiving: `LayoutDesign::from_meta()` reads a missing section, a `null` section, an unknown key or an invalid token as empty. Writes from the editor are strict: `LayoutDesign::rest_schema()` refuses a wrong-typed token with a 400.
- `Renderer\DesignStyles` prints one layout's design, then the site-wide custom CSS, then the layout's custom CSS. Each CSS is checked on print and dropped alone when it fails. The editor canvas prints the tokens and neither CSS.
- The site-wide custom CSS is the setting `advanced.custom_css` (`Models\Settings`, option `yaycheckout_settings`), edited in Settings → Advanced and checked with `LayoutDesign::sanitize_custom_css()`. It is not exported with a layout.
- There is no global design option, model, repository, REST route or admin page. Nothing reads `yaycheckout_design`. Uninstall's `yaycheckout_*` sweep removes a leftover row.

## Consequences

- One model changes when a token is added: its entry in `LayoutDesign::schema()`, its line in `DesignStyles::vars()` and the copy in `packages/blocks/src/shared/design-css.ts`, which must print the same text.
- A layout's look depends only on its own meta, the site CSS and the theme. Duplicate, export and import carry the whole design.
- Changing every layout's colours at once now means editing each layout, or using the site-wide custom CSS.
- An old or partial layout, or an import file from before this change, shows the theme until the merchant sets values. It never fails to load.
- Exporting a layout to another store leaves the site CSS behind.
