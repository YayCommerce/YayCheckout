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

- **Blocks are dynamic.** Each block is a folder `packages/blocks/src/<block>/` with `block.json`, `render.php` and its editor script; `save()` emits no markup of its own. Leaf blocks return null, and a block with inner blocks (Checkout, Billing, Shipping) uses `saveNestedBlocks()`, which saves only the inner blocks. A renamed or added attribute needs a default in `render.php`, because stored layouts keep the old markup.
- **A new block** registers by itself once built. Add its name to `BlockRegistry::ORDER` (`includes/Blocks/BlockRegistry.php`) for its place in the inserter, and its icon to `packages/blocks/src/shared/block-icons.tsx`. A section block also goes in `LayoutMarkupReader::SECTION_SLOTS`, which decides the fragments `CheckoutFragments` swaps.
- **Placement** lives in `block.json`: `parent`, `ancestor`, `allowedBlocks` and `supports.multiple`. The one rule core can't express, keeping a block out of a container, is an `allowedBlocks` prop built by `useBlocksAllowedExcept()` (`packages/blocks/src/shared/allowed-blocks.ts`). A container renders its inner blocks through `useInnerBlocksProps`, never an `<InnerBlocks>` element inside a wrapper, and passes `renderAppender: useContainerAppender( clientId )` (`packages/blocks/src/shared/container-appender.ts`): core Group's rule, the outlined "+" button only while empty, otherwise core's appender, which shows while the container or a block in it is selected; `packages/blocks/src/checkout/inserter-root.ts` only points the header inserter at Checkout while no block is selected.
- **Text a merchant types** (button text, step titles) is edited in the canvas with `RichText` and `allowedFormats={ [] }`, not a sidebar field. It is stored with HTML entities (`&amp;`); `render.php` prints it with `esc_html()`, which does not encode them twice.
- **Editor-only styles** go in the block's `editor.css`, imported by `edit.tsx` and loaded through `"editorStyle": "file:./index.css"`, scoped under `.editor-styles-wrapper`. Sidebar layout uses `@wordpress/components` (`Flex`, `BaseControl`), never inline styles.
- **Markup:** render WooCommerce's own markup (`woocommerce_form_field()`, its class names and ids), because WooCommerce's checkout scripts and themes target it.
- **Editor data:** read the plan, post type and upgrade link from `packages/blocks/src/shared/editor-data.ts` (`window.yayCheckoutBlocks`, printed by `BlockRegistry`), never redeclare them.

## Couplings no check catches

- `packages/blocks/src/checkout/publish-guard.ts` repeats `LayoutMarkupReader::publish_error()`, and `packages/blocks/src/shared/extra-field-keys.ts` repeats `LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN`. Change both sides in the same commit. The server's copy decides publishing; for drafts the editor's lock is stricter, since the server accepts any draft. `packages/blocks/src/extra-field/edit.tsx` repeats the Key rules a third time.
- `packages/blocks/src/shared/` is also the SPA's `@blocks-shared`. A change there reaches the admin bundle too: build the blocks and type-check the SPA.
- Nothing type-checks or lints the blocks' TypeScript: `pnpm build:blocks` is the only check there, while `composer lint` covers each `render.php`. Build it, then open a layout in the editor.
