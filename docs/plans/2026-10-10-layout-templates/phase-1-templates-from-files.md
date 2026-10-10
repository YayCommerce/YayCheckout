# Phase 1: Templates from files

**Plan:** `README.md` in this directory, which holds the Status, constraints and interface contract
**Depends on:** nothing. Start by running `git switch -c trace/feat/layout-templates trace/docs/layout-templates`.
**Delivers:**
- Every new layout (Blank, From library, wizard) and every Reset gets a template's markup, design meta and slug.
- Templates without files show as Coming soon and are refused by the server.

**Exit criterion:**
- `composer test -- --filter LayoutTemplateCatalogueTest` passes.
- In the SPA, Layouts → New layout → From library shows Coming soon on every template except Classic two-column. Creating a Classic two-column layout and opening it in the editor shows a `#f0f0f1` canvas background.

`wp` commands below run in Local's site shell (Local → the site → Open site shell), from the WordPress root.

### Task 1: Template files and catalogue lookups

**Files:**
- Move: `templates/default-layout.php` → `templates/layouts/classic-two-column/markup.php` (`git mv`, content unchanged apart from the header comment)
- Create: `templates/layouts/classic-two-column/design.json`
- Modify: `includes/Controllers/LayoutTemplateCatalogue.php`
- Modify (mirror comments only): `packages/blocks/src/shared/address-template.ts:20,39`, `packages/blocks/src/shared/address-fields.ts:12`, `packages/blocks/src/order-summary/edit.tsx:18`, `.claude/rules/blocks.md:20`, `docs/conventions.md:23`
- Test: `tests/Integration/LayoutTemplateCatalogueTest.php`

**Interfaces:**
- Consumes: `LayoutDesign::from_meta()`, `LayoutDesign::schema()`, `LayoutMarkupReader::publish_error()`
- Produces: from the README contract, `DEFAULT_SLUG`, `templates()` with `available`, `is_available()`, `assert_available()`, `markup()` and `design()`

- [ ] **Step 1: Write the failing test** `tests/Integration/LayoutTemplateCatalogueTest.php`, namespace `YayCheckout\Tests\Integration`, class `LayoutTemplateCatalogueTest extends WP_UnitTestCase`:

```php
public function test_every_available_template_is_publishable_and_sets_every_section(): void {
    $available = array_filter( LayoutTemplateCatalogue::templates( true ), static fn( $t ) => $t['available'] );
    $this->assertContains( LayoutTemplateCatalogue::DEFAULT_SLUG, array_column( $available, 'slug' ) );

    foreach ( array_column( $available, 'slug' ) as $slug ) {
        $this->assertSame( '', LayoutMarkupReader::publish_error( LayoutTemplateCatalogue::markup( $slug ) ), $slug );

        $decoded = wp_json_file_decode( YAYCHECKOUT_PLUGIN_DIR . "templates/layouts/{$slug}/design.json", [ 'associative' => true ] );
        $this->assertIsArray( $decoded, $slug );
        foreach ( array_keys( LayoutDesign::schema() ) as $section ) {
            $this->assertIsArray( $decoded[ $section ] ?? null, "{$slug}: {$section}" );
        }
        // Same keys, order and types: nothing in the file was dropped or defaulted by the projection.
        $this->assertSame( $decoded, LayoutTemplateCatalogue::design( $slug )->to_meta(), $slug );
    }
}

public function test_a_template_without_files_is_coming_soon(): void {
    $available = array_column( LayoutTemplateCatalogue::templates( true ), 'available', 'slug' );
    $this->assertFalse( $available['split-express'] );

    $this->expectException( \InvalidArgumentException::class );
    $this->expectExceptionMessage( 'This template is coming soon.' );
    LayoutTemplateCatalogue::assert_available( 'split-express' );
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: FAIL. The first error is the undefined constant `DEFAULT_SLUG`, or an undefined index `available`.

- [ ] **Step 3: Move the default layout and add its design**
  - `git mv templates/default-layout.php templates/layouts/classic-two-column/markup.php`. Update only the file's header comment, so it names the template and keeps the "build in the editor, copy from the Code editor" instructions.
  - Create `design.json` with the spec's acceptance criterion 4 values, written in `LayoutDesign::schema()` key order (`colours`: `enabled, primary, text, background, border`; `typography`: `enabled, font_family, base_size`; `inputs`: `enabled, style, size, radius`; then `custom_css`), so step 1's `assertSame` holds.

- [ ] **Step 4: Implement the catalogue lookups in `LayoutTemplateCatalogue`**
  - `public const DEFAULT_SLUG = 'classic-two-column';`
  - `templates( bool $is_pro ): array` sets `available` on each entry from `is_available()`.
  - `is_available( string $slug ): bool`.
  - `assert_available()` checks in order: unknown, then locked, then unavailable (`__( 'This template is coming soon.', 'yaycheckout' )`).
  - `markup( string $slug ): string` includes `markup.php` inside `ob_start()`/`ob_get_clean()`, as `default_markup()` did.
  - `design( string $slug ): LayoutDesign` uses `LayoutDesign::from_meta( wp_json_file_decode( $path, [ 'associative' => true ] ) )`.
  - A private `dir( string $slug ): string` returns `YAYCHECKOUT_PLUGIN_DIR . 'templates/layouts/' . $slug . '/'`. Only `is_available()`, `markup()` and `design()` call it, each after confirming `$slug` is in `array_column( self::catalogue(), 'slug' )`.
  - Remove the `TODO(template-source)` line, and update the class docblock to say that templates live in `templates/layouts/<slug>/`.

- [ ] **Step 5: Run the test to confirm it passes**

Run: `composer test -- --filter LayoutTemplateCatalogueTest`
Expected: PASS (2 tests). Until Task 2 lands, `LayoutRepository::default_markup()` still points at the old path, so don't create layouts between this step and Task 2.

- [ ] **Step 6: Verify the slug guard and the check order**
  - Run: `wp eval 'try { YayCheckout\Controllers\LayoutTemplateCatalogue::markup( "../../../yaycheckout" ); echo "INCLUDED"; } catch ( InvalidArgumentException $e ) { echo "refused"; }'`
    Expected: `refused`
  - Read `assert_available()`: the locked check comes before the coming-soon check.

- [ ] **Step 7: Retarget the mirror comments.** In each "Modify (mirror comments only)" file above, replace `templates/default-layout.php` with `templates/layouts/classic-two-column/markup.php`.
  Run: `git grep -n "default-layout" -- . ':!docs/plans' ':!docs/specs'`
  Expected: only `docs/architecture.md` and `includes/Repositories/LayoutRepository.php` remain (Task 2 handles both).

- [ ] **Step 8: Lint**
  Run: `composer lint -- includes/Controllers/LayoutTemplateCatalogue.php templates/layouts/classic-two-column/markup.php tests/Integration/LayoutTemplateCatalogueTest.php`
  Expected: no errors; the boundary check passes.

### Task 2: Store the template on layouts, and create and reset from it

**Files:**
- Modify: `includes/Models/Layout.php:18-20` (constant), `:55-70` (`from_post()` hydrates `$template`)
- Modify: `includes/Repositories/LayoutRepository.php`:
  - `create()` (`:349`)
  - `duplicate()` (`:358`)
  - `import()` (`:376`)
  - `reset()` (`:386`)
  - `insert_draft()` (`:443`)
  - remove `default_markup()` (`:503-511`)
- Modify: `includes/Controllers/LayoutRestController.php:297-311` (`create_draft()`), `:425` (the `reset_layout()` docblock)
- Modify: `includes/Controllers/SetupRestController.php:75` (call site only)
- Modify: `docs/architecture.md:41,46` (the template and reset sentences; the wizard part is Phase 2's), and the writers line `:39` (`LayoutRepository` writes `_yaycheckout_template`)

**Interfaces:**
- Consumes: Task 1's `DEFAULT_SLUG`, `is_available()`, `assert_available()`, `markup()` and `design()`
- Produces: from the README contract, `Layout::META_TEMPLATE`, `Layout::$template`, `create( $title, $template_slug )`, the new `reset()`, and the removal of `default_markup()`

- [ ] **Step 1: Add `Layout::META_TEMPLATE = '_yaycheckout_template'` and `public string $template = ''`.** Hydrate it in `from_post()` with `(string) self::meta_value( $meta, self::META_TEMPLATE, '' )`. Leave `to_array()` and `to_summary_array()` unchanged.

- [ ] **Step 2: Change the insert path**
  - `insert_draft( string $title, string $content, array $rules, LayoutDesign $design, string $template_slug )` adds `META_TEMPLATE => $template_slug` to `$meta` when it isn't `''`.
  - `create( string $title, string $template_slug ): Layout` calls `insert_draft( $title, LayoutTemplateCatalogue::markup( $template_slug ), [], LayoutTemplateCatalogue::design( $template_slug ), $template_slug )`.
  - `duplicate()` passes `$source->template`.
  - `import()` passes `''`.
  - Delete `default_markup()`.

- [ ] **Step 3: Rewrite `reset( int $id ): Layout`**
  - Pick `$slug`: the layout's `template` when `LayoutTemplateCatalogue::is_available()` says so, else `DEFAULT_SLUG`. There is no lock check.
  - Keep `wp_save_post_revision()` and the slashed `wp_update_post()` with `post_content => markup( $slug )`.
  - Then call `update_post_meta( $id, Layout::META_DESIGN, LayoutTemplateCatalogue::design( $slug )->to_meta() )`.
  - Leave `META_TEMPLATE` as it is.
  - Docblock: "Puts a layout's blocks and design back to its template's…; design meta has no revisions."

- [ ] **Step 4: Point the controllers at the slug**
  - `create_draft()`: `$slug = 'library' === $source ? $template_slug : LayoutTemplateCatalogue::DEFAULT_SLUG;`. Call `assert_available( $slug )` for both sources, so a Blank on a broken install fails cleanly, then `create( $name, $slug )`. Update its docblock.
  - `SetupRestController::complete_setup()`: `create( __( 'Checkout — draft', 'yaycheckout' ), (string) $request->get_param( 'template_slug' ) )`. Nothing else changes until Phase 2.

- [ ] **Step 5: Verify create, duplicate and reset**
  The Free cap applies, so archive spare layouts first if the site holds 3.
  1. Run: `wp eval 'wp_set_current_user( 1 ); $r = rest_do_request( ( new WP_REST_Request( "POST", "/yaycheckout/v1/layouts" ) )->set_body_params( [ "name" => "T blank", "source" => "blank" ] ) ); echo $r->get_data()["id"];'`
     Then run `wp post meta get <id> _yaycheckout_template` and `wp post meta get <id> _yaycheckout_design --format=json`.
     Expected: `classic-two-column`, and JSON whose `colours.background` is `#f0f0f1` and `inputs.radius` is `2`.
  2. The same request with `"source" => "library", "template_slug" => "split-express"`.
     Expected: status 400, message `This template is coming soon.`, and `wp post list --post_type=yay_checkout_layout --format=count` unchanged.
  3. Edit layout `<id>`: `wp post meta update <id> _yaycheckout_design '{"colours":null,"typography":null,"inputs":null,"custom_css":"x"}' --format=json` and `wp post update <id> --post_content='<!-- wp:yaycheckout/checkout /-->'`. Then POST `/yaycheckout/v1/layouts/<id>/reset`.
     Expected: `post_content` equals `LayoutTemplateCatalogue::markup( "classic-two-column" )` (check with `wp eval`, compare with `===`, expect `1`), `_yaycheckout_design` is back to the step 1 JSON, and `wp post list --post_type=revision --post_parent=<id>` lists the one-block revision.
  4. Run `wp post meta delete <id> _yaycheckout_template`, then reset again.
     Expected: the same result as 3. Then run `wp post meta update <id> _yaycheckout_template split-express` and reset: again the same result.
  5. Duplicate `<id>` (`POST /yaycheckout/v1/layouts/<id>/duplicate`, after restoring the slug to `classic-two-column`).
     Expected: the copy's `_yaycheckout_template` is `classic-two-column`.
  6. Read `reset()`: it calls no `assert_available()` and no `Plan::` method, which covers the Pro template on a Free site.

- [ ] **Step 6: Update `docs/architecture.md`**
  - `:46`: new layouts start from a catalogue template's `markup.php` and `design.json`; Blank is `DEFAULT_SLUG`; Reset restores the template named by `Layout::META_TEMPLATE` (or the default), rewriting the design meta, which has no revisions.
  - `:41`: drop "but the draft is `LayoutRepository::default_markup()` whatever the template".
  - `:39`: add `_yaycheckout_template` to `LayoutRepository`'s writes.

  Run: `git grep -n "default_markup\|default-layout" -- . ':!docs/plans' ':!docs/specs'`
  Expected: no output.

- [ ] **Step 7: Lint and test**
  Run: `composer lint -- includes/Models/Layout.php includes/Repositories/LayoutRepository.php includes/Controllers/LayoutRestController.php includes/Controllers/SetupRestController.php`, then `composer test`.
  Expected: no lint errors; every test passes.

### Task 3: SPA: Coming soon in the picker, and Reset copy

**Files:**
- Modify: `apps/admin/src/lib/schema/template.schema.ts` (add `available: z.boolean()`)
- Modify: `apps/admin/src/components/shared/template-picker/TemplatePicker.tsx:97-120` and the Free grid `:130-175`
- Modify: `apps/admin/src/components/setup-wizard/StartFromStep.tsx:26` (default pick)
- Modify: `apps/admin/src/pages/tools/components/ResetCard.tsx:17-22`, `apps/admin/src/pages/tools/components/ResetModal.tsx:60-74`

**Interfaces:**
- Consumes: the wire `available` field (Task 1)
- Produces: `Template.available: boolean`

- [ ] **Step 1: Add `available` to `templateSchema`.** No `.api.ts` conversion is needed: the field is a single word, the same in snake_case and camelCase.

- [ ] **Step 2: Show Coming soon in `TemplatePicker`**
  - A tile is disabled when `template.locked || !template.available`.
  - A tile that is unavailable but not locked shows `<Badge variant="dimmed-outline">{__('Coming soon', 'yaycheckout')}</Badge>` where `ProTag` sits, with the same `cursor-not-allowed opacity-50` classes and `title={__('This template is coming soon', 'yaycheckout')}`.
  - A locked tile keeps `ProTag`. Locked takes precedence when both apply.
  - On Free, unavailable-but-unlocked templates stay in the main grid, not in the locked strip.

- [ ] **Step 3: Default pick.** In `StartFromStep`, the wizard's initial pick is `data?.templates.find((t) => !t.locked && t.available)`. Rename the variable to `firstPickable`.

- [ ] **Step 4: Reset copy**
  - `ResetCard` title: `Reset to its template`. Description: "Replaces the selected layout's blocks and design with the template it was created from."
  - `ResetModal` title: `Reset "%s" to its template?` (keep the translators comment). Body: "Every block you added is removed and replaced with the layout's template. Your design changes for this layout will be lost. Revisions keep the old blocks, so you can roll back the blocks from the editor's history."

- [ ] **Step 5: Type-check and lint**
  Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
  Expected: exit 0.
  Run: `pnpm --filter @yaycheckout/admin-page lint`
  Expected: only the failures listed in Global constraints.

- [ ] **Step 6: Verify in the admin** (`./scripts/run.sh dev` running)
  1. Open `/wp-admin/admin.php?page=yaycheckout`, go to Layouts → New layout → From library.
     Expected: Classic two-column is selectable; the other 10 show Coming soon (or the Pro tag where locked on a Free build) and can't be selected.
  2. Create "T lib" from Classic two-column and open it in the editor.
     Expected: the canvas background is `#f0f0f1`, there's no invalid-block warning, and the editor's design sidebar shows Colours, Typography and Inputs overridden.
  3. Tools → Reset.
     Expected: the new title and copy.

- [ ] **Step 7: Commit**
  Stage:
  - `templates/default-layout.php` (deleted)
  - `templates/layouts/classic-two-column/markup.php`
  - `templates/layouts/classic-two-column/design.json`
  - `includes/Controllers/LayoutTemplateCatalogue.php`
  - `includes/Models/Layout.php`
  - `includes/Repositories/LayoutRepository.php`
  - `includes/Controllers/LayoutRestController.php`
  - `includes/Controllers/SetupRestController.php`
  - `tests/Integration/LayoutTemplateCatalogueTest.php`
  - the SPA files from Task 3
  - the mirror-comment files from Task 1
  - `docs/architecture.md`
  - `docs/conventions.md`
  - `.claude/rules/blocks.md`

  Message: `feat(templates): create and reset layouts from template files`
