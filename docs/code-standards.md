# Code Standards

Conventions for YayCheckout. `phpcs.xml` enforces formatting; this file covers what a linter cannot check — layering, naming, and where a new class belongs.

Run `composer lint` (and `composer lint:fix` for the auto-fixable subset).

## Layering

```
includes/
  PostTypes/    register_post_type only — caps, template, labels. No logic.
  Models/       dumb entities. Typed props, from_post()/to_array(). Zero WP calls.
  Repositories/ ALL get_post/update_post_meta/get_option. Returns Models. Owns invariants.
  Services/     only when an op spans repos or is pure computation.
  Controllers/  REST routing, arg validation, permissions. Thin.
  Renderer/     frontend WooCommerce hooks. Read-only.
  Blocks/       PHP block registration + render_callbacks.
  Helpers/      stateless statics + option wrappers.
  Support/      plugin-lifecycle primitives (Plan). No WP calls.
  Pro/      everything Pro-only. Absent from the Free build.
  Engine/       boot, wiring, asset registration.
  Utils/        traits.
```

**A WP core function call outside `Repositories/`, `Helpers/`, `Engine/`, `Renderer/`, `Blocks/` is a bug.**

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
update_post_meta( $id, Layout::META_PUBLISHED, $markup );

// No
update_post_meta( $id, '_yaycheckout_published', $markup );
```

Layouts keep a working draft (`post_content`) and a published snapshot (`_yaycheckout_published`) at the same time. A typo'd key silently reads or writes the wrong copy — the renderer serves a draft to a customer, or a publish writes nowhere. No error, no test failure, just wrong output. The constant is what makes that a fatal error instead.

Verify with:

```bash
grep -rn "_yaycheckout_" includes/ --include=*.php
```

Matches should appear only in `Models/`.

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

PHP `to_array()` emits camelCase so the TS side stays idiomatic. The zod schema is the only place the PHP shape is asserted in JS — keep it exact.

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

## The pro boundary

`includes/Pro/` is deleted wholesale to build the Free artifact, so it must be genuinely detachable:

> **No file outside `includes/Pro/` may name a `YayCheckout\Pro\*` class.**

The single permitted reference is the guarded bootstrap in `YayCheckout::boot_pro()`. Pro features attach to core through hooks and filters — `DerivedCacheBuilder::FILTER_CONTRIBUTORS` is the model — so core never calls into pro code.

`composer lint` runs `scripts/check-pro-boundary.sh`, which fails on a second reference. This is not a style preference: without it, one `use` statement makes the Free build impossible, and nothing else would reveal that until a customer installed the artifact.

Plan is derived, not declared. `Plan::is_pro()` reports whether the provider actually booted, so it cannot claim Pro while the files are missing. It describes **plan**, not license — an expired Pro license is still the Pro plan.

## Formatting

Enforced by `phpcs.xml`; notable deviations from WPCS defaults, all matching existing code:

- 4-space indent, not tabs
- aligned `=>` in multi-line arrays
- no Yoda conditions
- short array syntax
- docblocks where they explain something non-obvious, not on every method

`scoper.inc.php` is excluded — it is php-scoper build config, not WordPress runtime code.
