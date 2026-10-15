# Export and Import Carry the Logged-out View: Implementation Plan

> **For agentic workers:** do the tasks in order, one at a time. Steps use `- [ ]` for tracking. The source is the backlog item `docs/backlog/2026-10-15-export-import-drops-logged-out-view.md`.

**Status:** done 2026-10-15
**Goal:** A layout's logged-out view (`Layout::META_LOGGED_OUT`) survives Pro export and import, through both the REST endpoints and the SPA Tools page.
**Spec:** none; planned from the backlog item above.
**Architecture:** Add one `logged_out_content` string next to `title`, `content`, `rules` and `design` in each exported layout. `LayoutImportReview::layout()` projects it (non-string or missing → `''`). `LayoutRepository::import()` passes it to `insert_draft()`, which already writes the meta when the value isn't `''`. The editor's in-place Import still replaces only the open view's blocks, so `review_layout()` and the editor's `ImportReview` type stay as they are.
**Touches:**
- `includes/Pro/Controllers/LayoutExportRestController.php`
- `includes/Pro/Services/LayoutImportReview.php`
- `includes/Pro/Controllers/LayoutImportRestController.php`
- `includes/Repositories/LayoutRepository.php` (shared: `import()` has one caller, `LayoutImportRestController::import_layout()`)
- `apps/admin/src/lib/schema/tools.schema.ts`
- `docs/architecture.md`, the backlog file (deleted)

**Tests:** `tests/Integration/Pro/LayoutImportReviewTest.php` (Integration suite) guards that `LayoutImportReview::from_envelope()` returns the file's `logged_out_content` verbatim, and returns `''` when the key is missing or isn't a string.

## Requirements

- **Goal:** A merchant who exports a layout and imports the file on another store gets the same logged-out view.
- **Out of scope:** the editor's Import / Export panel (it stays hidden in the logged-out view, and its import ignores the key), reset to template, and revisions.
- **Acceptance criteria:**
  1. `GET /yaycheckout/v1/layouts/{id}/export` returns `layouts[0].logged_out_content`, equal to `Layout::$logged_out_content`. That is `''` for a layout with no logged-out view.
  2. `POST /layouts/import` with that file creates a draft whose `_yaycheckout_logged_out_content` meta equals the file's value. For a file with no key, or with `''`, the draft has no such meta.
  3. A non-string `logged_out_content` imports as `''`. It doesn't stop the import.
  4. The SPA Tools page exports and imports both a current file (one with `design` and `logged_out_content`) and an older file without those keys, with no Zod error.
  5. End to end: save a logged-out view on layout A. Export it from the Tools page and import the file. The new draft's preview with `yc-view=logged-out` shows A's logged-out blocks.
- **Decided with the user:**
  - `layoutExportWireSchema` is `.strict()` and has lacked `design` since #66, so the Tools page probably rejects every export today. This plan adds `design` too.
  - Add the `LayoutImportReview` test.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. The changes stay in `includes/Pro/`, plus a parameter on the Free `LayoutRepository::import()`, which doesn't name Pro.
- Wire keys are snake_case, and the export file is sent back verbatim (no camelCase projection).
- Conventional Commits. Each commit ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Stage files by name.

## Review focus

- An older export with no `logged_out_content` key must import exactly as it does today, writing no meta. (Task 1 test, plus Step 5's second check.)
- A hand-edited file with `"logged_out_content": 5` or `null` must import, as `''`. (Task 1 test.)
- The SPA's strict schema must accept files with and without `design` and `logged_out_content`. (Task 2 Step 2.)

---

### Task 1: PHP export and import carry `logged_out_content`

**Files:**
- Test: `tests/Integration/Pro/LayoutImportReviewTest.php` (namespace `YayCheckout\Tests\Integration\Pro`, extends `WP_UnitTestCase`, styled like `tests/Integration/LayoutTemplateCatalogueTest.php`)
- Modify: `includes/Pro/Services/LayoutImportReview.php`, `includes/Pro/Controllers/LayoutExportRestController.php`, `includes/Repositories/LayoutRepository.php:362-371`, `includes/Pro/Controllers/LayoutImportRestController.php:95`

**Interfaces:**
- Produces: `LayoutImportReview::from_envelope()` returns `{ title, content, rules, design, logged_out_content: string }`. `LayoutRepository::import( string $title, string $content, array $rules, LayoutDesign $design, string $logged_out_content = '' ): Layout`. Export entries add `'logged_out_content' => $layout->logged_out_content`.

- [x] **Step 1: Write the failing test**

```php
private static function envelope( array $layout ): array {
    return [ 'format' => Layout::EXPORT_FORMAT, 'plugin_version' => '1', 'exported_at' => '2026-10-15T00:00:00Z',
             'layouts' => [ array_merge( [ 'title' => 'T', 'content' => '<!-- wp:yaycheckout/checkout /-->' ], $layout ) ] ];
}

public function test_logged_out_content_is_kept_verbatim(): void {
    $markup = '<!-- wp:yaycheckout/checkout --><!-- wp:yaycheckout/login /--><!-- /wp:yaycheckout/checkout -->';
    $this->assertSame( $markup, LayoutImportReview::from_envelope( self::envelope( [ 'logged_out_content' => $markup ] ) )['logged_out_content'] );
}

public function test_missing_or_non_string_logged_out_content_reads_as_empty(): void {
    foreach ( [ [], [ 'logged_out_content' => null ], [ 'logged_out_content' => 5 ], [ 'logged_out_content' => [ 'x' ] ] ] as $extra ) {
        $this->assertSame( '', LayoutImportReview::from_envelope( self::envelope( $extra ) )['logged_out_content'] );
    }
}
```

Use the exact block markup that `LayoutMarkupReader::has_checkout()` accepts. If `<!-- wp:yaycheckout/checkout /-->` doesn't pass, copy the markup from `tests/Support/LayoutFactory.php`.
Run: `composer test -- --filter LayoutImportReviewTest`. Expected: both tests fail on an undefined `logged_out_content` index.

- [x] **Step 2: Project `logged_out_content` in `LayoutImportReview::layout()`.** Add `'logged_out_content' => is_string( $layout['logged_out_content'] ?? null ) ? $layout['logged_out_content'] : ''`. Update the `@return` shapes of `from_envelope()` and `layout()`. The value is verbatim like `content`: no `has_checkout()` check, because the logged-out view takes no order.

- [x] **Step 3: Run the test.** `composer test -- --filter LayoutImportReviewTest`. Expected: 2 tests pass.

- [x] **Step 4: Export and import the key.**
  - Add `'logged_out_content' => $layout->logged_out_content` to the layout entry in `LayoutExportRestController::export_layout()`, and add the logged-out view to the inline comment's list.
  - Add the trailing `string $logged_out_content = ''` to `LayoutRepository::import()`, pass it to `insert_draft()`, and update the docblock ("title, markup, logged-out view, …").
  - Pass `$layout['logged_out_content']` from `LayoutImportRestController::import_layout()`.
  - Leave `review_layout()` unchanged.

- [x] **Step 5: Verify the round trip with wp-cli** (socket from the local-site memory; `$WP` = `php -d mysqli.default_socket=… /opt/homebrew/bin/wp`)
  - Run `$WP eval` that sets `update_post_meta( <id>, '_yaycheckout_logged_out_content', '<!-- wp:paragraph --><p>LO</p><!-- /wp:paragraph -->' )` on a scratch layout. Dispatch `rest_do_request( new WP_REST_Request( 'GET', '/yaycheckout/v1/layouts/<id>/export' ) )` as an admin (`wp_set_current_user( 1 )`). Then POST its `get_data()` as `file` to `/yaycheckout/v1/layouts/import`, and print `get_post_meta( <new id>, '_yaycheckout_logged_out_content', true )`. Expected: the export has `logged_out_content` equal to the set value, and the new draft's meta prints the same string.
  - Repeat with the key `unset()` from the envelope. Expected: the import returns 201, and `metadata_exists( 'post', <new id>, '_yaycheckout_logged_out_content' )` is false.
  - Run `composer lint -- includes/Pro/Services/LayoutImportReview.php includes/Pro/Controllers/LayoutExportRestController.php includes/Pro/Controllers/LayoutImportRestController.php includes/Repositories/LayoutRepository.php tests/Integration/Pro/LayoutImportReviewTest.php`. Expected: the boundary check passes and phpcs reports no errors.
  - Trash the scratch drafts afterwards.

- [x] **Step 6: Commit.** Stage the five files above. Message: `feat(import-export): carry the logged-out view in layout exports`

### Task 2: SPA export schema accepts `design` and `logged_out_content`

**Files:**
- Modify: `apps/admin/src/lib/schema/tools.schema.ts:16-23`

**Interfaces:**
- Consumes: Task 1's export entry keys.
- Produces: the layout entries of `LayoutExportEnvelope` gain `design?: unknown` and `logged_out_content?: string`.

- [x] **Step 1: Extend the layout object.** Add `design: z.unknown().optional()` with the comment `// Unchecked: PHP projects it through LayoutDesign::from_meta() and never refuses it.`, and `logged_out_content: z.string().optional()`. Keep `.strict()`. Both are optional, because files from before #66 and from before this plan lack them. `reviewExportFile()` and `importLayout()` need no change: the envelope goes to PHP verbatim.

- [x] **Step 2: Verify.**
  - Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expected: no errors.
  - Run `./scripts/run.sh dev`, open wp-admin → YayCheckout → Tools on the Pro build, and export the Task 1 scratch layout. Expected: a `.json` downloads with `design` and `logged_out_content`, and the console shows no ZodError.
  - Import that file on the Tools page, then import a copy with both keys deleted. Expected: both reach the review step and create drafts. Only the first draft has the meta (check with `$WP post meta get <id> _yaycheckout_logged_out_content`).
  - Run `git status` (the dev script may rewrite `composer.json` / `scoper.inc.php`; don't stage those).

- [x] **Step 3: Commit.** Folded into Task 1's commit: `.claude/rules/php-standards.md` puts a wire-shape change and its `*WireSchema` in the same commit.

### Task 3: Docs and backlog

**Files:**
- Modify: `docs/architecture.md:57`
- Delete: `docs/backlog/2026-10-15-export-import-drops-logged-out-view.md`
- Modify: `docs/plans/2026-10-15-export-import-logged-out-view.md` (Status)

- [x] **Step 1:** In `architecture.md`'s logged-out paragraph, change "It has no revisions, duplicate copies it, and export, import and reset leave it out." to "It has no revisions. Duplicate, export and import copy it, the editor's Import replaces only the open view, and reset leaves it out."
- [x] **Step 2:** Run `./scripts/run.sh backlog`. Expected: the item is no longer listed. (All four Done when boxes are met by Tasks 1 and 2, so delete the file instead of ticking it.)
- [x] **Step 3: Final end-to-end check (acceptance 5).** On the imported draft from Task 2, mint `PreviewToken::mint( <new id>, 30 )` and open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>&yc-view=logged-out` in a private window. Expected: the logged-out blocks of the source layout show.
- [x] **Step 4:** Set this plan's Status to `done 2026-10-15`. Commit `docs/architecture.md`, the deleted backlog file and the plan. Message: `docs(import-export): record that export and import carry the logged-out view`
