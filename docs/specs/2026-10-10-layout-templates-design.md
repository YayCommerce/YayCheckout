# Layout Templates Design

**Status:** accepted 2026-10-10
**Related:** `~/Downloads/WooCommerce Checkout Templates_v1.1.html` (the template mocks; template 01 is "Classic two-column"); `TODO(template-source)` in `includes/Controllers/LayoutTemplateCatalogue.php`

## Goal

A layout created from a template gets that template's own block markup and its own look, stored in the layout's design meta, so the result matches the template the merchant picked. Template 01 (Classic two-column) replaces today's default layout and is rebuilt to match its mock as far as the existing blocks allow.

## Context

- `LayoutTemplateCatalogue::catalogue()` is a fixture list of 11 templates (slug, name, category) with no markup. Free unlocks the first 3 (`FREE_UNLOCKED_COUNT`); `assert_available()` refuses an unknown or locked slug.
- Every creation path writes the same markup, `LayoutRepository::default_markup()`, which includes `templates/default-layout.php`:
  - `POST /layouts` (`LayoutRestController::create_draft()`), for both `source: blank` and `source: library`;
  - `POST /setup` (`SetupRestController::complete_setup()`), which also saves the wizard's Brand step to the global `yaycheckout_design` option;
  - `POST /layouts/{id}/reset` (`LayoutRepository::reset()`), which replaces `post_content` and leaves meta alone.
- `LayoutRepository::insert_draft()` is the one insert path; it writes `Layout::META_DESIGN` (`_yaycheckout_design`, a `Models\LayoutDesign`) with the post. `create()` passes `LayoutDesign::none()`.
- `LayoutDesign::to_meta()` is the meta, wire and export shape: per section of `LayoutDesign::schema()` (`colours`, `typography`, `inputs`) a full token array or `null`, plus `custom_css`. `LayoutDesign::from_meta()` projects any value through the schema.
- SPA: `NewLayoutModal` (source, then the library step), the shared `TemplatePicker` and `TemplateThumbnail` sketches, `StartFromStep` and `BrandStep` in `components/setup-wizard/`, and Tools' `ResetCard` and `ResetModal`.
- Mirrors that name `templates/default-layout.php`: `packages/blocks/src/shared/address-template.ts`, `packages/blocks/src/shared/address-fields.ts`, `packages/blocks/src/order-summary/edit.tsx`, `.claude/rules/blocks.md`, `docs/conventions.md`, `docs/architecture.md`.
- The plugin is unreleased (1.0.0, no release tag), so no stored data needs migrating.

## Scope

**In:**
- A per-template directory holding the markup and the design.
- A template is available only when both of its files exist; otherwise the merchant sees it as Coming soon and the server refuses it.
- Creating from the library, from Blank and from the setup wizard writes the template's markup, design and slug.
- A new `_yaycheckout_template` meta records which template a layout came from; Duplicate copies it.
- Reset restores the layout's own template: its markup and its design.
- The setup wizard's Brand step is removed, and `POST /setup` no longer writes the global design.
- Template 01's markup is rebuilt to match its mock with existing blocks, and it gets its own `design.json`.
- One integration test over the catalogue.
- Doc and mirror-comment updates for the moved file.

**Out:**
- Removing the global Design (the Design page, `yaycheckout_design`, `DesignRepository`, `DesignStyles`' global layer). That gets its own spec, because it reverses `docs/decisions/2026-10-09-layout-design-override-layer.md`. Until it lands, the global design still prints under each layout's override.
- Markup or design for templates 02–11. They stay Coming soon.
- Any new block or block option that template 01's mock needs:
  - shipping-first with "Billing address is the same as shipping";
  - the "N items" count;
  - the "USD" currency label;
  - card-brand icons;
  - delivery-time lines on shipping rates;
  - the lock icon;
  - the applied-coupon chip;
  - a subtitle under the Payment heading;
  - Refund and Shipping policy links.
- Carrying `_yaycheckout_template` in Pro's export file and import. An imported layout has no slug.
- Changing the New layout modal's Blank/From library choice, or the thumbnail sketches.
- Changing `Design::schema()` defaults.

## Acceptance criteria

1. Given `templates/layouts/classic-two-column/markup.php` and `design.json` both exist, when the SPA requests `GET /layouts/templates`, then the `classic-two-column` entry has `"available": true`, and every slug without both files has `"available": false`.
2. Given a merchant on any plan, when they `POST /layouts` with `source: library` and `template_slug` set to an unavailable slug (e.g. `split-express`), then the response is a 400 with the message "This template is coming soon." and no layout is created. A locked slug still gets "This template is part of YayCheckout Pro.", and the lock check runs first.
3. When a merchant creates a layout with `source: blank`, or with `source: library` and `template_slug: classic-two-column`, then the new draft's `post_content` equals the output of `classic-two-column/markup.php`, its `_yaycheckout_design` equals `LayoutDesign::from_meta( <classic-two-column/design.json> )->to_meta()`, and its `_yaycheckout_template` is `classic-two-column`.
4. Template 01's `design.json` sets every section, so a layout created from it has no `null` section:
   - `colours`: `enabled: true`, `primary: "#3858e9"`, `text: "#1e1e1e"`, `background: "#f0f0f1"`, `border: "#dddddd"`
   - `typography`: `enabled: true`, `font_family: ""`, `base_size: 13`
   - `inputs`: `enabled: true`, `style: "boxed"`, `size: "medium"`, `radius: 2`
   - `custom_css: ""`
5. When the setup wizard runs, its steps are Scan and Start from, then Finish; there is no Brand step. Start from's button sends `POST /setup` with only `template_slug` and `rerun`. The response is `{ "layout": … }` with no `design` key. `yaycheckout_design` is unchanged, and the created layout carries the picked template's markup, design and slug.
6. In the template picker (the New layout modal's library step and the wizard's Start from step), an unavailable template shows a "Coming soon" badge and cannot be selected, and the wizard's initial pick is the first template that is both unlocked and available.
7. Given a layout with `_yaycheckout_template: classic-two-column` whose blocks and design were edited, when the merchant resets it from Tools, then its `post_content` and `_yaycheckout_design` equal a fresh `classic-two-column` layout's, while its title, status, active flag and rules stay as they were. The old markup is kept as a revision. The confirm modal shows "Your design changes for this layout will be lost."
8. Given a layout with no `_yaycheckout_template`, or one naming a slug that is no longer available, when it is reset, then it gets `classic-two-column`'s markup and design. Reset ignores the plan lock, so a Pro template's layout on a downgraded Free site resets to its own template.
9. When a layout is duplicated, the copy has the source's `_yaycheckout_template`.
10. A layout created from `classic-two-column` passes `LayoutMarkupReader::publish_error()` (returns no error), opens in the editor with no block flagged invalid, and renders on the storefront with the structure listed under **UI → Template 01 markup**.
11. `composer test` runs `tests/Integration/LayoutTemplateCatalogueTest.php`, and it passes. For every available template, the test checks that:
    - the printed markup passes `publish_error()`;
    - `design.json` decodes to an array with every section of `LayoutDesign::schema()` present and non-null;
    - every token in it survives `LayoutDesign::from_meta()` unchanged.
12. End-to-end verification:
    - On a site with setup not done, run the setup wizard, pick Classic two-column and apply.
    - Open the created layout in the editor: no block is invalid, and the canvas shows a `#f0f0f1` page with the white summary card.
    - Activate it and load the checkout as a shopper with a product in the cart: the page matches the template 01 mock's structure, with billing before shipping and the items listed under Out absent. Place an order with a test gateway, and it succeeds.
    - Back in wp-admin, change the layout's primary colour in the editor sidebar, then reset it from Tools: the colour returns to `#3858e9`.
    - In the New layout modal's library, every template except Classic two-column shows Coming soon.

## Constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`; everything here is Free code under `includes/` and must pass `scripts/check-pro-boundary.sh`.
- The meta key is a class constant: `Layout::META_TEMPLATE = '_yaycheckout_template'`. Like `Layout::META_RULES`, it is not registered with `register_post_meta()`, so it is not exposed on wp/v2 and only `LayoutRepository` writes it.
- Everything on the wire is snake_case (`available`, `template_slug`); the SPA converts in `apps/admin/src/lib/api/` and parses with strict wire schemas.
- `markup.php` is written exactly as the block editor saves it. PHP appears only for translated strings (`esc_html_e`), the logo-or-title choice and conditional links, and every new string uses the `yaycheckout` text domain.
- Every insert goes through `LayoutRepository::insert_draft()`, so the Free layout cap (`LayoutRepository::FREE_LIMIT = 3`) and the slashing still apply.
- The plan lock stays in `LayoutTemplateCatalogue` alone; `Plan::is_pro()` withholds Pro templates server-side.
- Never hand-edit `assets/dist/`; rebuild with `pnpm build:blocks` / `./scripts/run.sh build`.

## Approach

**Chosen:** a directory per template under `templates/layouts/<slug>/`, holding `markup.php` and `design.json`.

**Architecture:** The catalogue still owns the ordered list of templates (slug, name, category) and the Free lock rule. It also answers which templates exist on disk and returns their markup and design. A template is available when both files exist. Creation paths pass a slug to `LayoutRepository`, which asks the catalogue for the markup and design and stores the slug in `_yaycheckout_template`. Reset reads that slug back and rewrites both. A template's look lives only in the layout's `_yaycheckout_design`, with every section set, so it doesn't depend on the global design that a later spec removes.

**Components:**
- `templates/layouts/classic-two-column/markup.php`: template 01's block markup. It replaces `templates/default-layout.php`, which is deleted.
- `templates/layouts/classic-two-column/design.json`: template 01's `LayoutDesign::to_meta()` (criterion 4).
- `includes/Controllers/LayoutTemplateCatalogue.php`:
  - `DEFAULT_SLUG = 'classic-two-column'`;
  - `templates()` adds `available` to each entry;
  - `assert_available()` adds the Coming soon refusal after the lock check;
  - `is_available( string $slug ): bool`;
  - `markup( string $slug ): string`, which includes `markup.php` into an output buffer;
  - `design( string $slug ): LayoutDesign`, which reads `design.json` through `LayoutDesign::from_meta()`.

  `markup()` and `design()` throw `\InvalidArgumentException` for a slug that isn't available. The file-level `TODO(template-source)` is removed.
- `includes/Models/Layout.php`: `META_TEMPLATE` constant; `Layout::$template` (string, `''` when unset) hydrated from meta. It isn't added to `to_array()`, because no screen reads it.
- `includes/Repositories/LayoutRepository.php`:
  - `create( string $title, string $template_slug )` replaces `create( $title )`; `default_markup()` is removed;
  - `insert_draft()` gains a `string $template_slug` argument that it writes as `META_TEMPLATE` (skipped when `''`);
  - `duplicate()` passes the source's slug, and `import()` passes `''`;
  - `reset()` picks the stored slug if it is available, else `DEFAULT_SLUG`, then writes the markup through `wp_update_post()` and the design through `update_post_meta( META_DESIGN, … )`. The revision is saved first, as today.
- `includes/Controllers/LayoutRestController.php`: `create_draft()` maps `blank` to `DEFAULT_SLUG` and `library` to `template_slug` (after `assert_available()`), then calls `create( $name, $slug )`. The `reset_layout()` docblock now says "its template".
- `includes/Controllers/SetupRestController.php`:
  - drops the `design` arg, `DesignRepository` and `$this->design`;
  - calls `create( __( 'Checkout — draft', 'yaycheckout' ), $slug )`;
  - returns `[ 'layout' => … ]`.
- SPA:
  - **Wizard:**
    - delete `components/setup-wizard/BrandStep.tsx`;
    - in `SetupWizard.tsx`, set `STEPS` to Scan and Start from, and remove the design query, the form and `SetupWizardWithDesign`'s design gate;
    - `StartFromStep` (now the last step) carries the Apply button.
  - **Setup API:** `lib/api/setup.api.ts` drops `form` and sends `{ template_slug, rerun }`; `lib/schema/setup.schema.ts` drops `design`; `SetupResult` is `{ layout }`.
  - **Templates:**
    - `lib/schema/template.schema.ts` adds `available: z.boolean()`;
    - `TemplatePicker` shows a "Coming soon" badge and disables an entry when `!available`, following how it treats `locked`;
    - `StartFromStep`'s default pick requires `!locked && available`.
  - **Tools:** `ResetCard` title "Reset to its template"; `ResetModal` heading `Reset "%s" to its template?` with the line "Your design changes for this layout will be lost."
- `tests/Integration/LayoutTemplateCatalogueTest.php` (suite `Integration`): criterion 11.

**Data and storage:**
- New post meta `_yaycheckout_template` (`Layout::META_TEMPLATE`): a catalogue slug, written on insert. Missing on layouts created before this change and on imported layouts, where Reset falls back to `DEFAULT_SLUG`.
- `_yaycheckout_design` is now written with a full design for every templated layout, and rewritten by Reset.
- `yaycheckout_design` is no longer written by setup. Its other writers (the Design page) stay until the removal spec.
- No migration (unreleased). On uninstall, the meta goes with the layout posts, as `_yaycheckout_design` does today.

**Integration points:**
- REST `GET /yaycheckout/v1/layouts/templates`: each entry gains `available`.
- REST `POST /yaycheckout/v1/layouts`: unchanged args, plus the new Coming soon refusal.
- REST `POST /yaycheckout/v1/setup`: drops `design`, and the response drops `design`.
- REST `POST /yaycheckout/v1/layouts/{id}/reset`: now also rewrites `_yaycheckout_design`.
- No new hooks or filters.
- Mirror comments to retarget from `templates/default-layout.php` to `templates/layouts/classic-two-column/markup.php`:
  - `packages/blocks/src/shared/address-template.ts`
  - `packages/blocks/src/shared/address-fields.ts`
  - `packages/blocks/src/order-summary/edit.tsx`
  - `.claude/rules/blocks.md`
  - `docs/conventions.md`
- Also update `docs/architecture.md`: the setup bullet, the new-layout bullet and the writers list.

**UI → Template 01 markup** (`classic-two-column/markup.php`, top to bottom):
- **Header:** a full-width `header` Group (background `#ffffff`, bottom border `1px solid #dddddd`). Inside it, a constrained (1120px) flex row, space-between, 64px tall, 24px side padding:
  - Site Logo if `has_custom_logo()`, else Site Title;
  - a paragraph (`#757575`) "Cart › **Checkout** › Order received", with Cart linking to `wc_get_cart_url()`;
  - a paragraph "Secure checkout" in `#2f8a48`, weight 500.
- **Main:** a constrained Group (1120px; padding 40px top, 24px sides, 80px bottom) holding `yaycheckout/checkout`, which holds Columns with a 40px gap:
  - **Column 60%**, block gap 40px:
    1. `yaycheckout/express-pay` (hides itself without a wallet gateway).
    2. A Row Group (space-between, wrap) holding a Heading h2 "Contact information" (20px, weight 400) and `yaycheckout/login` with text "Have an account? Log in" and link text "Log in".
    3. `yaycheckout/email`: placeholder `you@example.com`, help text "You are currently checking out as a guest."
    4. `yaycheckout/billing`, title "Billing address", with today's default fields and their arrangement.
    5. `yaycheckout/shipping`, title "Shipping address", with the same fields.
    6. `yaycheckout/shipping-methods`, label "Shipping options".
    7. `yaycheckout/payment`, label "Payment options".
    8. `yaycheckout/order-note`: `disclosure: "checkbox"`, `disclosureText: "Add a note to your order"`, placeholder "Notes about your order."
    9. A Group (top border `1px solid #dddddd`, padding-top 24px, block gap 16px) holding:
       - a small paragraph (`#757575`): "By proceeding with your purchase you agree to our Terms and Conditions and Privacy Policy." Each link prints only when its page exists (`wc_terms_and_conditions_page_id() > 0`, `get_privacy_policy_url()`), and the sentence reads correctly with either link missing;
       - `yaycheckout/place-order` with `showTotal: true`;
       - `yaycheckout/return-to-cart` with `arrow: "chevron"`.
  - **Column 40%:** a Group with `position: sticky; top: 24px` holding:
    1. a card Group (background `#ffffff`, border `1px solid #dddddd`, radius 4px, padding 24px) holding `yaycheckout/order-summary` (label "Order summary"), which holds:
       - `order-summary-items` with `quantityDisplay: "badge"` and `showLabels: false`;
       - `coupon-form` with `disclosure: "collapsible"` and `disclosureText: "Add coupons"`, holding `coupon-button`;
       - `order-summary-totals`.
    2. A centred small paragraph (`#757575`, margin-top 16px) with Privacy and Terms links, each printed only when its page exists, and nothing printed when neither does.
- **No footer:** today's copyright and link footer is removed.

**UI → admin:** described under Components (wizard, template picker, Tools reset copy).

**Edge cases and failure modes:**
- `design.json` missing or invalid JSON: the template is unavailable, so it is never created from. Invalid tokens inside valid JSON read as their schema defaults (`from_meta()`), and the integration test fails on any such token.
- A layout's stored slug is no longer available, or it has no slug (old, imported): Reset uses `DEFAULT_SLUG`.
- A Free site with a layout made from a Pro template (after a downgrade): Reset still restores that template, since the lock only governs creation.
- The Free cap is reached: creation refuses before any template work, as today.
- No logo: Site Title prints. No terms or privacy page: those links are left out, and the sentences still read correctly.
- `markup()` includes PHP that calls `wc_get_cart_url()` and `wc_terms_and_conditions_page_id()`. Every caller already runs with WooCommerce loaded (REST, setup), as `default_markup()` did.
- Two overlapping `POST /setup` requests can still both create a draft, as today.

**Decision record:** None. This moves template content from a fixture to files, behind the same catalogue owner, and reverses no accepted record. The global-Design removal spec will add one.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Design held in the catalogue's PHP array next to the slug | You chose to keep each template's data beside its markup. |
| Register templates as core block patterns | Patterns can't carry layout meta or the plan lock, so the lock would need a second gate. |
| Template 01 design `null`, with new `Design::schema()` defaults matching it | Superseded when you decided to drop the global design: templates carry only their own meta. |
| Seed the wizard's Brand step from the picked template | The Brand step is removed. |
| Reset restores only markup, never design | You chose to restore both, with a warning, so Reset really returns to the template. |
| Hide unbuilt templates, or keep falling back to template 01 | Hiding makes the library look empty; falling back creates a layout that doesn't match the thumbnail the merchant picked. |
| Move `default-layout.php` unchanged | You asked for template 01 to match its mock as far as existing blocks allow. |

## Rollout

Unreleased plugin, so no upgrade path is needed. Dev sites with existing layouts keep their markup and design. Their layouts have no `_yaycheckout_template`, so a Reset there gives Classic two-column's markup and design. A dev site that ran the old wizard keeps its saved `yaycheckout_design`.

## Open questions

None.
