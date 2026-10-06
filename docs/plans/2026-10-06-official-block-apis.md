# Official Block APIs Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec: the requirements below are the brief.

**Status:** active
**Goal:** make every YayCheckout block use the block APIs WordPress core and WooCommerce use for the same job, in place of hand-rolled equivalents.
**Spec:** none, planned from the request
**Architecture:** each task swaps one home-grown pattern for the core API that does the same job: `useInnerBlocksProps`, `registerBlockType( metadata, … )`, `editorStyle`, in-canvas `RichText`, the `lock` attribute, `example`/`keywords`. The storefront markup (`render.php`) doesn't change, except that Checkout stops writing `templateLock`. Matching core keeps behaviour predictable for merchants who know other blocks, and leaves less custom code to maintain.
**Touches:** `packages/blocks/src/**` (edit, index, block.json, new `editor.css` files), `packages/blocks/src/shared/register-dynamic-block.tsx` (shared: every block's `index.ts`), `.claude/rules/blocks.md`. No PHP changes.
**Tests:** none. Placement and editor UI are outside CLAUDE.md's guarded risks, so each task gets a manual check.

## Requirements

- **Goal:** the layout editor behaves like core blocks: containers render their inner blocks without extra wrappers, text is edited in the canvas, the inserter previews blocks, List View names steps, and Checkout can't be deleted by accident.
- **Out of scope:** storefront markup and `render.php` output; `__experimental*` support keys (core 7.1 still uses them in 73+ of its own block.json files); the publish guard; `BlockRegistry` PHP; Billing/Shipping section titles (still edited in the sidebar).
- **Acceptance criteria:**
  1. No container edit renders `<InnerBlocks>`. Each uses `useInnerBlocksProps`.
  2. Every `index.ts` passes the whole `metadata` object to registration.
  3. No `style={ … }` with constant values is left in `packages/blocks/src`. Computed values (widths and the like) may stay inline.
  4. Continue, Back and Place order text and the Step title are edited by typing in the canvas. Their sidebar text fields are gone.
  5. A new Steps block holds two Steps. List View shows each Step by its title.
  6. Hovering any YayCheckout block in the inserter shows a preview.
  7. Checkout shows a lock icon and has no Delete action until it's unlocked. Saved markup no longer holds `templateLock`.
  8. End to end: `pnpm build:blocks` succeeds, the SPA type-check exits 0, and an existing layout opens with no block-validation warnings, no console errors, and the same storefront output as before.
- **Decided with the user (2026-10-06):**
  - Include all four merchant-visible changes: inline text editing, Step names in List View, Steps starting with 2 Steps, inserter previews.
  - Lock Checkout against removal (`lock: { remove: true }`), as WooCommerce does; the save lock stays as a backstop.
  - Fold in the uncommitted placement/appender change: Task 1's commit includes it.
  - Continue/Back stay allowed anywhere in Checkout (earlier decision, unchanged).

## Global constraints

- Never hand-edit `assets/dist/`. Change `packages/blocks/src/` and rerun `pnpm build:blocks`.
- A block's `render.php` is edited only in `packages/blocks/src/<block>/`.
- Commits follow `type(scope): summary`, end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`, and stage files by name.
- Run commands from the repo root on Node 22 (`nvm use`), pnpm only.
- Comments: an inline comment only carries a why; no comment cites a doc or plan.
- `packages/blocks/src/shared/` is also the SPA's `@blocks-shared`. After touching it, type-check the SPA.

## Review focus

- **Entities in edited text:** RichText stores `&` as `&amp;` and `<` as `&lt;`. Expected: a Step titled `Pay & ship` shows `Pay & ship` in the storefront header, in Progress, in the List View label and on the button. `esc_html()`/`esc_attr()` don't double-encode, so PHP needs no change. Checked in Task 4.
- **Existing layouts:** layouts saved before this change have raw `&` in titles, `"templateLock":…` on Checkout, and no `lock`. Expected: they open without "unexpected or invalid content" warnings, and Checkout is locked through the attribute default. Checked in Tasks 4 and 5.
- **Inserter previews outside Checkout:** the preview renders an `edit` with no Checkout or Billing ancestor. Expected: Field, Progress and Billing previews draw without throwing. Checked in Task 6.

---

### Task 1: Containers use `useInnerBlocksProps`; Steps starts with two Steps

**Files:**
- Modify: `packages/blocks/src/checkout/edit.tsx`, `packages/blocks/src/steps/edit.tsx`, `packages/blocks/src/step/edit.tsx`, `packages/blocks/src/shared/address-section-edit.tsx`
- Modify: `.claude/rules/blocks.md`, the "Placement" bullet

**Interfaces:**
- Consumes: `useBlocksAllowedExcept()` / `isCheckoutSection()` from `packages/blocks/src/shared/allowed-blocks.ts` (already in the working tree)
- Produces: nothing new

- [ ] **Step 1: Checkout and Steps.** Replace `<div { ...blockProps }><InnerBlocks …/></div>` with `<div { ...useInnerBlocksProps( blockProps, options ) } />`. Keep the current options: Checkout `templateLock: false`; Steps `templateLock: false`, `defaultBlock`, `directInsert`, `renderAppender: InnerBlocks.ButtonBlockAppender`. Add to Steps: `template: [ [ 'yaycheckout/step' ], [ 'yaycheckout/step' ] ]`. Drop Checkout's doc comment about the saved `templateLock`; Task 5 removes that attribute.
- [ ] **Step 2: Step.** The body becomes `<div { ...useInnerBlocksProps( { className: 'yaycheckout-step__body' }, { allowedBlocks, template, templateLock: false, renderAppender } ) } />`. The header stays outside it, so the editor DOM matches `step/render.php`.
- [ ] **Step 3: Billing/Shipping.** Use core's `children` pattern: `const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, { allowedBlocks, templateLock: false, renderAppender } )`, then `<div { ...innerBlocksProps }>{ heading }{ children }</div>`. This matches `billing/render.php`: the h3 sits beside the fields in the wrapper.
- [ ] **Step 4: Rules doc.** In `.claude/rules/blocks.md`, add one clause to the Placement bullet: containers render inner blocks through `useInnerBlocksProps`, never an `<InnerBlocks>` element inside a wrapper.
- [ ] **Step 5: Verify.** Run `pnpm build:blocks`; expect `compiled successfully`. Open `/wp-admin/post-new.php?post_type=yay_checkout_layout` and insert Steps; expect two Steps, each holding a Continue button. In the canvas iframe, run `document.querySelectorAll('.yaycheckout-billing > .block-editor-inner-blocks').length`; expect `0`, meaning no extra wrapper. Billing, Step and Steps still show their "+" appenders.
- [ ] **Step 6: Commit.** Stage the four edit files, `.claude/rules/blocks.md`, `docs/plans/2026-10-06-official-block-apis.md`, and the earlier uncommitted change: `docs/architecture.md`, `packages/blocks/src/checkout/inserter-root.ts`, `packages/blocks/src/{express-pay,order-summary,steps}/block.json`, `packages/blocks/src/shared/allowed-blocks.ts`. Message: `refactor(blocks): use core appenders, placement rules and inner-block props for containers`

### Task 2: Register from metadata; Step title in List View

**Files:**
- Modify: `packages/blocks/src/shared/register-dynamic-block.tsx` (shared: every `packages/blocks/src/*/index.ts`)
- Modify: every `packages/blocks/src/*/index.ts` (19 files, same one-word change); `packages/blocks/src/step/index.ts` also adds settings

**Interfaces:**
- Produces: `registerDynamicBlock( metadata: BlockMetadata, edit, save = () => null, settings = {} ): void`. `BlockMetadata` is `{ name: string } & Record< string, unknown >`, cast to `registerBlockType`'s first parameter inside the function.

- [ ] **Step 1:** Change `registerDynamicBlock` to take `metadata` and call `registerBlockType( metadata, { icon: blockIcons[ metadata.name ], ...settings, edit, save } )`. This is the documented `block.json` pattern; the server bootstrap still supplies the same fields.
- [ ] **Step 2:** In every `index.ts`, change `registerDynamicBlock( metadata.name, …` to `registerDynamicBlock( metadata, …`.
- [ ] **Step 3:** In `step/index.ts`, pass `settings.__experimentalLabel = ( attributes, { context } ) => …`. In the `'list-view'` context, return `attributes.metadata?.name` or `stepTitle( attributes.title )`, decoded with `decodeEntities` from `@wordpress/html-entities`. In any other context return `undefined`. This mirrors `core/heading`'s label.
- [ ] **Step 4: Verify.** Run `pnpm build:blocks`, then `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` (exit 0). In the editor, rename a Step to `Pay & ship` and open List View: expect `Pay & ship`. An untitled Step shows `Step`. Run `wp.blocks.getBlockType('yaycheckout/step').description`; expect the block.json description.
- [ ] **Step 5: Commit.** Stage `register-dynamic-block.tsx` and the 19 `index.ts` files. Message: `refactor(blocks): register blocks from their block.json metadata`

### Task 3: Editor previews styled through `editorStyle`

**Files:**
- Create: `packages/blocks/src/<block>/editor.css` for each block with inline preview styles: express-pay, extra-field, order-review, order-summary, payment, place-order, shipping-methods, slot. Also `packages/blocks/src/shared/field-preview.css`.
- Modify: those blocks' `edit.tsx` and `block.json`; `packages/blocks/src/shared/field-preview.tsx`

**Interfaces:**
- Produces: CSS classes named `yaycheckout-<block>__<part>` (for example `yaycheckout-place-order__button`), which Task 4 reuses for Place order.

- [ ] **Step 1:** Move each constant `style={ { … } }` into a class rule in that block's `editor.css`. Name classes `yaycheckout-<block>__<part>`, and scope rules under `.editor-styles-wrapper` so they never reach the storefront. Keep inline only values computed from attributes, such as field width. `field-preview.tsx` imports `./field-preview.css`.
- [ ] **Step 2:** In each `edit.tsx`, add `import './editor.css';`. In each `block.json`, add `"editorStyle": "file:./index.css"`; that's where `wp-scripts` writes CSS imported from the editor script. For blocks whose only CSS comes from `field-preview.css` (contact, field, extra-field), add the same `editorStyle` line too.
- [ ] **Step 3: Verify.** Run `pnpm build:blocks`; expect `assets/dist/blocks/<block>/index.css` for each block above. Then run `git grep -n "style={ {" -- packages/blocks/src`; only computed-value lines remain, and each one gets a one-line why comment. In the editor, every preview looks as before: same borders, spacing and colours as a screenshot taken before the change.
- [ ] **Step 4: Commit.** Stage the new CSS files and the edited `edit.tsx`, `block.json` and `field-preview.tsx` files. Message: `refactor(blocks): move editor preview styles into editorStyle files`

### Task 4: Edit button text and Step titles in the canvas

**Files:**
- Modify: `packages/blocks/src/step-button/edit.tsx`, `packages/blocks/src/back-button/edit.tsx`, `packages/blocks/src/place-order/edit.tsx`, `packages/blocks/src/place-order/editor.css`, `packages/blocks/src/step/edit.tsx`
- Modify: `.claude/rules/blocks.md`, the Patterns list

**Interfaces:**
- Consumes: `yaycheckout-place-order__button` from Task 3; `stepTitle()` from `packages/blocks/src/shared/step-title.ts`

- [ ] **Step 1: Continue and Back.** Render `<RichText { ...useBlockProps( { className } ) } tagName="div" …/>`, not a `<button>`: typing inside a button lets Space click it, which is why core/search edits its button label as a `div`. Props: `value={ buttonText }`, `onChange`, `placeholder={ __( 'Continue' | 'Back', 'yaycheckout' ) }`, `allowedFormats={ [] }`, `withoutInteractiveFormatting`. Remove the `InspectorControls` panel and the `type: 'button'` prop. The CSS selectors are class-based (`.yaycheckout-step-button`), so the look is unchanged.
- [ ] **Step 2: Place order.** Inside the wrapper, use `<RichText tagName="div" className="yaycheckout-place-order__button" …/>` with the same props and placeholder `__( 'Place order', 'yaycheckout' )`. Remove its `InspectorControls`.
- [ ] **Step 3: Step.** In the header, replace the title span with `<RichText tagName="span" className="yaycheckout-step__title" value={ title } placeholder={ stepTitle( '' ) } allowedFormats={ [] } withoutInteractiveFormatting onChange …/>`. Remove the `InspectorControls` panel. Progress already edits the same attribute this way.
- [ ] **Step 4: Rules doc.** Add a Patterns bullet to `.claude/rules/blocks.md`: text a merchant types is edited in the canvas with `RichText` (`allowedFormats={ [] }`), not a sidebar field; `render.php` prints it with `esc_html()`, which leaves the stored entities as they are.
- [ ] **Step 5: Verify.** Run `pnpm build:blocks`. In the editor, click Continue and type `Next & pay`; press Space mid-word and expect a space, not a click. Set a Step title to `Pay & ship` and save the draft. Preview the storefront through the layout's preview link: expect `Next & pay` on the button and `Pay & ship` in the Step header and Progress, with no visible `&amp;`. Run `curl -s <preview url> | grep -c '&amp;amp;'`; expect `0`. Open a layout saved before this change; expect no block warnings.
- [ ] **Step 6: Commit.** Stage the five files and `.claude/rules/blocks.md`. Message: `feat(blocks): edit button text and step titles in the canvas`

### Task 5: Lock Checkout against removal; drop `templateLock`

**Files:**
- Modify: `packages/blocks/src/checkout/block.json`

- [ ] **Step 1:** In `attributes`, remove `templateLock` and add `"lock": { "type": "object", "default": { "remove": true } }`, WooCommerce's pattern for required sections. In `supports`, remove `"lock": false` so core's lock UI comes back.
- [ ] **Step 2: Verify.** Run `pnpm build:blocks`. On a new layout, select Checkout: the toolbar shows a lock icon, the options menu has no Delete, and Backspace on the selected block does nothing. Unlock it through the lock dialog; Delete appears. Open an existing layout: Checkout is locked, no warnings. After a save, run `wp.data.select('core/editor').getEditedPostContent()`; it doesn't contain `templateLock`.
- [ ] **Step 3: Commit.** Stage `packages/blocks/src/checkout/block.json`. Message: `feat(blocks): lock the Checkout block against removal`

### Task 6: Inserter previews and search keywords

**Files:**
- Modify: every `packages/blocks/src/*/block.json` (19 files)

- [ ] **Step 1:** Give every block an `example` and 2–4 `keywords`. Leaf blocks get `"example": {}`, or attributes that show a label (Step button `{ "buttonText": "Continue" }`). Containers get inner blocks:
  - Checkout: Contact, Billing, Payment, Place order
  - Billing / Shipping: two Fields, `billing_first_name` and `billing_last_name` (`shipping_*` for Shipping)
  - Steps: two Steps titled `Information` and `Payment`
  - Step: one Step button

  Keywords:

  | Block | Keywords |
  |---|---|
  | contact | `email`, `customer` |
  | billing | `address`, `invoice` |
  | shipping | `address`, `delivery` |
  | steps | `accordion`, `multi-step`, `wizard` |
  | step | `accordion`, `section` |
  | progress | `steps`, `indicator` |
  | step-button | `continue`, `next` |
  | back-button | `previous`, `return` |
  | payment | `gateway`, `card` |
  | place-order | `submit`, `buy` |
  | order-summary | `cart`, `totals` |
  | order-review | `cart`, `totals` |
  | express-pay | `apple pay`, `wallet` |
  | shipping-methods | `rates`, `delivery` |
  | field | `input`, `form` |
  | extra-field | `custom`, `input` |
  | slot | `hook`, `placeholder` |
  | checkout | `woocommerce`, `form` |

  Keywords in block.json are translated by `register_block_type` through the textdomain.
- [ ] **Step 2: Verify.** Run `pnpm build:blocks` and `composer lint` (no new failures). In the editor, open the inserter and hover each YayCheckout block; every one shows a preview, and the console shows no errors from Field, Progress or Billing (`read console: pattern "yaycheckout|Error"`). Search `wizard`; expect Steps.
- [ ] **Step 3: Commit.** Stage the 19 block.json files. Message: `feat(blocks): add inserter previews and search keywords`

### Final verification

- [ ] Run `pnpm build:blocks`, then `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` (exit 0), then `composer lint`; expect no new failures against `dev/edd`.
- [ ] Open a layout made from the `multi-step-wizard` template, saved before this branch. Expect no "unexpected or invalid content" warnings and no console errors. Its storefront preview should match `dev/edd`'s HTML, apart from attribute order, the dropped `templateLock`, and edited text.
