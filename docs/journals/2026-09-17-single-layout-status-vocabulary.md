# 2026-09-17 — One layout status vocabulary

Commits `72f1eba..612fcd6` on `dev/edd`. Plan: `plans/260917-2105-single-layout-status-enum-truthful-post-status`.

## What changed

- `post_status = 'publish'` now implies a snapshot. `Engine/LayoutPublishSync` hooks `transition_post_status` and calls `LayoutRepository::publish()` on any transition into `publish` (editor Publish button, cron, quick edit, CLI). A static `is_publishing()` flag stops it re-entering from `publish()`'s own status flip.
- `Engine/LayoutPublishGuard` dry-runs `DerivedCacheBuilder::build()` on `rest_pre_insert_yay_checkout_layout` when a `wp/v2` request moves a layout to `publish` **or** `future`, and answers 400 `yaycheckout_layout_markup_invalid` before core saves.
- `LayoutPostStatus` and `Layout::status()` are gone. `LayoutStatus` holds the WP values (`draft | pending | future | publish | trash`) and the REST `status` is the raw `post_status`. SPA enum, badge and Set-active modal read those values; PHP and SPA shipped in one commit because the zod schema is strict.
- First PHPUnit harness: wp-phpunit from composer against a dedicated `yaycheckout_tests` DB, plugin booted without WooCommerce. 26 integration tests.

## Decisions

- Guard fires on the transition only; a published layout given bad *content* keeps its old snapshot and the SPA `/publish` rejects it later.
- `activate()` also publishes a `publish` row with no snapshot (non-REST publish of unusable markup leaves that state) — review finding, one-line guard.
- `counts()` keys `listed`/`archived` stay; they are API names, not wire statuses.
- No backfill for pre-existing `publish` rows without snapshots: plugin unreleased.

## Gotchas worth remembering

- wp-phpunit reads `WP_PHPUNIT__TESTS_CONFIG`, not core's `WP_TESTS_CONFIG_FILE_PATH`.
- `update_post_meta` overwrites in place, so a meta row count cannot prove "written once"; the tests count `added/updated_post_meta` actions instead.
- Core normalises `<!-- wp:x --><!-- /wp:x -->` to `<!-- wp:x /-->` on save.
- `wp_insert_post` flips a past-dated `future` to `publish` *after* `rest_pre_insert` runs — the reason the guard also checks `future`.
- The test DB is dropped per run: two concurrent `composer test` processes collide.
- `WPMU_PLUGIN_DIR` is pinned to a missing dir so the Local site's mu-plugins stay out of the test process.
