# Code Standards

Conventions for YayCheckout. `phpcs.xml` enforces formatting; this file covers what a linter cannot check — layering, naming, and where a new class belongs.

Run `composer lint` (and `composer lint:fix` for the auto-fixable subset).

## Layering

```
includes/
  Enums/        constants classes: status enum, condition types for assignment rules. Zero WP calls.
  PostTypes/    register_post_type only — caps, template, labels. No logic.
  Models/       dumb entities. Typed props, from_post()/to_array(). Zero WP calls.
  Repositories/ ALL get_post/update_post_meta/get_option. Returns Models. Owns invariants.
  Services/     only when an op spans repos or is pure computation.
  Controllers/  REST routing, arg validation, permissions. Thin.
  Renderer/     frontend WooCommerce hooks. Read-only.
  Blocks/       PHP block registration + render_callbacks.
  Helpers/      stateless statics: Logger, localize payload, detection. No option storage — that is a Repository.
  Support/      plugin-lifecycle primitives (Plan). No WP calls.
  Pro/          everything Pro-only. Absent from the Free build.
  Engine/       boot, wiring, asset registration.
  Utils/        traits.
  Fallback.php  duplicate-guard admin notice. Standalone file wired in bootstrap.
  I18n.php      textdomain loader. Standalone file wired in bootstrap.
```

**A WP core function call outside `Repositories/`, `Helpers/`, `Engine/`, `Renderer/`, `Blocks/` is a bug.**

Translation and escaping functions — `__()`, `_n()`, `esc_html()` and friends — are exempt. They format strings rather than reach for state, and banning them everywhere would push user-facing copy out of the layer that owns the rule it describes. `Plan::layout_limit_message()` is the example: the limit and its wording belong together.

Models and Services stay pure so they can be reasoned about (and later tested) without WordPress loaded. A Model receives already-fetched data; it never goes and gets it. A Service is a function of its inputs.

Check with:

```bash
grep -rnE "get_post|update_post_meta|get_option|wp_insert_post" includes/Models includes/Services
```

## Naming

- PHP files are PascalCase matching the class name. The `spl_autoload_register` in `yaycheckout.php` maps `YayCheckout\Foo\Bar` → `includes/Foo/Bar.php`, so a mismatch breaks autoloading silently.
- Suffix by role: `*PostType`, `*Repository`, `*RestController`, `*Helper`. No bare nouns.
- Post meta keys: `_yaycheckout_*` (leading underscore = hidden from the custom-fields UI).
- Options: `yaycheckout_*` (no leading underscore).
- Hooks and filters: `yaycheckout_*`.
- REST routes: kebab-case, plural resource — `/layouts`, `/layouts/{id}/publish`.

## Storage keys are constants

**Every meta key and option name is a class constant on its Model. No string literals at call sites.**

```php
// Yes
get_option( Layout::OPTION_ACTIVE, [] );

// No
get_option( 'yaycheckout_active_layout', [] );
```

A typo'd key silently reads nothing — the store forgets its active layout with no error and no failing test. The constant is what makes that a fatal error instead.

Verify with:

```bash
grep -rn "_yaycheckout_" includes/ --include=*.php
```

Matches should appear only in `Models/`.

The one exception is `uninstall.php`: WordPress runs it without the plugin's autoloader, so it repeats `yaycheckout_settings` and `yay_checkout_layout` as literals, each with a comment naming the constant it mirrors. Change the constant, change the literal.

### Settings are declared once

`Models\Settings::schema()` is the only description of a setting — section, key, type, default, allowed values. `Settings::from_option()`, `Settings::sanitize()` and defaulting all walk it, so a key cannot be accepted without a default or defaulted without validation, and a stale or hand-edited stored value reads back as its default. Adding a setting is one schema entry, the matching wire/app zod entries in `settings.schema.ts`, and a form field. No separate defaults array, no per-key sanitize function.

## Single source of truth

**Every rule has one owner; everything else calls it or mirrors it from the table below.**

Two places that decide the same thing leave the reader unable to tell which one wins, and they drift: one gets fixed, the other keeps shipping the old rule. The two sections above are this rule applied to storage keys and settings.

| Rule | Owner | Allowed mirrors |
|---|---|---|
| Capability for layouts and every REST route | `LayoutPostType::CAPABILITY` | — |
| Meta keys, option names, post type | Constants on the owning class (`Layout::OPTION_ACTIVE`, `Layout::POST_TYPE`, …) | `uninstall.php` literals (no autoloader); the blocks bundle reads `postType` from `window.yayCheckoutBlocks` (`BlockRegistry`) |
| Settings keys, types, defaults | `Models\Settings::schema()` | `apps/admin/src/lib/schema/settings.schema.ts` |
| Design tokens, presets, radius/base-size bounds | `Models\Design::schema()`, `Design::presets()` | `RADIUS_RANGE`, `BASE_SIZE_RANGE` in `apps/admin/src/lib/schema/design.schema.ts` |
| Input size → px height, fallback behind a picked font | `Renderer\DesignStyles::INPUT_HEIGHTS`, `FONT_FALLBACK` | `PreviewPane.tsx` (the admin Preview draws what the storefront prints) |
| Token diff count, active preset, which groups are on | `apps/admin/src/lib/helpers/design.helper.ts` (`countTokenDiffs`, `activePresetIdFor`, `isGroupOn`) | — |
| Extra-field Keys, accepted options | `Services\LayoutMarkupReader` (`EXTRA_FIELD_KEY_PATTERN`, `extra_fields()`, `options()`) | `packages/blocks/src/shared/extra-field-keys.ts` |
| Import file review (format, one layout, Checkout block) | `Pro\Services\LayoutImportReview::from_envelope()` — the editor asks `/layouts/import/review` | `parseExportFile()` in `apps/admin/src/lib/api/tools.api.ts` (early error before upload) |
| Block count of a layout | `Layout::block_count()` | `reviewExportFile()` in `apps/admin/src/lib/api/tools.api.ts` |
| Assignment condition types, operators, value kind, single-choice values (devices, login statuses) | `Enums\ConditionType::definitions()` | `apps/admin/src/pages/layouts/assignment/condition-types.ts` (the drawer needs them to render pickers and to hold a save until every condition has a value) |
| Assignment matching (which active layout serves the shopper) | `Services\LayoutAssignment::pick()`, called by `LayoutRepository::active()` | — |
| Assignment rules readable on import | `Pro\Services\LayoutImportReview::rules_from_file()` (via `Layout::sanitize_rules()`) | `reviewExportFile()` in `apps/admin/src/lib/api/tools.api.ts` (labels a file whose rules are not groups of conditions "Unreadable" before upload) |
| Rules import status (`imported` / `dropped` / `none`) | `Pro\Services\LayoutImportReview::RULES_*` | `importResponseWireSchema` in `apps/admin/src/lib/schema/tools.schema.ts` (the wire enum) |
| Upgrade link | `YAYCHECKOUT_UPGRADE_URL` (`yaycheckout.php`) | — (reaches the SPA via `yayCheckoutMeta`, the blocks via `window.yayCheckoutBlocks`) |
| Export file download (name, save, `.json` check) | `packages/blocks/src/shared/export-file.ts` | — (the SPA imports it via `@blocks-shared`) |
| Mutation keys another component watches (`useIsMutating`) | `*_MUTATION_KEY` constants in `apps/admin/src/lib/queries/*.queries.ts` (e.g. `IMPORT_LAYOUT_MUTATION_KEY`) | — |
| Which templates a plan may create from (unknown, locked) | `Controllers\LayoutTemplateCatalogue::assert_available()`, called by `create_draft` and `/setup` | `locked` on each `GET /layouts/templates` row (the picker renders it, never re-derives it) |
| Template by slug (name, category, locked) | `useTemplate()` in `apps/admin/src/lib/queries/template.queries.ts` | — |
| Layouts `?edit=ID` deep link into the embedded editor | `apps/admin/src/lib/helpers/layout.helper.ts` (`editLayoutPath`, `editLayoutIdFrom`, `withEditLayout`) | — |
| Publish rules | `LayoutMarkupReader::publish_error()` — called, never re-checked | `packages/blocks/src/checkout/publish-guard.ts` |
| Required/optional field rules | `Services\RequiredFieldOverlay` | `assets/js/checkout-field-requirements.js` (payload from `RequiredIndicator::script_payload()`) |
| Block list, inserter order | `Blocks\BlockRegistry::ORDER` | — |
| REST wire shapes | PHP `to_array()` | `*WireSchema` in `apps/admin/src/lib/schema/` |
| Cross-cutting rationale | `docs/system-architecture.md` | a code comment of one or two lines plus a pointer |

A mirror exists only where the language boundary forces one; it names its owner in a comment and must agree with it. Adding a mirror means adding it to this table with the reason. `/yay:code-corrector` reads this table to flag copies in a diff.

## REST controllers

Extend `BaseRestController`. Register routes in the constructor, use `SingletonTrait`, and instantiate from `Engine\RestAPI::checkout_endpoints()` on `rest_api_init`.

Wrap every callback:

| Wrapper | Use for |
|---|---|
| `exec_read` | reads |
| `exec_write_wp` | **WordPress API writes** — posts, meta, options |
| `exec_write` | **custom table writes** — wraps in a SQL transaction |

`exec_write` opens a MySQL transaction. WordPress writes go through the object cache, so a `ROLLBACK` reverts MySQL but leaves a persistent cache (Redis/Memcached) holding rolled-back data. That bug is invisible on a host without a persistent object cache and intermittent on one with it. Use `exec_write_wp` for WordPress writes and order the writes so a partial failure leaves the previous state intact.

Validate route params at the route (`args` with `validate_callback`), not in the handler. When a route takes a post ID, **check the post type in the handler** — otherwise the endpoint mutates arbitrary posts.

Return errors through the `error_*` helpers so the response shape stays consistent. Map expected failures to 4xx; let only genuine faults reach the 500 in the wrapper.

## Frontend (apps/admin)

### Data layer

Three files per feature, matching the existing `settings.*` set:

- `src/lib/schema/{feature}.schema.ts` — zod schema + inferred type
- `src/lib/api/{feature}.api.ts` — ky calls, one function per endpoint
- `src/lib/queries/{feature}.queries.ts` — `queryOptions` object + hooks

**Wire vs app naming — same rule as every YayCommerce plugin.** Everything on the wire is `snake_case`: query params follow WP REST core spelling (`page`, `search`, `status`), request bodies (`template_slug`) and every payload field PHP `to_array()` / `to_summary_array()` emits (`is_active`, `published_at`, `total_pages`). Everything TS-side is camelCase (`Layout`, `LayoutListResponse`, form values, hook params). The two meet in exactly one place: `src/lib/api/{feature}.api.ts`, which parses responses against the strict `*WireSchema` in `lib/schema/` and converts to the app types (`summaryFromWire`, `layoutFromWire`), and converts request bodies on the way out. Pages and hooks never see a snake_case key; PHP never learns how the SPA spells one. The `wp_localize_script` bootstrap (`LocalizeHelper`, `window.yayCheckoutMeta`) is the one exception: its top-level keys follow the YayCommerce house camelCase shape (`wpMeta.siteUrl`).

The zod wire schema is the only place the PHP shape is asserted in JS — keep it exact and `.strict()`, so a renamed PHP key fails loudly instead of parsing to `undefined`.

**Timestamps on the wire are ISO 8601 with an explicit UTC offset** (`2026-09-17T09:25:41+00:00`), never the zone-less GMT MySQL string WordPress stores. PHP converts at the serialization boundary through `Helpers\DateHelper::to_iso8601()` (storage keeps core's MySQL shape); the SPA formats only through `lib/helpers/date.helper.ts` (`formatDateTime`), which applies the site's Settings → General date/time format in the viewer's browser timezone — a deliberate departure from core's site-timezone lists, so each admin reads a timestamp against their own clock. Never render a raw wire timestamp or reach for `new Date(...)`/`Intl.DateTimeFormat` in a page.

### Where components live

A component lives at the narrowest scope that covers its consumers.

```
src/
  pages/{page}/
    {Page}Page.tsx         the route component
    components/            used by this page only
    hooks/                 used by this page only
  components/
    ui/                    shadcn primitives — generated, do not hand-edit
    shared/                our components used by two or more pages
    layout/                app chrome: Header, Footer, HeaderMenu
    pro/                   ProGate and the upsell surfaces
    setup-wizard/          the first-run popup mounted in AppLayout; route-less, so not under pages/
    icons/
```

**Start page-private.** A component used by one page belongs in that page's
`components/` folder, however reusable it looks. When a second page needs it,
move it to `src/components/shared/` — that move is the point at which you
actually know what its shared API should be, and guessing earlier is usually
wrong.

The rule exists so `src/components/` keeps meaning "shared". The failure mode
is visible in a sibling plugin, where 18 of 22 folders under `components/`
serve exactly one route: nothing there can be deleted or changed with
confidence, because the tree no longer says who depends on what.

`ui/` is shadcn's, regenerated by its CLI — our own components never go inside
it, which is why the former `ui/custom/` moved to `shared/`.

### File naming

- `components/ui/*` stays kebab-case — that is what shadcn generates, and
  renaming would break regeneration.
- Everything we author is PascalCase matching the component it exports:
  `ProGate.tsx`, `Header.tsx`, `BuilderPage.tsx`.
- Non-component modules stay kebab-case: `layout.api.ts`, `use-settings-form.ts`.

### FormItem collapses inline controls

shadcn's `FormItem` renders a vertical `Field` whose `*:w-auto` overrides the width of every direct child, so a `Switch` inside it loses its track and shows as a bare thumb. For a control that has no label or message of its own (a toggle or select in a settings row), render it straight from `FormField`'s render prop and skip `FormItem`/`FormControl` — `pages/settings/components/GeneralTab.tsx` is the pattern, and so is YayWholesale's settings page.

## Logging

`Helpers\Logger` writes to WooCommerce's log under the `yaycheckout` source, visible at WooCommerce → Status → Logs, which is where support already looks.

Log **faults** — something went wrong that nobody asked for. `Logger::exception()` records the message, the route, the exception class, the file and line, and the trace, which is what makes a bug report actionable instead of "it broke". `BaseRestController` does this for every 500 it returns.

Do **not** log expected outcomes. A merchant hitting a plan limit or posting unusable markup is a 4xx, not a fault; logging those buries real failures in noise.

`Logger::debug()` is a no-op unless `WP_DEBUG` is on, so debug calls can be left in place without filling a production log directory.

### Logging in the SPA

`lib/logger.ts` wraps the console with a `[YayCheckout]` prefix so admin-page output can be filtered from everything else WordPress prints.

`error` and `warn` survive into production on purpose: when a merchant reports a problem, asking them to screenshot the console is often the shortest path to a cause. `info` and `debug` are guarded on `import.meta.env.DEV`, and terser removes those calls entirely from a production build — verified by grepping the bundle, where `console.info` and `console.debug` do not appear at all. The message strings still ship, since the guard lives inside the function rather than at the call site; that is cosmetic, but do not put anything sensitive in a debug message.

Failures are logged where they are handled, not at every layer: `query-client.ts` for query and mutation errors, and the 500 route for render errors. A query error is logged even when its toast is suppressed, because a silent background refetch failure is the case with no other trace.

This is a browser aid, not a record. The server-side counterpart is `Helpers\Logger`.

## The Pro boundary

`includes/Pro/` is deleted wholesale to build the Free artifact, so it must be genuinely detachable:

> **No file outside `includes/Pro/` may name a `YayCheckout\Pro\*` class.**

The single permitted reference is the guarded bootstrap in `YayCheckout::boot_pro()`. Pro features attach to core through hooks and filters, so core never calls into Pro code.

`composer lint` runs `scripts/check-pro-boundary.sh`, which fails on a second reference. This is not a style preference: without it, one `use` statement makes the Free build impossible, and nothing else would reveal that until a customer installed the artifact.

Plan is derived, not declared. `Plan::is_pro()` reports whether the provider actually booted, so it cannot claim Pro while the files are missing. It describes **plan**, not license — an expired Pro license is still the Pro plan.

## Readable code

Body-level rules for new and touched code; the caps are soft and existing code is not refactored to meet them.

- **Return early; nest at most two levels.** Each level is one more condition the reader holds in their head to know when a line runs.
- **One job per function.** If its purpose line needs "and", split it; past ~40 lines, check whether it has. A function doing two jobs can't be reused for either.
- **Names carry kind and unit.** Booleans read `is_`/`has_`/`can_`, quantities name their unit (`_seconds`, `_bytes`), no abbreviations beyond `id` and `url`. A bare `timeout` or `flag` sends the reader to the call site to find out.
- **Boring over clever.** No nested ternaries, at most two chained `map`/`filter`/`reduce`, a string function over a regex when it is enough. Clever code is read many more times than it is written.

## Comments

Two kinds of comment, two rules.

**Docblocks** (class, public/protected method, constant, hook) start with one line saying *what this does for its caller* — the contract an IDE shows before the body is read. Keep it unless the name and signature already say it (`get_id()`: cut; `create()` "a layout draft from the New layout modal": keep). After that line, only the *why* and the `@param` / `@return` / `@throws` a caller relies on. A docblock never opens with a caveat: if there is a why, the purpose line comes first.

**Inline comments** (inside a body) default to none. **Code shows *how*; comment only to carry *why* — a non-obvious constraint, deliberate deviation, gotcha, or workaround.** Keep the one fact a reader needs at that line, in the fewest words; two lines is the usual ceiling, longer only for a WordPress behaviour the code works around. Never narrate the code ("loop over layouts"), narrate the change ("fixed X", "as requested"), or mark block ends.

"Restates the name" means the line adds nothing beyond the identifier — no caller context, trigger, unit, or side effect. A summary that adds one of those is a contract, not a restatement.

**When a comment does exist, it states only what is true of the code directly beneath it — the invariant it protects and what breaks without it.** Never what other code does, what the code used to do, or what it will do. Cross-cutting rationale lives in `docs/system-architecture.md`; the comment points at it (`See docs/system-architecture.md, <section>`).

The rule exists because comments that describe something outside their own block rot silently: the other module changes, the comment does not, and the next reader is sent down the wrong path. A 2026-09 audit found 53 such comments; none of the comments about a block's own invariant were wrong.

A comment survives review when all four hold:

1. **Owns its claim** — describes only the code beneath it. Not the SPA, not cron, not a controller, not a modal, not a commit.
2. **States an invariant or a why** — what this code guarantees or guards, and what breaks without it.
3. **Not derivable** — reading the code would not tell you this. Library semantics (TanStack, react-hook-form, WP core) are not ours to document unless the code works around them.
4. **Points, doesn't repeat** — if `system-architecture.md` has the full rationale, the comment is one or two lines plus a pointer.

Cut: other-module behaviour · narrated history ("this used to…") · restated code or library defaults · rationale duplicated from `docs/` · future tense and promises · performance folklore · design or wireframe IDs.

Keep: guards whose removal is a silent bug · ordering constraints (one line + pointer) · WordPress behaviours the code works around · every `@param` / `@return` / `@throws` a caller depends on · real `TODO:` markers.

The review question is: *does this comment describe something outside this function?* If yes, cut it or move it to the doc.

`TODO:` is for planned work only — one line, what and why not now. Not for wishes.

With no test suite, invariant comments are the spec. Trim them to the invariant; delete only when the code already says it.

## Formatting

Enforced by `phpcs.xml`; notable deviations from WPCS defaults, all matching existing code:

- 4-space indent, not tabs
- aligned `=>` in multi-line arrays
- no Yoda conditions
- short array syntax

`scoper.inc.php` is excluded — it is php-scoper build config, not WordPress runtime code.
