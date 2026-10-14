# Checkout requests read plugin headers from disk when a conflict entry has fixed_in

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** /yay-review of the safe mode fallback (`docs/plans/2026-10-13-safe-mode-fallback.md`), 2026-10-13, branch `alan/feat/safe-mode`, PR #114. Kept out of that PR to keep it to the fallback itself.

## Today

- `CheckoutHealth::conflicting_plugin()` runs on every checkout page load, every `update_order_review` AJAX and every submit.
- For a catalogue `conflict` entry that matches an active plugin and has `fixed_in` or no `title`, it calls `PluginHeaderHelper::describe()`, which loads `wp-admin/includes/plugin.php`, globs the plugin's folder and runs `get_plugin_data()` on its files.

## Why it matters

File I/O on the checkout's hot path, for a version that changes only when the plugin updates.

## Suggested fix

Read the installed version from the `get_plugins()` cache (`plugins` in the object cache) or memoise `describe()` per plugin file and its `filemtime()` in a transient that `upgrader_process_complete` and `activated_plugin` clear.

## Done when

- [ ] A checkout request with a matching `fixed_in` entry runs no `glob()` or `get_plugin_data()`.
