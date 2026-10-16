# Conventions

**Purpose:** the team's rules that the code can't show by itself: storage keys, settings and design schemas, TypeScript copies of PHP, wire casing and dates, file names, logging and comments. Each rule says why, and where the code follows it.

**Update when:** the team adopts, changes or drops one of these rules, or the code that follows a rule moves. Layering and who writes what are in [architecture.md](architecture.md), not here.

## Storage keys are constants

- **Rule:** every meta key, option name, transient and the post type is a class constant on the class that owns it (`Layout::POST_TYPE`, `Layout::OPTION_ACTIVE`, `CheckoutExtraFields::META_SNAPSHOT`, `CompatibilityCatalogue::TRANSIENT`). Call sites use the constant, never the string.
- **Why:** a mistyped key reads nothing and raises no error, so the store silently forgets its active layout. A mistyped constant is a fatal error.
- **In code:** `includes/Models/`, and the owning class elsewhere. `uninstall.php` is the one exception: it runs without the autoloader, so it repeats `yaycheckout_settings`, `general.delete_data_on_uninstall` and `yay_checkout_layout` as literals, and its docblock names the constants they copy. A per-user editor preference in `core/preferences` (the editor tour's `yaycheckout` / `editorTourDone` and `featureToursSeen`) is a constant in the module that reads it; core stores it, so there is nothing to uninstall.

## Settings and design are declared once

- **Rule:** a setting or design token is one entry in `Models\Settings::schema()` or `Models\LayoutDesign::schema()`: its section, key, type, default and allowed values. There is no separate defaults array and no per-key sanitizer. Adding a setting is that entry, the matching zod entry, and its form field. Adding a design token is that entry, its line in `DesignStyles::vars()` and the copy in `packages/blocks/src/shared/design-css.ts`; the editor sidebar reads the schema from PHP.
- **Why:** reading, defaulting and sanitizing all walk the schema, so a key can't be accepted without a default or defaulted without validation, and a stale stored value reads back as its default.
- **In code:** `from_option()` and `sanitize()` in `includes/Models/Settings.php`, and `from_meta()` and `rest_schema()` in `includes/Models/LayoutDesign.php`; the zod side in `apps/admin/src/lib/schema/settings.schema.ts`.

## TypeScript copies of PHP name their owner

- **Rule:** a TypeScript copy of a PHP value or rule exists only where the language boundary forces one, and its comment names the PHP owner, as in `/** Mirrors DesignStyles::DEFAULT_BASE_SIZE in PHP: … */`. Change both in the same commit. A rule the server can answer is asked, not copied: the editor's import review asks `/layouts/import/review`, and the template picker reads `locked` from each row of the catalogue `LocalizeHelper` prints into `window.yayCheckoutAdmin.templates`, computed for the installed plan; creating a layout checks it again.
- **Why:** two places that decide the same thing drift, and only the named owner says which one is right. The owner is always PHP, because the server is what a request can't bypass.
- **In code:** the `Mirrors …` comments in `apps/admin/src/lib/schema/` and `packages/blocks/src/` (`git grep -n 'Mirrors' -- apps/admin/src packages/blocks/src`), among them `packages/blocks/src/shared/extra-field-keys.ts` (`LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN`), `packages/blocks/src/checkout/publish-guard.ts` (`LayoutMarkupReader::publish_error()`), `packages/blocks/src/place-order/edit.tsx` (the class and default label `place-order/render.php` prints on the button), `packages/blocks/src/shared/design-css.ts` (`DesignStyles::css()` without custom CSS, and `LayoutDesign::is_gradient()`), `packages/blocks/src/checkout/layout-design.tsx` (`LayoutDesign::sanitize_custom_css()`), `packages/blocks/src/shared/button-arrow.ts` (`ButtonArrow::glyph()`), `packages/blocks/src/shared/section-heading.tsx` (`SectionTextStyles::heading_tag()` and `wrapper_class()`'s parts and classes), `packages/blocks/src/shared/summary-part-styles.tsx` (`SummaryPartStyles::wrapper_class()`) and `packages/blocks/src/shared/text-formats.ts` (the tags `LayoutMarkupReader::RICH_TEXT_TAGS` keeps). One pair runs the other way: `packages/blocks/src/shared/address-fields.ts` owns the address-field list, and `packages/blocks/src/shared/address-template.ts` and `templates/layouts/classic-two-column/markup.php` both seed a new address section with the same structure built from it. `assets/js/checkout-field-requirements.js` reads its flags from `RequiredIndicator::script_payload()`, but repeats `RequiredIndicator::span()`'s fallback marks and markup.

## Wire casing

- **Rule:** everything on the wire is snake_case: query parameters, request bodies and every field PHP's `to_array()` emits. Everything in the SPA's TypeScript is camelCase; the blocks package calls only a few routes through `apiFetch` and reads their fields as sent. They meet only in `apps/admin/src/lib/api/<resource>.api.ts`, which parses each response with a strict `*WireSchema` from `apps/admin/src/lib/schema/` and converts it (`summaryFromWire`, `layoutFromWire`), and converts request bodies on the way out.
- **Why:** pages and hooks never see a snake_case key, and PHP never learns how the SPA spells one. A strict wire schema makes a renamed PHP key fail loudly instead of parsing to `undefined`.
- **In code:** `apps/admin/src/lib/api/`, `apps/admin/src/lib/schema/`. Data printed for scripts keeps camelCase keys (`window.yayCheckoutMeta` from `Helpers\LocalizeHelper`, `window.yayCheckoutBlocks`, `window.yayCheckoutRequired`), except the `to_array()` payloads inside `window.yayCheckoutAdmin`, which stay snake_case and go through their wire schemas like any response.

## Dates on the wire

- **Rule:** a timestamp on the wire is ISO 8601 with an explicit UTC offset (`2026-09-17T09:25:41+00:00`), never WordPress's zone-less GMT string. PHP converts at serialization through `Helpers\DateHelper::to_iso8601()`; storage keeps core's MySQL format. The SPA formats only through `apps/admin/src/lib/helpers/date.helper.ts`, in the viewer's browser time zone with the site's date and time formats; a page never calls `new Date()` or `Intl.DateTimeFormat` itself.
- **Why:** zone-less GMT strings were rendered verbatim, so the Layouts list showed UTC to every admin. [The decision record](decisions/2026-10-05-iso-8601-utc-wire-timestamps.md) says why the viewer's zone, not the site's.
- **In code:** `Models\Layout::to_array()`, `LayoutRestController` (preview-link expiry), `formatDateTime()` and `formatRelative()`.

## File names and where components live

- **Rule:**
  - PHP class files are PascalCase and match the class (`includes/Models/Layout.php` for `YayCheckout\Models\Layout`). A role suffix says what the class is: `*PostType`, `*Repository`, `*RestController`, `*Helper`.
  - In the SPA, a component file is PascalCase and matches the component it exports (`ProGate.tsx`); a hook's file takes the hook's name (`useRouteLeaveGuard.ts`); other modules are kebab-case (`layout.api.ts`); `apps/admin/src/components/ui/` stays kebab-case, shadcn's naming, though no `components.json` regenerates it.
  - A component lives at the narrowest scope that covers its users: `pages/<page>/components/` for one page, `apps/admin/src/components/shared/` once a second page needs it. Our components never go in `apps/admin/src/components/ui/`.
- **Why:** the autoloader maps names to paths, so a mismatch fails silently. `components/` stays a list of what is genuinely shared, so a component there can be changed knowing who depends on it.
- **In code:** the loader in `yaycheckout.php`; `apps/admin/src/pages/`, `apps/admin/src/components/`.

## Admin headings, labels and copy

- **Rule:**
  - A page's title and description go through `PageLayout`. Its h1 is the only `text-2xl` on the page; a section is a `Card` with `CardTitle` and `CardDescription`.
  - A settings section is a `SettingsSection`. A form-bound setting is a `SettingRow` inside `FormField`'s render prop, with the focusable element wrapped in `FormControl`. A switch row is horizontal (label on the left, switch on the right); a select, input or textarea row is vertical (label above). A row that triggers an action instead of holding a value is a `SettingActionRow`.
  - UI copy is sentence case: "Save changes", not "Save Changes". Product and plugin names keep their capitals. A section or setting description is one short sentence ending in a period.
  - A string translated outside a component runs `__()` inside a function, not at module load.
- **Why:** the SPA follows YayLoyalty's admin, so merchants who run both see one pattern. `FormLabel` and `FormControl` give each control an accessible name and show its zod error. `__()` at import can run before the locale data loads.
- **In code:** `apps/admin/src/pages/PageLayout.tsx`, `apps/admin/src/pages/settings/components/SettingsSection.tsx`, `apps/admin/src/pages/settings/components/SettingRow.tsx`.

## Logging

- **Rule:** log faults, not expected outcomes. PHP logs through `Helpers\Logger` (WooCommerce's log, source `yaycheckout`); `Logger::exception()` records the route, class, file, line and trace, and `BaseRestController` calls it for every 500. A 4xx the merchant caused, such as the layout limit or unpublishable markup, is not logged. The SPA logs through `apps/admin/src/lib/logger.ts`, where the failure is handled (`apps/admin/src/lib/query-client.ts`, `apps/admin/src/pages/500.tsx`), not at every layer.
- **Why:** support reads WooCommerce → Status → Logs, and expected outcomes there bury the failures that matter. In the browser, `error` and `warn` stay in production so a merchant's console screenshot shows the cause; `info` and `debug` run only in development, so nothing sensitive goes in their messages.
- **In code:** `includes/Helpers/Logger.php`, `apps/admin/src/lib/logger.ts`.

## Comments

- **Rule:**
  - A docblock opens with one line on what the code does for its caller, then only the why and the `@param`, `@return` and `@throws` a caller relies on.
  - An inline comment is the exception: it carries a why the code can't show (a constraint, a deliberate deviation, a WordPress behaviour worked around), never what the code does or what it used to do.
  - A comment states only what is true of the code beneath it. It never cites a doc, a doc section, a plan or a feature ID; if the reason is cross-cutting, the comment states the reason itself.
  - A to-do names its topic: `TODO(<topic>): what, and why not now`.
- **Why:** a comment about other code rots when that code changes, and a doc pointer breaks when the doc moves. Docs cite code; code never cites docs.
- **In code:** `./scripts/check-harness.sh` fails a `§` or a feature ID in code; review catches the rest.
