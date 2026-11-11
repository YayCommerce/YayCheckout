---
area: helpers
updated: 2026-11-12
updated_ref: edf2074
covers: [includes/Helpers/, includes/Repositories/, includes/Services/, includes/Support/, includes/Renderer/CheckoutPage.php, includes/Renderer/CheckoutScope.php, includes/Renderer/BlockEmptiness.php, includes/Blocks/BlockRegistry.php, apps/admin/src/hooks/, apps/admin/src/lib/, packages/blocks/src/shared/]
---
# Helpers: reuse before you write

> Update when: a helper below is added, renamed or removed, or a new one is meant for reuse. Find more with `yayh code find <verb> <noun>`.

## Edition and settings

| Use | When |
|---|---|
| `Support\Plan::is_pro()` (`includes/Support/Plan.php`) | the only edition check in PHP |
| `SettingsRepository::get()`, `save()`, `is_safe_mode()` (`includes/Repositories/SettingsRepository.php`) | all settings access. `save()` takes values already through `Models\Settings::sanitize()` |
| `Models\Settings::schema()` | the one definition of every setting |

## Layouts

| Use | When |
|---|---|
| `LayoutRepository::find()`, `active( $context )`, `activate()`, `deactivate()`, `forget_active()`, `archive()`, `duplicate()`, `reset()` (`includes/Repositories/LayoutRepository.php`) | every layout read and write. Never write `yaycheckout_active_layout` yourself |
| `LayoutRepository::depends_on_cart()` | whether the served layout is pinned per page load |
| `Renderer\CheckoutPage::get_instance()->layout()`, `takes_over()`, `is_logged_out_view()` (`includes/Renderer/CheckoutPage.php`) | "which layout is served now", from `wp` on |
| `Services\LayoutMarkupReader::plain_text()`, `rich_text()`, `slots()`, `publish_error()` (`includes/Services/LayoutMarkupReader.php`) | merchant text in `render.php`, the layout's sections, whether it may go live |
| `Services\LayoutAssignment` (`includes/Services/LayoutAssignment.php`) | evaluating assignment rules against shopper facts |
| `Helpers\AdminUrlHelper::edit_layout()` (`includes/Helpers/AdminUrlHelper.php`) | a link to a layout in the SPA from outside it |

## Storefront output

| Use | When |
|---|---|
| `Helpers\KsesHelper::storefront_html()` (`includes/Helpers/KsesHelper.php`) | `wp_kses` for HTML assembled from WooCommerce pieces (`<bdi>` prices, images, SVG chips) |
| `Helpers\JsonElementHelper::html()`, `print()` (`includes/Helpers/JsonElementHelper.php`) | data for a storefront script that `update_order_review` can swap as a fragment |
| `Helpers\ButtonClassHelper::classes()` (`includes/Helpers/ButtonClassHelper.php`) | a storefront button that follows the block theme's button style |
| `Services\Disclosure` (`includes/Services/Disclosure.php`) | a field that hides behind "+ Add …". JS mirror: `shared/disclosure.tsx` |
| `Services\RequiredIndicator`, `Services\RequiredFieldOverlay` | the required/optional label suffix, and making WooCommerce's field registry follow the layout |
| `Services\ExtraFieldSubmission::collect()` | turning a submission into extra-field rows and errors |
| `Renderer\CheckoutScope::classes()` (`includes/Renderer/CheckoutScope.php`) | a block that may render outside Checkout |
| `Renderer\BlockEmptiness::wrapper()` (`includes/Renderer/BlockEmptiness.php`) | a wrapper that supports the "Block is empty" visibility rule |
| `Blocks\BlockRegistry::built_dirs()`, `locked_blocks()`, `registered_names()` (`includes/Blocks/BlockRegistry.php`) | built block paths and edition-aware block lists |
| `Helpers\CheckoutSetupHelper::shipping_needs_setup()`, `payment_needs_setup()` (`includes/Helpers/CheckoutSetupHelper.php`) | WooCommerce setup gaps that leave a checkout unusable |

## Plumbing

| Use | When |
|---|---|
| `Controllers\BaseRestController` (`includes/Controllers/BaseRestController.php`) | every REST controller: `exec_read()`, `exec_write_wp()`, `error_*()`, `id_args()` |
| `Helpers\Logger::error()`, `warning()`, `exception()`, `debug()` (`includes/Helpers/Logger.php`) | faults, to WooCommerce → Status → Logs (source `yaycheckout`) |
| `Helpers\DateHelper::to_iso8601()`, `parse()`, `now_iso8601()` (`includes/Helpers/DateHelper.php`) | every timestamp on the wire |
| `Helpers\ExceptionHelper::message()` (`includes/Helpers/ExceptionHelper.php`) | an exception's message as plain text |
| `Helpers\PreviewToken::mint()`, `parse()`, `checkout_url()`, `with_token()` (`includes/Helpers/PreviewToken.php`) | preview links. `with_token()` appends by hand, because `add_query_arg()` breaks `%%endpoint%%` |
| `Helpers\PluginHeaderHelper::describe()` (`includes/Helpers/PluginHeaderHelper.php`) | name and version of an installed plugin or theme |
| `Repositories\FontRepository::ensure_installed()`, `faces()` (`includes/Repositories/FontRepository.php`) | fonts in the Font Library |
| `Utils\SingletonTrait` (`includes/Utils/SingletonTrait.php`) | a component that adds hooks |

## Hooks the plugin offers

See [architecture.md § Hooks the plugin exposes](architecture.md#hooks-the-plugin-exposes).

## Admin SPA (`apps/admin/src/`)

| Use | When |
|---|---|
| `api`, `wordpressApi` (`lib/api/api.ts`) | every request: nonce, plain permalinks, 403 reload |
| `getErrorMsg()` (`lib/helpers/response.helper.ts`) | the message for a toast or catch |
| `formatDateTime()`, `formatDateAtTime()`, `formatRelative()` (`lib/helpers/date.helper.ts`) | showing ISO timestamps |
| `copyToClipboard()` (`lib/helpers/clipboard.helper.ts`), `useCopyWithFallback` | copying, including on http origins |
| `isPro`, `useIsPro` (`lib/plan.ts`) | edition checks |
| `useRouteLeaveGuard`, `useDebouncedSearch`, `useTypedConfirm` (`hooks/`) | unsaved-changes guard, a 300 ms search box, typed confirmation |
| `ProBadge`, `ProLinkBadge`, `UpgradeButton`, `UpgradePrompt` (`components/pro/ProGate.tsx`) | locked-feature UI (cosmetic only) |
| `SettingRow`, `SettingsSection` (`pages/settings/components/`) | settings form rows |
| `cn()` (`lib/utils.ts`) | class merging |

## Blocks (`packages/blocks/src/shared/`)

| Use | When |
|---|---|
| `registerDynamicBlock()`, `saveNestedBlocks()`, `listViewLabel()` (`register-dynamic-block.tsx`) | registering a block |
| `editor-data.ts` | the plan, post type and upgrade URL in the editor. Never redeclare them |
| `pro-blocks.ts` | the Pro block list (matches `BlockRegistry::PRO_BLOCKS`) |
| `block-icons.tsx` | block icons |
