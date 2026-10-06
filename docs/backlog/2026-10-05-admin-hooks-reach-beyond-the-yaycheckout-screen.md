# Admin hooks reach past the YayCheckout screen, and the screen check hard-codes a menu title

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Body class.** `Engine\Admin\Settings::admin_body_class()` (`includes/Engine/Admin/Settings.php`) adds `yay-ui` to the `<body>` of every admin screen. The SPA styles that key on it (`apps/admin/src/styles/preflight.css`) load only on the YayCheckout screen.
- **Dev preamble.** With `YAYCHECKOUT_IS_DEVELOPMENT` true, `RegisterDev::render_dev_refresh()` prints the Vite React-refresh preamble on `admin_footer` of every admin screen: a module import from `http://localhost:3040`. The release removes `includes/Engine/Register/RegisterDev.php`, so this is dev only.
- **Hook suffix.** `Engine\Admin\Settings::admin_enqueue_scripts()` loads the SPA only when the hook suffix is the literal `yaycommerce_page_yaycheckout`. WordPress core's `wp-admin/includes/plugin.php` `get_plugin_page_hookname()` builds that suffix from the parent menu's title, as `add_menu_page()` recorded it. admin-shell's `TopLevelMenu::register_menu()` lets the first YayCommerce plugin to register the `yaycommerce` menu win, so that plugin's title decides the suffix. admin-shell's `PluginSubmenu::register()` gets the real suffix from `add_submenu_page()` but doesn't expose it.

The body class came in `9b620fe`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

No visible harm is traced today. Any stylesheet that keys on `body.yay-ui`, ours or another YayCommerce plugin's, would restyle every admin screen (inferred). The dev preamble makes every admin page import from the dev server, and log an error when Vite isn't running. If another YayCommerce plugin registers the menu under a different or translated title, the YayCheckout page loads blank (inferred).

## Suggested fix

Add `yay-ui` only on the YayCheckout screen, and print the dev preamble only where the SPA is enqueued. Identify the screen by the `page` query arg, or by the suffix `get_plugin_page_hookname( 'yaycheckout', 'yaycommerce' )` returns, not by a literal. Exposing the suffix from admin-shell is the cleaner fix, but admin-shell is a dependency: change it upstream, never in `vendor-prefixed/`.

## Done when

- [ ] On `wp-admin/edit.php`, `<body>` has no `yay-ui` class, while the YayCheckout screen keeps it.
- [ ] With `YAYCHECKOUT_IS_DEVELOPMENT` true, `wp-admin/index.php` loads nothing from `http://localhost:3040`.
- [ ] `Engine\Admin\Settings::admin_enqueue_scripts()` doesn't compare against the literal `yaycommerce_page_yaycheckout`.
