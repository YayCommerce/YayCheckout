# A layout overrides the Design page per token group, through a sparse layer in its own meta

**Purpose:** why a layout's own design lives in layout meta as a per-group layer over the global design, why core's `wp/v2` writes it from the editor, and why only Pro applies it.

**Update when:** never edit it. Supersede it with a new record if the layer moves out of layout meta, becomes per token instead of per group, gets its own REST route, or reaches Free.

## Status

Proposed, 2026-10-09.

## Context

- The Design page stores one global design in the option `yaycheckout_design`: token sections (colours, typography, inputs), each with an `enabled` switch, plus custom CSS. `Renderer\CheckoutBaseStyles` prints it on the storefront and in the layout editor canvas.
- Merchants with several layouts (assignment rules send different shoppers to different ones) want one layout to look different, without forking the whole design: a later global edit should still reach every group the layout did not change.
- A layout is one post ([decision record](2026-10-05-layout-is-one-post.md)). Its assignment rules already live in its meta, are carried by duplicate, export and import, and the editor saves the post through core's `wp/v2`.
- Options turned down:
  - A per-token layer with a Global/Overridden state and a reset per token: too fine for the editor's narrow sidebar, and per-group switches read more clearly. The storage would have the same shape.
  - One option keyed by layout id: it outlives deleted layouts, and duplicate, export and import would each need their own plumbing.
  - A YayCheckout REST route for the layer: the editor would need a second save beside the post's, with its own dirty state, undo and failure handling.
  - A whole design copy per layout: every later global edit would stop reaching that layout.
  - A panel in the editor's Layout tab: cramped, and hidden whenever a block is selected.

## Decision

- A layout's design is a sparse layer in the post meta `_yaycheckout_design` (`Layout::META_DESIGN`): `{ tokens?: { <section>: { <every key of that section> } }, custom_css?: string }`. A section in the layer replaces the global section whole. Layer CSS prints after the global CSS. A missing section or CSS follows global. An empty layer is deleted, never stored as `{}`.
- Reads are forgiving and writes are strict. `Design::project_layer()` keeps a section only when every key is present and valid, so after a schema change a stale section falls back to global instead of failing. `Design::validate_layer()` refuses a `wp/v2` save with a 400 that names the first missing or invalid token, or unsafe CSS. Unknown sections and keys are dropped, as they are for the global design.
- Every reader asks `DesignRepository::for_layout()`, which passes the global design through the filter `yaycheckout_design_for_layout`. Only Pro hooks that filter, registers the meta for `wp/v2` and validates the save. Free always prints global. A Free site keeps any stored layer unused, and an upgrade applies it again.
- The sidebar offers one switch per group, Global or Custom. Custom always prints the group (`enabled` true); a layer can still hold `enabled: false` from an import or a direct `wp/v2` write, and the storefront honours it.
- The editor's Design sidebar edits the layer as the post's meta, so the layer is saved, dirtied and undone with the layout. A `<style>` in the canvas shows unsaved values. Duplicate copies the layer, and export and import carry it, dropping invalid sections instead of refusing the file.

## Consequences

- A layout differs from global only where the merchant chose. A global edit still reaches every group left on Global.
- Saving is core's: no new route, save button or dirty state. The publish guard that blocks saving a live layout also blocks saving its design.
- Groups are all-or-nothing: changing one colour copies the whole colours section into the layer, so later global edits to the other colours no longer reach that layout until the group goes back to Global.
- Adding a key to an existing section makes every stored layer section for it incomplete, so those layouts fall back to global for that group. A schema change has to say so in its release note.
- The sidebar's CSS-variable builder copies `DesignStyles::vars()` in TypeScript (`packages/blocks/src/shared/design-vars.ts`). The two must change together.
