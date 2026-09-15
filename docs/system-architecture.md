# System Architecture

How YayCheckout is put together, and why. `code-standards.md` covers conventions; this covers the decisions those conventions exist to serve.

## Shape

```
WordPress admin                          Storefront
  │                                        │
  ├─ React SPA (apps/admin)                └─ published snapshot → blocks render
  │    ├─ core wp/v2  ──► draft (post_content)
  │    └─ yaycheckout/v1 ──► publish, list
  │
  └─ Block editor (packages/blocks) ──► draft
```

Three build outputs, all under `assets/dist/`: the admin SPA (Vite), the editor blocks (`@wordpress/scripts`), and PHP that is shipped as-is.

## A layout carries two copies of its markup

The single most important thing to understand.

| Where | What | Read by |
|---|---|---|
| `post_content` | working draft | editor, nonce-signed previews |
| `_yaycheckout_published` | published snapshot | the renderer, i.e. customers |

WordPress core cannot hold two versions of one post, so publishing copies draft → snapshot. Nothing else writes the snapshot, and **a customer request never reads the draft**.

This is why every storage key is a constant on `Models\Layout`: a mistyped key silently reads or writes the wrong copy — the renderer serving an unpublished draft, or a publish landing nowhere — with no error and no failing test.

### Publishing is an ordered sequence

`LayoutRepository::publish()`:

1. Read the draft.
2. `DerivedCacheBuilder::build()` — **pure, in memory**. Unusable markup throws here, before anything is written, so the previously published layout keeps serving.
3. Save a WP revision, capturing its ID.
4. Write `_yaycheckout_derived`.
5. Write `_yaycheckout_published` and `_yaycheckout_published_revision`.
6. Write `_yaycheckout_published_at` **last, as the commit marker**.

The order means a failure part-way through leaves the old snapshot intact rather than a half-updated one. WordPress offers no atomicity across meta writes, so ordering is the only tool available.

Two subtleties worth keeping:

- `wp_save_post_revision()` returns `null` when content is unchanged, which is the normal case when republishing an untouched draft. The previous revision ID must be kept, not cleared — orders point at it.
- Layout revisions are exempt from `WP_POST_REVISIONS` pruning (`wp_revisions_to_keep` returns `-1` for this post type). Orders record the layout revision they were placed on, so pruning would erase that history for exactly the old orders where support needs it.

## Storage

No custom tables in 1.x.

| Key | Kind | Written by |
|---|---|---|
| `post_content` | post field | core REST (draft) |
| `_yaycheckout_published` | meta | publish |
| `_yaycheckout_derived` | meta | publish — fields, extra_fields, slots, order_modes |
| `_yaycheckout_published_at` | meta | publish, last |
| `_yaycheckout_published_revision` | meta | publish |
| `yaycheckout_active_layout` | option | `set_active()` — one option, so "exactly one active" holds by construction |

The four derived caches live in one `_yaycheckout_derived` array rather than four keys. Fewer writes means a smaller window in which new derived data sits against an old snapshot, and one `get_post_meta` on the customer path instead of four.

## Two REST surfaces, one auth rule

The block editor saves drafts through **core's** `wp/v2/yay_checkout_layout` — autosave and revisions come free. Operations core cannot express are ours, under `yaycheckout/v1`.

Both check `manage_woocommerce`. Core does so because the post type maps every capability to it at registration; our controllers do so in `permission_callback`. One rule, declared in one place, no `map_meta_cap` filter.

**A published layout's `post_status` is `private`, never `publish`.** `WP_REST_Posts_Controller::check_read_permission()` returns true for any post whose status is `publish` without consulting the post type's capabilities — so publishing would have made layout markup readable through `wp/v2` by anonymous visitors, bypassing the capability map entirely. `private` routes the read through `read_post`.

Full map, owned by `LayoutPostStatus`:

| `LayoutStatus` | `post_status` |
|---|---|
| `draft` | `draft` |
| `pending` | `pending` |
| `scheduled` | `future` |
| `published` | `private` |
| `archived` | `trash` |

## Blocks are dynamic

`save()` returns `null`; all markup comes from `render.php`. Published layouts are stored as markup and re-rendered on every request, so a renamed attribute is absorbed by a default in the render callback. Static save output would instead need a block deprecation for every attribute rename, and existing merchant content would show as invalid.

`block.json` has one home: `packages/blocks/src/{block}/`. `@wordpress/scripts` copies it and `render.php` into `assets/dist/blocks/{block}/`, and `BlockRegistry` registers that built directory — so script dependencies come from the generated `index.asset.php` rather than being hand-listed in PHP.

Blocks contribute to the derived caches through a filtered map (`DerivedCacheBuilder::FILTER_CONTRIBUTORS`), so Pro can add blocks without changing core.

## Free and Pro

One codebase, two artifacts.

| | Folder | `includes/Pro/` | Layouts |
|---|---|---|---|
| Free | `yaycheckout/` | absent | 3 |
| Pro | `yaycheckout-pro/` | present | unlimited |

The split is **asymmetric on purpose**:

- **PHP is structurally excluded.** The Free zip has no premium code — not disabled code, absent code. The server is the only thing an attacker cannot edit, so enforcement lives here.
- **The SPA blurs.** Pro components ship in the Free bundle behind `ProGate` and are obscured, giving merchants a preview of what upgrading buys. This is marketing, not security, and is only acceptable because the Free build's PHP has no matching endpoint — an unblurred panel calls something that does not exist.

Anything that would render real customer data or call a real endpoint belongs behind `ProOnly`, not `ProGate`.

Plan is **derived, not declared**: `Plan::is_pro()` reports whether `ProServiceProvider` actually booted. A build-time constant could claim Pro while the files are absent, and the first Pro call would fatal.

Plan and license are separate axes. Plan is which build is installed — build-time, immutable. License state is valid/expired/unactivated — runtime, mutable. An expired Pro license is still the Pro plan.

Pro installs to its own directory rather than replacing Free: if Free is ever listed on wordpress.org, an update for slug `yaycheckout` would otherwise overwrite a paid install. Both being active at once is handled — the plugin deactivates a second copy of itself.

### Enforcing the layout limit

Free allows three layouts, Pro is unlimited — but layouts are created through **core's** `wp/v2` endpoints, so there is no handler of ours to hold a count check. `LayoutCapGuard` filters `rest_pre_insert_yay_checkout_layout` instead, which is the only seam at the point of creation.

That filter also fires on **updates**, so it returns early when the prepared post already carries an ID. Without that, editing any layout would start failing the moment a merchant reached the limit — a worse bug than the one being fixed.

`LayoutRepository::duplicate()` enforces the same limit separately, because it is our own code path rather than core's and would otherwise be the obvious way around a create-time cap. Both read `Plan::layout_limit_reached()` so the rule cannot drift between them.

Enforcement is server-side because gating only the admin app would be bypassed by posting to `wp/v2` directly, which any shop manager can do.

### The boundary rule

> No file outside `includes/Pro/` may name a `YayCheckout\Pro\*` class.

One guarded `require` in `YayCheckout::boot_pro()` is the single permitted reference; Pro attaches to core through hooks from there. `scripts/check-pro-boundary.sh` runs as part of `composer lint` and fails on a second reference.

The check is the load-bearing part, not the directory. A sibling plugin has a `Premium/` folder that cannot actually be removed, because its container hard-imports those classes across two thousand lines. Nothing but a mechanical check prevents that drift.

## Writes and the object cache

`BaseRestController` offers two wrappers:

- `exec_write_wp` — WordPress API writes. No SQL transaction.
- `exec_write` — custom tables. Wraps in `START TRANSACTION`.

WordPress writes go through the object cache, so a `ROLLBACK` reverts MySQL while leaving a persistent cache holding the rolled-back data. That bug is invisible on a host without a persistent object cache and intermittent on one with it. Ordering writes safely is the substitute for a transaction.

## Logging

Two loggers, deliberately separate:

- `Helpers\Logger` writes to WooCommerce's log under the `yaycheckout` source. Every 500 returned by `BaseRestController` records the route, exception class, file, line and trace.
- `apps/admin/src/lib/logger.ts` wraps the browser console with a `[YayCheckout]` prefix. Query, mutation and route-render failures go through it.

Both follow the same rule: log **faults**, not expected outcomes. A plan limit or unusable markup is a 4xx the merchant caused, and recording those buries the failures that matter.

The client logger keeps `error` and `warn` in production, since a console screenshot is often the fastest route to a cause; `info` and `debug` are compiled out.

## Class loading

The plugin registers its own `spl_autoload_register` mapping `YayCheckout\Foo\Bar` to `includes/Foo/Bar.php`, separate from Composer's classmap, which covers only the scoped admin shell and the adapter.

It uses `require_once` rather than `require`. A malformed class name — a doubled backslash from another plugin's `class_exists()`, say — maps to the same file through a different string, and a plain `require` then redeclared the class and took down the entire site.

## Where the plan enters the build

| Surface | Mechanism |
|---|---|
| PHP | `includes/Pro/` staged or stripped |
| SPA | `YAYCHECKOUT_PLAN` → `__YAYCHECKOUT_PLAN__` via `vite.config.ts` |
| Artifact | zip and folder name |

`vite.config.ts` resolves the plan so there is one source: the dev server defaults to `pro` (working on the Pro product should show the full UI), builds default to `free` (an unflagged build cannot ship premium UI), and `YAYCHECKOUT_PLAN` overrides both.

`cmd_release` copies a fixed file list, so both a missing file and a leaked one fail silently in a dev tree that always contains everything. It therefore verifies the archive it just produced and refuses to report success on a mismatch.

## Deliberately not built

Pro assignment rules (`resolve_for_request()` returns the active layout and is the seam for them), `_yaycheckout_tokens_override`, the `yay_checkout_pattern` library CPT, import/export, the extra-field registry, `Renderer/` and the WooCommerce checkout hooks, order meta writes, self-test, telemetry, the 1.3 analytics table, and `yay_cart_layout` / `yay_thankyou_layout`.
