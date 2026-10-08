# Full-Width Checkout Layout Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements section below is the contract.

**Status:** active
**Goal:** The checkout layout spans the full viewport width on the storefront and in the layout editor, so the theme's or FSE's content width, wide width and root padding never box it. The layout editor shows no title field.
**Spec:** none, planned from the request
**Architecture:** Both changes are CSS scoped to the layout. On the storefront, a stylesheet enqueued only when `CheckoutPage` takes over does two things. It strips horizontal margin, padding and max-width from `body.yaycheckout-page`, `.wp-site-blocks` and the `.woocommerce.yaycheckout-page` wrapper. It also resets the theme.json variables (`--wp--style--global--content-size`, `--wp--style--global--wide-size`, `--wp--style--root--padding-left/right`) inside the wrapper. In the editor, a `block_editor_settings_all` filter, applied to layout posts only, adds a canvas style that resets the same variables on `.is-root-container` and hides the post-title wrapper. I chose this over the alternatives, which edit theme.json data (`wp_theme_json_data_theme`) or set `supportsLayout: false`. Those change the theme for the whole request, or turn off layout styles for the merchant's Groups, including the default layout's 1200px Group.
**Touches:** `templates/` (no change needed: it already prints no page title), `includes/Renderer/CheckoutPage.php`, `includes/Engine/Register/ScriptName.php`, new `assets/css/checkout-page.css`, new `includes/Engine/Admin/LayoutEditorCanvas.php`, `includes/YayCheckout.php`, `docs/architecture.md`, new backlog item. No shared code.
**Tests:** none. This is presentation CSS, which falls under none of CLAUDE.md's checkout-logic cases, so each task ends with an exact manual check.

## Requirements

- **Goal:** A merchant's layout fills the page edge to edge on every theme, classic or block. The only width limits are the ones the layout's own blocks set. The layout editor shows no title, on the canvas or on the storefront.
- **Out of scope:** the document `<title>` in the browser tab (it stays). Renaming layouts from the SPA (backlog item added in Task 2). Vertical body or `.wp-site-blocks` padding from the theme. Changing `templates/default-layout.php` (its constrained Group keeps its explicit 1200px).
- **Acceptance criteria:**
  1. Storefront, TwentyTwentyFive (block theme): `getComputedStyle(document.querySelector('.woocommerce.yaycheckout-page')).width` equals `window.innerWidth` minus any scrollbar, and `document.body` has `padding-left`/`padding-right` of `0px`.
  2. Storefront: a top-level Group with layout "constrained" and no custom content width renders at full width (the reset content-size var), not the theme's 645px. A Group with an explicit `contentSize` (the default layout's 1200px) keeps it.
  3. Storefront, Storefront (classic) theme: the same width check as 1 passes.
  4. Storefront, every theme: no page-title heading (`h1.entry-title`, `.wp-block-post-title`) is printed. This holds today because `templates/checkout-page.php` never prints one; verify it stays that way.
  5. Layout editor (`post.php?post=<layout>&action=edit&yc-embed=1`), on TwentyTwentyFive: no "Add title" field in the canvas, and the root blocks span the canvas width with no theme side padding.
  6. Other post types' editors (a normal Page) still show their title and the theme's content width.
  7. End-to-end: on TwentyTwentyFive, create a layout from the SPA, add a top-level constrained Group with a cover block inside, and publish and activate it. The editor and `/checkout/` both show the Group full width with no title.
- **Decided with the user:**
  - Where is it limited? Both the storefront and the editor.
  - How to remove the title? Hide the title field in the editor canvas only. The post type keeps `title` support, because layout names live in `post_title` and the SPA list and search use them.
  - Rename after hiding the title? Not for now. Names are fixed at creation, and a backlog item records a rename in the SPA.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments, union types, `mixed`/`static`/`never` types, or trailing commas in parameter lists.
- WordPress ≥ 6.5 (`Requires at least: 6.5`).
- Never hand-edit `assets/dist/`. `assets/css/` is source, not generated.
- Free/Pro: none of this is Pro. Keep it out of `includes/Pro/` and don't reference `YayCheckout\Pro`.
- PHP style: WordPress standard, 4-space indents, short arrays, no Yoda. A class file opens with namespace, `use`, `defined( 'ABSPATH' ) || exit;`, docblock. Comments carry only a why and never cite docs.
- Storefront hooks stand down when `CheckoutPage::get_instance()->layout()` is null. The stylesheet is only enqueued inside `CheckoutPage::enqueue_scripts()`, which already runs only after the takeover.
- Git: branch from `dev/edd` as `trace/feat/full-width-layout`. Conventional Commits, with a `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` trailer. Stage files by name. Commit or push only when asked.

## Review focus

- **Theme `!important` widths.** A classic theme may put `max-width … !important` on `.woocommerce` or `body.woocommerce-checkout`. Expect the layout to still span full width. Task 1 Step 3 checks a classic theme (Storefront), and Task 1 Step 4 checks Divi.
- **`alignfull` blocks on root-padding-aware themes.** Their negative margins use `--wp--style--root--padding-*`. With the vars at 0, expect no horizontal overflow or scrollbar. Checked in Task 1 Step 2 and Task 2 Step 3.
- **Editor class names.** The title wrapper is `.editor-visual-editor__post-title-wrapper` on WP 6.5+. Older Gutenberg used `.edit-post-visual-editor__post-title-wrapper`. Expect the CSS to hide both. Checked in Task 2 Step 3.
- **Other editors.** Expect the canvas style only on `Layout::POST_TYPE` posts. Checked in Task 2 Step 4.
- **The merchant's custom CSS (Design page).** Expect it to still beat the new stylesheet: `checkout-page.css` is enqueued before `DesignStyles`. Checked in Task 1 Step 2.

---

### Task 0: Branch and save the plan

- [ ] **Step 1:** `git switch dev/edd && git pull`, then `git switch -c trace/feat/full-width-layout`.
- [ ] **Step 2:** Copy this plan to `docs/plans/2026-10-07-full-width-checkout-layout.md`, without the plan-mode note at the top.

### Task 1: Storefront renders the layout full width

**Files:**
- Create: `assets/css/checkout-page.css`
- Modify: `includes/Engine/Register/ScriptName.php` (new constant)
- Modify: `includes/Renderer/CheckoutPage.php:57-64` (`enqueue_scripts()`)
- Modify: `docs/architecture.md:62` (Storefront → Rendering)

**Interfaces:**
- Produces: `ScriptName::STYLE_CHECKOUT_PAGE = 'yaycheckout/style/checkout-page'`

- [ ] **Step 1: Add the stylesheet and enqueue it**

`ScriptName::STYLE_CHECKOUT_PAGE = 'yaycheckout/style/checkout-page'`.

In `CheckoutPage::enqueue_scripts()`, before `( new DesignStyles() )->enqueue()`, call `wp_enqueue_style( ScriptName::STYLE_CHECKOUT_PAGE, YAYCHECKOUT_PLUGIN_URL . 'assets/css/checkout-page.css', [], YAYCHECKOUT_VERSION )`. Enqueueing it first keeps the merchant's custom CSS printing after it. Update the method's docblock line to mention the full-width stylesheet.

`assets/css/checkout-page.css` sets these values. The selectors are the decision; keep a one-line why comment at the top: the theme's layout limits stop at the checkout, and the layout's own blocks set any width.
- `body.yaycheckout-page`: `margin-left: 0; margin-right: 0; padding-left: 0; padding-right: 0; max-width: none;`
- `body.yaycheckout-page > .wp-site-blocks, .woocommerce.yaycheckout-page`: `box-sizing: border-box; width: 100%; max-width: none; margin-left: 0; margin-right: 0; padding-left: 0; padding-right: 0;`
- `.woocommerce.yaycheckout-page`: `--wp--style--global--content-size: 100%; --wp--style--global--wide-size: 100%; --wp--style--root--padding-left: 0px; --wp--style--root--padding-right: 0px;`

Add `!important` to the width, max-width, margin and padding declarations only if Step 3 or Step 4 shows a theme winning, and say in the comment which theme needed it.

- [ ] **Step 2: Verify on TwentyTwentyFive**

Activate TwentyTwentyFive and open `/checkout/` with an active layout. In the console:
`[document.body, document.querySelector('.wp-site-blocks'), document.querySelector('.woocommerce.yaycheckout-page')].map(e => getComputedStyle(e)).map(s => [s.paddingLeft, s.paddingRight, s.maxWidth])`
Expected: every entry is `["0px","0px","none"]`. The wrapper's `offsetWidth` equals `document.documentElement.clientWidth`.

Then add a top-level Group (constrained, no custom width) with a paragraph to the layout and reload. Expected: the paragraph spans the full width, not 645px. The default layout's 1200px Group is still 1200px wide plus its 24px padding on each side. `document.documentElement.scrollWidth === document.documentElement.clientWidth` (no horizontal scroll). Adding `.yaycheckout-page{background:red}` as custom CSS on the Design page still turns the page red.

- [ ] **Step 3: Verify on the Storefront theme (classic)**

Run the same console check as Step 2. Expected: the same result, and no `h1.entry-title` or `.wp-block-post-title` in the DOM (`document.querySelector('h1.entry-title, .wp-block-post-title') === null`).

- [ ] **Step 4: Verify on Divi**

Run the same checks as Step 3. Expected: the same. If Divi wins on any property, add `!important` to that property only (see Step 1), then rerun Steps 2–4.

- [ ] **Step 5: Lint**

Run `composer lint -- includes/Renderer/CheckoutPage.php includes/Engine/Register/ScriptName.php`. Expected: no errors.

- [ ] **Step 6: Docs**

In `docs/architecture.md` § Storefront → Rendering, add one sentence: `CheckoutPage` enqueues `assets/css/checkout-page.css`, which removes the theme's horizontal margin, padding and max-width around the layout. It also resets the theme.json content size, wide size and root padding inside it, so only the layout's own blocks limit its width. No page title is printed.

- [ ] **Step 7: Commit**

Stage: `assets/css/checkout-page.css`, `includes/Engine/Register/ScriptName.php`, `includes/Renderer/CheckoutPage.php`, `docs/architecture.md`, `docs/plans/2026-10-07-full-width-checkout-layout.md`
Message: `feat(renderer): render the checkout layout full width on every theme`

### Task 2: Layout editor canvas is full width with no title

**Files:**
- Create: `includes/Engine/Admin/LayoutEditorCanvas.php`
- Modify: `includes/YayCheckout.php:24` (build it next to `LayoutEditorEmbed`)
- Modify: `docs/architecture.md:84` (the editor bullet)
- Create: `docs/backlog/2026-10-07-rename-layout-in-spa.md`

**Interfaces:**
- Consumes: `Models\Layout::POST_TYPE`
- Produces: `Engine\Admin\LayoutEditorCanvas` (SingletonTrait), filter callback `canvas_styles( array $settings, \WP_Block_Editor_Context $context ): array` on `block_editor_settings_all`

- [ ] **Step 1: Implement `LayoutEditorCanvas`**

Do this in the constructor: `add_filter( 'block_editor_settings_all', [ $this, 'canvas_styles' ], 10, 2 );`

`canvas_styles()` returns `$settings` unchanged unless `$context->post instanceof \WP_Post && Layout::POST_TYPE === $context->post->post_type`. In that case it appends `[ 'css' => self::CANVAS_CSS ]` to `$settings['styles']` (create the array if missing). Leave out `__unstableType => 'theme'`, so the style stays on when the "Use theme styles" preference is off. This applies to every layout edit, not just `yc-embed=1`, so the editor looks the same in and out of the modal.

`private const CANVAS_CSS` contains the following. Core prefixes editor styles with `.editor-styles-wrapper`, so write the selectors without it.
- `.is-root-container`: `--wp--style--global--content-size: 100%; --wp--style--global--wide-size: 100%; --wp--style--root--padding-left: 0px; --wp--style--root--padding-right: 0px; padding-left: 0; padding-right: 0;`
- `.block-editor-block-list__layout.is-root-container.is-root-container > :where(:not(.alignleft):not(.alignright):not(.alignfull)), … > .alignwide`: `max-width: none;`. Core prints the root container's content and wide sizes as literal pixels, so the variables alone don't reach them. The iframed canvas doesn't prefix editor styles and prints core's rule later, so the repeated class wins on specificity. (Found during Step 3.)
- `.editor-visual-editor__post-title-wrapper, .edit-post-visual-editor__post-title-wrapper`: `display: none;`

Docblock: what it does for the caller, plus the why. A layout is the whole checkout page, so the theme's content width doesn't apply. Its name is set when it is created, so the canvas shows no title field.

Wire it into `includes/YayCheckout.php` right after `\YayCheckout\Engine\Admin\LayoutEditorEmbed::get_instance();`.

- [ ] **Step 2: Lint**

Run `composer lint -- includes/Engine/Admin/LayoutEditorCanvas.php includes/YayCheckout.php`. Expected: no errors, and the Pro-boundary check passes.

- [ ] **Step 3: Verify the layout editor on TwentyTwentyFive**

Open a layout from the SPA (the editor modal). Expected:
- There is no "Add title" field.
- In the canvas iframe's console, `getComputedStyle(document.querySelector('.is-root-container')).paddingLeft === '0px'`.
- A top-level paragraph spans the full canvas width.
- A top-level constrained Group with no custom width is full width, and the default layout's 1200px Group is still 1200px.
- An `alignfull` block causes no horizontal scrollbar in the canvas.
- Saving the draft keeps the layout's name unchanged in the SPA list.

- [ ] **Step 4: Verify other editors are untouched**

Open `post-new.php?post_type=page`. Expected: the "Add title" field shows, and a paragraph is limited to the theme's content width (645px on TwentyTwentyFive).

- [ ] **Step 5: Docs and backlog**

In `docs/architecture.md`, add one sentence to the embedded-editor bullet: `Engine\Admin\LayoutEditorCanvas` gives layout posts a canvas style that resets the theme's content size, wide size and root padding and hides the title field. A layout's name is set when it is created and can't be changed after.

Create `docs/backlog/2026-10-07-rename-layout-in-spa.md` in the existing backlog format (Purpose, Update when, Source, Today, Why it matters, Suggested fix, Done when). The item: the editor no longer shows the title, so layouts can't be renamed. Done when the SPA layouts list has a Rename action that updates `post_title` through `LayoutRepository`, and a renamed layout shows its new name in the list and in search.

- [ ] **Step 6: Commit**

Stage: `includes/Engine/Admin/LayoutEditorCanvas.php`, `includes/YayCheckout.php`, `docs/architecture.md`, `docs/backlog/2026-10-07-rename-layout-in-spa.md`
Message: `feat(editor): full-width layout canvas without a title field`

### Task 3: End-to-end check

- [ ] **Step 1:** Run acceptance criterion 7 on TwentyTwentyFive: create a layout in the SPA, add a top-level constrained Group holding a Cover block, then publish and activate it. Expected: in the editor and at `/checkout/`, the Cover spans edge to edge, no title shows, and there is no horizontal scrollbar.
- [ ] **Step 2:** Run `./scripts/run.sh build`. Expected: it succeeds. Nothing in the SPA or blocks changed, so this only confirms the build still passes.
- [ ] **Step 3:** Set this plan's `Status:` to `done 2026-10-07` in the PR that finishes it.
