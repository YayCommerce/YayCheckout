---
area: conventions
updated: 2026-11-12
updated_ref: c7ee247
covers: [includes/, packages/blocks/src/, phpcs.xml]
---
# Conventions

> Update when: a naming rule, a house pattern or a recipe below stops matching the code.

The admin SPA's conventions are in [admin.md](admin.md).

## PHP

- **Files.** Namespace `YayCheckout\<Folder>`, one class per file, filename = class name. Every file starts with `defined( 'ABSPATH' ) || exit;`.
- **PHP 7.4 floor.** Typed properties and `fn` arrows are fine. No `match`, enums, named arguments or constructor promotion: use `switch` and class constants (`includes/Enums/`).
- **Prefixes:**

| Kind | Prefix |
|---|---|
| options | `yaycheckout_*` |
| meta | `_yaycheckout_*` |
| hooks | `yaycheckout_*` |
| CSS classes | `yaycheckout-*` |
| REST namespace | `yaycheckout/v1` |
| post type | `yay_checkout_layout` (note the extra underscore) |
| text domain | `yaycheckout` |

- **Dynamic hook names.** Built from a prefixed constant, they carry `// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- …` (see `includes/Helpers/IntegrationsHelper.php`).
- **Docblocks.** They explain why (invariants, WordPress quirks), not what.
- **Timestamps.** Wire timestamps are ISO 8601 UTC through `Helpers\DateHelper`. Storage stays core's GMT MySQL datetime.
- **Slashing.** Keep `wp_slash()` before writing `post_content`. `wp_insert_post()` unslashes, and block attributes would lose their `\uXXXX` escapes (`LayoutRepository::insert_draft()`).
- **REST handlers.** Extend `BaseRestController` and wrap handlers in `exec_read()` or `exec_write_wp()`.
  - `exec_write_wp()` has no SQL transaction on purpose: a ROLLBACK would leave a persistent object cache holding the rolled-back write. Order writes so that a partial failure leaves the old state live.
  - Use the `error_*` helpers. Never invent a capability: `permission_callback()` checks `manage_woocommerce`.
- **Logging.** `Helpers\Logger` logs faults only, not expected 4xx.

## Blocks (`render.php`)

- **Variables** are prefixed `$yaycheckout_*`. The wrapper is `get_block_wrapper_attributes()`, with a `phpcs:ignore … OutputNotEscaped` (core escapes it).
- **Merchant text.** Read it with `LayoutMarkupReader::plain_text( $attributes, 'key', $default )` or `rich_text()`, and print it with `esc_html()`. `plain_text` avoids double-encoding, which matters because labels reach WooCommerce notices and order meta.
- **Fields** go through `woocommerce_form_field()`, so WooCommerce's `woocommerce/assets/js/frontend/checkout.js` and themes target them. Required state comes from `RequiredFieldOverlay::is_required()`.
- A block that can sit outside Checkout puts `CheckoutScope::classes()` on its wrapper.

## Mirrored constants

Nothing catches drift between these pairs, so change both sides in the same commit:

| PHP | TypeScript |
|---|---|
| `Renderer\BlockVisibility` | `visibility-rules.ts` |
| `Renderer\LiveValues` | `live-value/constants.ts` |
| `Renderer\CheckoutScope` | `checkout-scope.ts` |
| `Renderer\DesignStyles` | `design-css.ts` |
| `LayoutMarkupReader::publish_error()` | `publish-guard.ts` |
| `BlockRegistry::PRO_BLOCKS` | `shared/pro-blocks.ts` (checked by `scripts/check-pro-boundary.sh`) |
| `Models\Settings` | `apps/admin/src/lib/schema/settings.schema.ts` |
| `Enums\ConditionType` (types, operators, value kinds) | `apps/admin/src/pages/layouts/assignment/condition-types.ts` |

## Recipes

### Add a block
1. Copy `packages/blocks/src/coupon-button/` (a small leaf) or `packages/blocks/src/field/` (a leaf using `woocommerce_form_field()`).
   - `block.json`: apiVersion 3, `category: "yaycheckout"`, `render: file:./render.php`.
   - `index.ts`: calls `registerDynamicBlock( metadata, icon, Edit )` from `packages/blocks/src/shared/register-dynamic-block.tsx`. A parent with inner blocks passes `saveNestedBlocks`, because a null `save()` drops the inner blocks.
2. Add the icon to `packages/blocks/src/shared/block-icons.tsx` and the name to `BlockRegistry::ORDER`.
3. For a section, add it to `LayoutMarkupReader::SECTION_SLOTS`. If it must refresh on `update_checkout`, give it a unique class and an entry in `CheckoutFragments`.
4. For a Pro block, put it under `packages/blocks/src/pro/` and add it to `BlockRegistry::PRO_BLOCKS` and `shared/pro-blocks.ts`.
5. Run `./scripts/run.sh build`: an unbuilt block never registers.

### Add a setting
See [admin.md § Recipes](admin.md#recipes). PHP only changes `Models\Settings::schema()`, because the REST output, the bootstrap and sanitising all derive from it.

### Add a REST route
Add a controller in `includes/Controllers/` that extends `BaseRestController`, and register it in `Engine\RestAPI`. A Pro route goes in `includes/Pro/` and is registered from `ProServiceProvider::boot()`.

### Add a component that hooks into WordPress
Use `Utils\SingletonTrait`, add the hooks in the constructor, and add `::get_instance()` to the list in `includes/YayCheckout.php`.
