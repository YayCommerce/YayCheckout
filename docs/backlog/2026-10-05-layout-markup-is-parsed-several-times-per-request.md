# Layout markup is parsed several times per request, and block metadata is reread on every request

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`LayoutMarkupReader::blocks()` in `includes/Services/LayoutMarkupReader.php` calls `parse_blocks()` on every call, with no cache.

- A checkout page view parses the layout three times: `do_blocks()` in `templates/checkout-page.php`, then `LayoutMarkupReader::fields()` and `LayoutMarkupReader::extra_fields()` in `CheckoutRequiredFields::fields()`.
- An `update_order_review` call parses it once, for `LayoutMarkupReader::slots()` in `CheckoutFragments::fragments()`.
- A submit parses it four times, because `CheckoutExtraFields::submission()` isn't memoised and runs in both `CheckoutExtraFields::validate()` and `CheckoutExtraFields::persist()`.

Separately, `BlockRegistry::register_blocks()` in `includes/Blocks/BlockRegistry.php` globs the built block.json files under `assets/dist/blocks/` and calls `register_block_type()` 13 times on every request, reading each block's block.json and index.asset.php. There is no blocks manifest: `wp_register_block_metadata_collection()` needs WordPress 6.7, and `yaycheckout.php` declares 6.5. Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

`parse_blocks()` costs more as a layout grows, and checkout requests are the ones page caches skip and a store can least afford to slow down. The block metadata cost is paid on every front-end and admin request, not only on checkout.

## Suggested fix

Memoise `LayoutMarkupReader::blocks()` per markup string for the request, for example in a small static map keyed by a hash of the markup; the class stays a function of the markup. Memoise `CheckoutExtraFields::submission()` for the request. For the blocks, build a manifest with `wp-scripts build --blocks-manifest` and register through `wp_register_block_metadata_collection()` when it exists, keeping the glob for WordPress 6.5 and 6.6.

## Done when

- [ ] `LayoutMarkupReader::blocks()` calls `parse_blocks()` at most once per distinct markup string per request.
- [ ] `CheckoutExtraFields::submission()` runs `ExtraFieldSubmission::collect()` at most once per request.
- [ ] `pnpm build:blocks` writes a blocks manifest into `assets/dist/blocks/`.
- [ ] On WordPress 6.7 or later, `BlockRegistry::register_blocks()` registers the blocks through `wp_register_block_metadata_collection()`.
