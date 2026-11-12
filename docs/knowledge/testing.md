---
area: testing
updated: 2026-11-12
updated_ref: af09235
covers: [tests/php/, tests/js/, tests/e2e/.wp-env.json]
---
# Testing

> Update when: a test layer, a bootstrap, a shared stub or the Coverage table changes. Which layer a risk gets: the yay kit's `reference/testing.md`.

## Layers and how to run them

| Layer | Lives in | Run | Notes |
|---|---|---|---|
| unit | `tests/php/unit/` (`tests/php/unit.xml.dist`) | `yayh test run unit` | PHPUnit 9.6 + Brain Monkey, no WordPress, host PHP. ~0.1 s. |
| js | `tests/js/**/*.test.ts` (`tests/js/vitest.config.mjs`) | `yayh test run js` | Vitest at the root, `node` environment. Needs `php` on the PATH (see the PHP bridge below). |
| integration (wp-env) | `tests/php/integration/` (`tests/php/integration.xml.dist`) | `yayh test run integration` | The wp-env **tests** site (`tests/e2e/.wp-env.json`, `testsEnvironment: true`); start it with `yayh e2e env start`. Only the scaffold's `PluginLoadsTest` so far. |
| integration (Local MySQL) | `tests/Integration/` (`phpunit.xml.dist`) | `composer test` | The older suite, untouched: see [release.md § Tests](release.md#tests). `commands.test` still runs it. |

- `yayh test run all` runs unit, js and the wp-env integration layer; it reports integration as *skipped* while the tests site is down.
- Use Node 22 (`nvm use 22`). Under Node 20, `pnpm` fails with `ERR_UNKNOWN_BUILTIN_MODULE`.
- Tags: `@group baseline` on the PHP baseline classes and `@baseline` in the JS `describe` names. A test for a plan or bug is tagged `@group P-<n>` / `B-<n>`, or `@P-<n>` in JS. `yayh test run unit --id=B-1` runs one.

## Bootstraps and shared helpers

- **`tests/php/unit/bootstrap.php`** loads Composer's autoload, defines `ABSPATH`, and registers the same `YayCheckout\` → `includes/` autoloader as `yaycheckout.php:95-113`. Any plugin class loads by name. Nothing else of the plugin boots.
- **`tests/php/unit/TestCase.php`** (`YayTests\Unit\TestCase`) turns Brain Monkey on and stubs translation and escaping functions to return their input. Stub everything else per test, e.g. `Functions\when( 'wc_get_price_decimals' )->justReturn( 2 )`.
- **`tests/php/unit/stubs/WP_Error.php`**: a minimal `WP_Error` (code, message) for validators that return one. `require_once` it in the test that needs it.
- **`tests/js/helpers/php-const.ts`**: `phpConst( file, NAME )` reads a string or flat string-array `const` from a PHP file, for mirror checks (hard rule 7).
- **`tests/js/helpers/design-styles.php`**: the PHP bridge. It reads raw designs as JSON on STDIN and prints `LayoutDesign::from_meta()`'s tokens, the editor's font lists and `DesignStyles::css()`. `tests/js/blocks/shared/design-css.test.ts` asserts that `designCss()` prints the same text. `tests/js/admin/pages/layouts/assignment/condition-types.test.ts` runs `php -r` the same way to read `ConditionType::definitions()`. Prefer this over regex when a mirror is a PHP method, not a literal.

## Edition gates

- No baseline unit test touches `Plan::is_pro()` or `includes/Pro/`. A unit test of Pro code would stub the plan in the same way. The Local suite skips `tests/Integration/Pro/` on Free.
- `condition-types.ts` (the Pro Assignment drawer) is plain TS with no gate at module load. It is tested on both editions.

## Traps

- **Pure PHP isn't always 7.4-safe to run on 7.4 without WordPress.** The code uses `str_contains` and friends, which WordPress polyfills (`BlockGap.php`, `RequiredFieldOverlay.php`). The unit layer runs on the host's PHP 8, so a test of those classes on PHP 7.4 would need shims.
- **`@wordpress/*` isn't installed at the root.** It sits only in the admin app's and the blocks package's own node_modules, so mock it: `vi.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ) )`, then `await import()` the module (as `tests/js/admin/pages/layouts/assignment/condition-types.test.ts` does). `@wordpress/html-entities` exists in neither package.
- **`phpcs.xml` lints `tests/`.** `tests/php/` and `tests/js/` are excluded from `PrefixAllGlobals` (the kit's `YayTests\` namespace) and `AlternativeFunctions` only.
- **wp-env and the e2e package.** `pnpm install --ignore-workspace` in the e2e package exits non-zero on `ERR_PNPM_IGNORED_BUILDS` (fs-ext-extra-prebuilt), but the binaries are installed. Never run two `wp-env start`s at once. The first test-init run collided and left a corrupt WooCommerce zip in wp-env's download cache (`adm-zip` error on the next start). Delete that environment's folder under `~/.wp-env/` and start again before relying on the integration layer.
- **The release never ships tests or dev packages.** `scripts/run.sh release` copies an allowlist (no `tests/`) and runs `composer dump-autoload --no-dev`, so Brain Monkey and PHPUnit never reach the zip.

## Coverage

`priority` is the scout's ranking. `tests` is the file and tag, `candidate` (not written yet) or `bug: B-<n>`.

| unit | layer | priority | tests | source |
|---|---|---|---|---|
| `Layout::sanitize_rules` (groups, operators, ids, amounts, counts, roles, countries) | unit | critical | `tests/php/unit/Models/LayoutTest.php` @group baseline | `includes/Models/Layout.php:134-228` |
| `PreviewToken::mint` / `parse` / `with_token` | unit | critical | `tests/php/unit/Helpers/PreviewTokenTest.php` @group baseline | `includes/Helpers/PreviewToken.php` |
| `LayoutDesign::from_meta` / `is_gradient` / `sanitize_custom_css` | unit | critical | `tests/php/unit/Models/LayoutDesignTest.php` @group baseline | `includes/Models/LayoutDesign.php:336-470` |
| `ExtraFieldSubmission::collect` | unit | critical | `tests/php/unit/Services/ExtraFieldSubmissionTest.php` @group baseline | `includes/Services/ExtraFieldSubmission.php` |
| `CheckoutFragments` payment style, `css_color`, layouts, thumbnails | unit | important | `tests/php/unit/Renderer/CheckoutFragmentsTest.php` @group baseline | `includes/Renderer/CheckoutFragments.php:250-440,927-965` |
| `CheckoutFragments::css_safe_token` refuses `;` and `\` (B-1) | unit | important | `tests/php/unit/Renderer/CheckoutFragmentsTest.php` @group B-1 | `includes/Renderer/CheckoutFragments.php:431-438` |
| `visibility-rules` `normalize` / `isVisible` + constants mirror | js | critical | `tests/js/blocks/shared/visibility-rules.test.ts` @baseline | `packages/blocks/src/shared/visibility-rules.ts` |
| `design-css` `designCss` = `DesignStyles::css()` | js | critical | `tests/js/blocks/shared/design-css.test.ts` @baseline | `packages/blocks/src/shared/design-css.ts`, `includes/Renderer/DesignStyles.php` |
| `condition-types` wire transforms + `ConditionType` mirror | js | critical | `tests/js/admin/pages/layouts/assignment/condition-types.test.ts` @baseline | `apps/admin/src/pages/layouts/assignment/condition-types.ts` |
| `LayoutRepository` active set (`activate`, Free replace, Pro order) + `LayoutActiveGuard` | integration | critical | candidate | `includes/Repositories/LayoutRepository.php:245-390,689`, `includes/Engine/LayoutActiveGuard.php` |
| `LayoutRepository::bulk` | integration | critical | candidate | `includes/Repositories/LayoutRepository.php:647` |
| `LayoutCoreRestController` publish guard and read gates | integration | critical | candidate | `includes/Controllers/LayoutCoreRestController.php` |
| `LayoutRulesRestController` + `set_rules` | integration | critical | candidate | `includes/Pro/Controllers/LayoutRulesRestController.php`, `LayoutRepository.php:534` |
| `RequiredFieldOverlay::apply` / `apply_order_note` | unit | critical | candidate | `includes/Services/RequiredFieldOverlay.php` |
| `Settings::sanitize` / `from_option` | unit | important | candidate | `includes/Models/Settings.php:76-138` |
| `SettingsRestController::save_settings` (merge, 400, 403) | integration | important | candidate | `includes/Controllers/SettingsRestController.php:61` |
| `DesignStyles::css` custom CSS branch | unit | important | candidate | `includes/Renderer/DesignStyles.php:129-139` |
| `LayoutAssignment::outlook` / `reads_cart` edge cases | unit | important | candidate (most cases in `tests/Integration/Services/LayoutAssignmentTest.php`) | `includes/Services/LayoutAssignment.php` |
| `Disclosure::mode` / `text` | unit | important | candidate | `includes/Services/Disclosure.php` |
| `BlockGap::css_value` / `sanitize` | unit | important | candidate | `includes/Renderer/BlockGap.php` |
| `LiveValues` `format_money` / `format_counts` / `fill_html` | unit + integration | important | candidate | `includes/Renderer/LiveValues.php` |
| `CheckoutFragments::fragments` | integration | important | candidate | `includes/Renderer/CheckoutFragments.php:173-235` |
| Pro import, export, assignment options, setup and create routes | integration | important | candidate | `includes/Pro/Controllers/`, `includes/Controllers/SetupRestController.php` |
| `style-paths` `paymentStyleVars` + mirror | js | important | candidate | `packages/blocks/src/payment/style-paths.ts` |
| `summary-sources` | js | important | candidate | `packages/blocks/src/pro/step/summary-sources.ts` |
| `RequiredIndicator::swap_in` / `html` | unit | minor | candidate | `includes/Services/RequiredIndicator.php` |
| `PRO_BLOCK_METAS` vs `BlockRegistry::PRO_BLOCKS` | js | minor | candidate | `packages/blocks/src/shared/pro-blocks.ts` |
