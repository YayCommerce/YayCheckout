# Other plugins' JavaScript-only blocks disappear from every other editor

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`BlockRegistry::allowed_blocks()` runs on `allowed_block_types_all` at priority 999. In the `other` context, which `BlockRegistry::editor()` returns for any post type but the layout and for the site, widget and Customizer widget editors, it calls `BlockRegistry::without()` to drop every YayCheckout block. When the incoming value is `true` ("every block"), `without()` first replaces it with `array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() )`: the blocks registered in PHP. Core's `checkAllowList()` in the block editor refuses any name missing from an array, so a block registered only through JavaScript `registerBlockType()` drops out of the inserter. Introduced by `03edcca` (PR #23, on `main`) and reshaped in `1a9bd06`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

While YayCheckout is active, a site owner can't insert another plugin's JavaScript-only blocks in posts, pages, templates or widget areas. Nothing points at YayCheckout as the cause, so the other plugin gets the blame and the support ticket.

## Suggested fix

Leave `true` alone outside the layout editor, and keep YayCheckout blocks out another way. Every YayCheckout block but Checkout already has a `parent` or `ancestor` that is a YayCheckout block, so only the Checkout block needs hiding. Hide it in the editor script once the editor's post type is known, or subtract names in PHP only when the incoming value is already a list.

## Done when

- [ ] `BlockRegistry::allowed_blocks()` returns `true` unchanged when it receives `true` in the `other` context.
- [ ] With a plugin active that registers a block only in JavaScript, that block appears in the inserter of a post, a page, the site editor and the widget editor.
