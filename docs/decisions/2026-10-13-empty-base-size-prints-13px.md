# A layout owns its whole design, and an empty base size prints 13px

**Purpose:** why each checkout layout carries its whole design in its own meta with no global design underneath, why an empty token is how a layout follows the theme, why the base font size is the one exception and prints 13px, and why custom CSS that reaches every checkout is a setting rather than a design.

**Update when:** never edit it. Supersede it with a new record if a design is shared across layouts again, if tokens get an on/off switch again, if another empty token stops following the theme, if the default base size changes, or if the site-wide custom CSS moves out of the settings.

## Status

Accepted, 2026-10-13, by Trace Bui. It supersedes [2026-10-12-empty-base-size-prints-16px.md](2026-10-12-empty-base-size-prints-16px.md), restating its decisions with the default base size at 13px instead of 16px.

## Context

- Two designs used to combine on every checkout: the global option `yaycheckout_design`, edited on the SPA's Design page, and a layout's meta `_yaycheckout_design`, whose sections were either a full set of tokens or `null` to follow the global one. Each section also had an `enabled` switch, and the global custom CSS printed before the layout's.
- Layouts are created from templates whose `design.json` sets every section, so a layout made from a template, Blank, Reset or the setup wizard already ignored the global tokens; only the global custom CSS still reached it.
- Two places to style a checkout, with a `null` state and an `enabled` switch on top, meant three ways a colour could end up unset, and the editor sidebar had to explain all of them. The plugin had not shipped, and only dev and test sites held a global design.
- Following the theme's font size broke the checkout's proportions. Themes set body text anywhere from 13px to 22px; at 22px every field and button grew with it, and the input size steps, which are in em, all came out large.
- The previous record chose 16px, partly because iOS Safari zooms into a focused field whose text is under 16px. `assets/css/checkout-base.css` already keeps field text at `max(16px, 1em)` on touch screens (`pointer: coarse`), so a smaller base size doesn't bring the zoom back. The merchant asked for a denser 13px checkout by default, matching WordPress admin's own text size.
- Options turned down:
  - Keeping `Models\Design` as a rules-only class beside `LayoutDesign`: two design classes, where only one has a reason to exist.
  - Keeping `enabled` in the meta, always true and hidden: a field that never changes, which the sidebar, import and docs would keep explaining.
  - Keeping `null` sections as "Theme default", with a Theme default / Custom switch per section: the merchant chose to have each section always custom, with an empty value printing nothing.
  - Migrating the global design into each layout and into the new setting: no merchant data depended on it.
  - Dropping site-wide custom CSS with no replacement, making it Pro-only, or putting it in Settings → General: merchants need CSS that reaches every checkout, custom CSS has always been Free, and Advanced is where the other technical options live.
  - Storing 13 only in the template, so new layouts start at 13px while an empty base size keeps printing 16px: the merchant asked for 13px as the default for every layout.

## Decision

- `Models\LayoutDesign` is the only design model, stored as `Layout::META_DESIGN`. It always holds every section of `LayoutDesign::schema()` (colours, typography, inputs) with every token, plus the layout's custom CSS. There is no `enabled` key and no `null` section.
- Every token may be empty: `''` for a colour or an enum, `null` for a size or radius. An empty token prints nothing, so the theme shows through, with one exception: an empty `typography.base_size` prints `DesignStyles::DEFAULT_BASE_SIZE` (13px), so a layout never takes the theme's font size. `0` is a value, not empty.
- Reads are forgiving: `LayoutDesign::from_meta()` reads a missing section, a `null` section, an unknown key or an invalid token as empty. Writes from the editor are strict: `LayoutDesign::rest_schema()` refuses a wrong-typed token with a 400.
- `Renderer\DesignStyles` prints one layout's design, then the site-wide custom CSS, then the layout's custom CSS. Each CSS is checked on print and dropped alone when it fails. The editor canvas prints the tokens and neither CSS.
- The site-wide custom CSS is the setting `advanced.custom_css` (`Models\Settings`, option `yaycheckout_settings`), edited in Settings → Advanced and checked with `LayoutDesign::sanitize_custom_css()`. It is not exported with a layout.
- There is no global design option, model, repository, REST route or admin page. Nothing reads `yaycheckout_design`. Uninstall's `yaycheckout_*` sweep removes a leftover row.

## Consequences

- One model changes when a token is added: its entry in `LayoutDesign::schema()`, its line in `DesignStyles::vars()` and the copy in `packages/blocks/src/shared/design-css.ts`, which must print the same text. `DEFAULT_BASE_SIZE` is copied the same way, and the sidebar's Base size control shows it while the token is empty.
- A layout's look depends only on its own meta, the site CSS and the theme. Duplicate, export and import carry the whole design.
- Changing every layout's colours at once now means editing each layout, or using the site-wide custom CSS.
- An old or partial layout, or an import file from before the global design was removed, shows the theme until the merchant sets values, at 13px. It never fails to load.
- Every layout saved with an empty base size changes from 16px to 13px. A layout saved with a base size of its own keeps it. A merchant who wants a larger size sets it in the layout or in the site-wide custom CSS.
- The checkout's touch-screen rule in `checkout-base.css` is now what keeps iOS from zooming into fields; removing it brings the zoom back.
- Exporting a layout to another store leaves the site CSS behind.
