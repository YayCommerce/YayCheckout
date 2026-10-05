---
paths:
  - "packages/blocks/**"
---

<!--
Purpose: rules for writing the checkout blocks in packages/blocks/: how a block is built, registered and placed, and its couplings to PHP and the admin SPA. Loads when Claude reads a matching file.
Update when: how blocks are built or registered changes, a new coupling to PHP or the SPA appears, or the build config changes.
-->

# Blocks

How blocks register, which editor offers them and how the embedded editor works is in [docs/architecture.md § Blocks and the embedded editor](../../docs/architecture.md#blocks-and-the-embedded-editor). TypeScript copies of PHP are in [docs/conventions.md](../../docs/conventions.md). A block's `render.php` follows the PHP rule.

## Patterns for new code

- **Blocks are dynamic.** Each block is a folder `src/<block>/` with `block.json`, `render.php` and its editor script; `save()` returns nothing. A renamed or added attribute needs a default in `render.php`, because stored layouts keep the old markup.
- **A new block** registers by itself once built. Add its name to `BlockRegistry::ORDER` (`includes/Blocks/BlockRegistry.php`) for its place in the inserter, and its icon to `src/shared/block-icons.tsx`. A section block also goes in `LayoutMarkupReader::SECTION_SLOTS`, which decides the fragments `CheckoutFragments` swaps.
- **Placement** lives in `block.json`: `parent`, `ancestor` and `supports.multiple`. No insert rule lives in JavaScript.
- **Markup:** render WooCommerce's own markup (`woocommerce_form_field()`, its class names and ids), because WooCommerce's checkout scripts and themes target it.
- **Editor data:** read the plan, post type and upgrade link from `src/shared/editor-data.ts` (`window.yayCheckoutBlocks`, printed by `BlockRegistry`), never redeclare them.

## Couplings no check catches

- `src/checkout/publish-guard.ts` repeats `LayoutMarkupReader::publish_error()`, and `src/shared/extra-field-keys.ts` repeats `LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN`. Change both sides in the same commit; the server's copy is the one that decides.
- `src/shared/` is also the SPA's `@blocks-shared`. A change there reaches the admin bundle too: build the blocks and type-check the SPA.
- Nothing type-checks or lints this package: `pnpm build:blocks` is the only check. Build it, then open a layout in the editor.
