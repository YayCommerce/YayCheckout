# Phase 1: Layer storage and storefront

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** nothing
**Delivers:** A layout with `_yaycheckout_design` prints its effective design on the storefront, in its preview link and in its editor canvas (Pro). Duplicate, export and import carry the layer. Free prints global.
**Exit criterion:**
1. The accepted test passes: `/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit --filter LayoutImportDesignTest` → `OK`.
2. On yaycheckout.test (Pro), take a layout L and set its layer through `wp/v2` (Task 3 Step 3) with `colours` primary `#171719`.
3. Create L's preview link from the Layouts page (Preview) and open it. The page source has `--yaycheckout-color-primary: #171719;`. The `--yaycheckout-font-*` variables match those of another layout's preview.

### Task 1: Layer methods on `Design`

**Files:**
- Modify: `includes/Models/Design.php`

**Interfaces:**
- Produces: `Design::project_layer()`, `Design::validate_layer()`, `Design::with_layer()` (see the README contract).

- [ ] **Step 1: Implement the three methods in `includes/Models/Design.php`**

All three walk `self::schema()`, and a section is all-or-nothing. A layer section must hold every key of its schema section, each passing `is_valid()`. Unknown sections and keys are dropped. `custom_css` goes through `sanitize_custom_css()`, and `''` counts as absent.
- `validate_layer` errors on: a section that is not an array (`invalid( '<section>' )`), a missing key or invalid value (`invalid( '<section>.<key>' )`), and a CSS failure (the `sanitize_custom_css()` error).
- `with_layer` uses `clone $this`, never `new self()`.

- [ ] **Step 2: Verify with WP-CLI**

Run from `/Users/mac/Sites/yaycheckout`:
```
wp eval '$D=YayCheckout\Models\Design::class;
var_export($D::project_layer(["tokens"=>["typography"=>["enabled"=>true,"font_family"=>"inter","base_size"=>18],"colours"=>["primary"=>"#111111"],"nope"=>[]],"custom_css"=>""]));
echo "\n"; var_export(is_wp_error($D::validate_layer(["tokens"=>["colours"=>["enabled"=>true,"primary"=>"red","text"=>"#000000","background"=>"#ffffff","border"=>"#e4e4e7"]]])) ? $D::validate_layer(["tokens"=>["colours"=>["enabled"=>true,"primary"=>"red","text"=>"#000000","background"=>"#ffffff","border"=>"#e4e4e7"]]])->get_error_message() : "no error");
echo "\n"; $g=(new YayCheckout\Repositories\DesignRepository())->get(); $e=$g->with_layer(["tokens"=>["typography"=>["enabled"=>true,"font_family"=>"inter","base_size"=>18]],"custom_css"=>"a{}"]); var_export([$e->tokens["typography"], $e->tokens["colours"]===$g->tokens["colours"], substr($e->custom_css,-4)]);'
```
Expected:
1. `array ( 'tokens' => array ( 'typography' => array ( 'enabled' => true, 'font_family' => 'inter', 'base_size' => 18 ) ) )`. The incomplete `colours` section, the unknown `nope` section and the empty CSS are all dropped.
2. `'Invalid value for colours.primary.'`
3. `array ( 0 => array ( 'enabled' => true, 'font_family' => 'inter', 'base_size' => 18 ), 1 => true, 2 => "\na{}" )`. The last value holds no `\n` when global CSS is empty.

- [ ] **Step 3: Lint**

Run: `composer lint -- includes/Models/Design.php`
Expected: no errors.

### Task 2: Layout meta, `for_layout()` and the print point

**Files:**
- Modify: `includes/Models/Layout.php`
- Modify: `includes/Repositories/DesignRepository.php`
- Modify: `includes/Repositories/LayoutRepository.php` (`duplicate()`, `import()`, `insert_draft()`)
- Modify: `includes/Renderer/CheckoutBaseStyles.php`
- Modify: `includes/Renderer/CheckoutPage.php` (`enqueue_scripts()`)
- Modify: `includes/Engine/Admin/LayoutEditorCanvas.php` (`canvas_base_styles()`)

**Interfaces:**
- Consumes: `Design::project_layer()` (Task 1).
- Produces: `Layout::META_DESIGN`, `Layout::$design`, `DesignRepository::for_layout()`, the filter `yaycheckout_design_for_layout`, `CheckoutBaseStyles::enqueue( bool, ?Layout )`, and `import()`/`insert_draft()` taking `$design` (see the README contract).

- [ ] **Step 1: Read the meta into `Layout::$design`** in `from_post()` with `Design::project_layer( self::meta_value( $meta, self::META_DESIGN, null ) )`. `to_array()` and `to_summary_array()` stay unchanged.
- [ ] **Step 2: Wire the rest**
  - `DesignRepository::for_layout()` applies the filter.
  - `CheckoutBaseStyles::enqueue()` prints `for_layout( $layout )` in place of `get()`.
  - `CheckoutPage::enqueue_scripts()` passes `$this->layout()`.
  - `LayoutEditorCanvas::canvas_base_styles()` passes `( new LayoutRepository() )->find( (int) get_the_ID() )`.
  - `duplicate()` passes `$source->design`.
  - `insert_draft()` adds `Layout::META_DESIGN => $design` to `meta_input` when `[] !== $design`.

- [ ] **Step 3: Verify that, with no filter hooked yet, output equals global (spec AC 1, and AC 6's Free half)**

Run from `/Users/mac/Sites/yaycheckout`, with L the id of any layout:
```
wp post meta update L _yaycheckout_design '{"tokens":{"colours":{"enabled":true,"primary":"#171719","text":"#171719","background":"#ffffff","border":"#e4e4e7"}}}' --format=json
wp eval '$r=new YayCheckout\Repositories\LayoutRepository(); $l=$r->find(L); $d=new YayCheckout\Repositories\DesignRepository(); remove_all_filters("yaycheckout_design_for_layout"); $s=new YayCheckout\Renderer\DesignStyles(); var_export([$l->design["tokens"]["colours"]["primary"], $s->css($d->for_layout($l),true)===$s->css($d->get(),true)]);'
```
Expected: `array ( 0 => '#171719', 1 => true )`.

- [ ] **Step 4: Verify duplicate carries the layer**

Action: on the Layouts page, duplicate L, then run `wp post meta get <new id> _yaycheckout_design --format=json`.
Expected: the same JSON as L.

- [ ] **Step 5: Lint**

Run: `composer lint -- includes/Models/Layout.php includes/Repositories/DesignRepository.php includes/Repositories/LayoutRepository.php includes/Renderer/CheckoutBaseStyles.php includes/Renderer/CheckoutPage.php includes/Engine/Admin/LayoutEditorCanvas.php`
Expected: no errors.

- [ ] **Step 6: Commit**

Stage: `includes/Models/Design.php`, plus the six files above.
Message: `feat(design): add per-layout design layer storage and print point`

### Task 3: Pro attaches the layer and the REST meta field

**Files:**
- Modify: `includes/Pro/ProServiceProvider.php`

**Interfaces:**
- Consumes: `Design::validate_layer()`, `Design::project_layer()`, `Design::with_layer()`, `Layout::META_DESIGN`, the filter `yaycheckout_design_for_layout`.
- Produces: the REST meta field `meta._yaycheckout_design` (see the README contract).

- [ ] **Step 1: In `boot()`, hook the following**
  - `yaycheckout_design_for_layout`, set to a method returning `$design->with_layer( $layout->design )`.
  - `init`, set to a method calling `register_post_meta( Layout::POST_TYPE, Layout::META_DESIGN, [ 'single' => true, 'type' => 'object', 'show_in_rest' => [ 'schema' => [ 'type' => 'object', 'additionalProperties' => true ] ], 'sanitize_callback' => [ Design::class, 'project_layer' ], 'auth_callback' => fn() => current_user_can( LayoutPostType::CAPABILITY ) ] )`.
  - `rest_pre_insert_yay_checkout_layout` (2 args), set to a method. When `$request['meta'][ Layout::META_DESIGN ]` is set, it runs `Design::validate_layer()`, and on error returns `new \WP_Error( 'invalid_arguments', $message, [ 'status' => 400 ] )`.
  - `update_post_metadata` (5 args), set to a short-circuit. For `Layout::META_DESIGN`, when `Design::project_layer( $value )` is `[]`, it calls `delete_post_meta( $object_id, Layout::META_DESIGN )` and returns `true`, so an empty layer is never stored, whoever writes it. For any other key it returns `$check`.

- [ ] **Step 2: Lint**

Run: `composer lint -- includes/Pro/ProServiceProvider.php`
Expected: no errors. The boundary check passes.

- [ ] **Step 3: Verify the storefront and the disabled-section case (spec AC 2, 3, 4)**

Run from `/Users/mac/Sites/yaycheckout`, with L from Task 2:
```
wp eval '$l=(new YayCheckout\Repositories\LayoutRepository())->find(L); echo (new YayCheckout\Renderer\DesignStyles())->css((new YayCheckout\Repositories\DesignRepository())->for_layout($l),true);'
```
Expected: `--yaycheckout-color-primary: #171719;`, while the typography lines equal those of `wp eval` on `->get()`.

Then set the layer to `{"tokens":{"colours":{"enabled":false,"primary":"#171719","text":"#171719","background":"#ffffff","border":"#e4e4e7"}},"custom_css":"b{}"}` and rerun. Expected: no `--yaycheckout-color-` lines, and the output ends with the global CSS, then `b{}`.

- [ ] **Step 4: Verify the REST validation (spec AC 10)**

Run:
```
wp eval 'wp_set_current_user(1); $q=new WP_REST_Request("POST","/wp/v2/yay_checkout_layout/L"); $q->set_body_params(["meta"=>["_yaycheckout_design"=>["custom_css"=>"</style>"]]]); $r=rest_do_request($q); echo $r->get_status()," ",$r->get_data()["message"];'
```
Expected: `400 Custom CSS contains disallowed markup.`, and the stored meta is unchanged.

Run the same request with `"meta"=>["_yaycheckout_design"=>[]]`. Expected: status 200, and `wp post meta get L _yaycheckout_design` prints nothing.

- [ ] **Step 5: Commit**

Stage: `includes/Pro/ProServiceProvider.php`
Message: `feat(pro): apply the layout design layer and expose it to wp/v2`

### Task 4: Export and import carry the layer

**Files:**
- Modify: `includes/Pro/Controllers/LayoutExportRestController.php` (`export_layout()`)
- Modify: `includes/Pro/Services/LayoutImportReview.php` (`layout()`)
- Modify: `includes/Pro/Controllers/LayoutImportRestController.php` (`import_layout()`)
- Test: `tests/Integration/Pro/LayoutImportDesignTest.php`

**Interfaces:**
- Consumes: `LayoutRepository::import(…, array $design)`, `Design::project_layer()`.
- Produces: `from_envelope()` with `design` (see the README contract).

- [ ] **Step 1: Write the failing test**

```php
namespace YayCheckout\Tests\Integration\Pro;

use WP_UnitTestCase;
use YayCheckout\Models\Layout;
use YayCheckout\Tests\Support\LayoutFactory;

/** Import keeps a file's valid design sections, drops invalid ones without refusing the file, and adds nothing for a file without design. */
class LayoutImportDesignTest extends WP_UnitTestCase {

    private const COLOURS = [ 'enabled' => true, 'primary' => '#171719', 'text' => '#171719', 'background' => '#ffffff', 'border' => '#e4e4e7' ];

    public function set_up(): void {
        parent::set_up();
        wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
    }

    private function import( array $entry ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'POST', '/yaycheckout/v1/layouts/import' );
        $request->set_body_params( [ 'file' => [ 'format' => Layout::EXPORT_FORMAT, 'layouts' => [ array_merge( [ 'title' => 'Imported', 'content' => LayoutFactory::checkout_markup() ], $entry ) ] ] ] );

        return rest_do_request( $request );
    }

    public function test_valid_sections_are_kept_and_invalid_ones_dropped(): void {
        $response = $this->import( [ 'design' => [ 'tokens' => [ 'colours' => self::COLOURS, 'inputs' => [ 'enabled' => true, 'style' => 'wavy' ] ], 'custom_css' => 'a{color:red}' ] ] );

        $this->assertSame( 201, $response->get_status() );
        $this->assertSame(
            [ 'tokens' => [ 'colours' => self::COLOURS ], 'custom_css' => 'a{color:red}' ],
            get_post_meta( $response->get_data()['id'], Layout::META_DESIGN, true )
        );
    }

    public function test_disallowed_css_is_dropped_not_refused(): void {
        $response = $this->import( [ 'design' => [ 'tokens' => [ 'colours' => self::COLOURS ], 'custom_css' => '</style><script>' ] ] );

        $this->assertSame( 201, $response->get_status() );
        $this->assertSame( [ 'tokens' => [ 'colours' => self::COLOURS ] ], get_post_meta( $response->get_data()['id'], Layout::META_DESIGN, true ) );
    }

    public function test_file_without_design_adds_no_meta(): void {
        $response = $this->import( [] );

        $this->assertSame( 201, $response->get_status() );
        $this->assertFalse( metadata_exists( 'post', $response->get_data()['id'], Layout::META_DESIGN ) );
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit --filter LayoutImportDesignTest`
Expected: FAIL. The first two tests fail because the meta is empty. The third passes already.

- [ ] **Step 3: Implement**
  - `layout()` returns `'design' => $layout['design'] ?? null`.
  - `import_layout()` passes `Design::project_layer( $layout['design'] )` to `import()`.
  - `export_layout()` adds `'design' => $layout->design` to the entry, only when not `[]`.
  - Update the docblocks that list the returned keys.

- [ ] **Step 4: Run the test to confirm it passes**

Run: `/opt/homebrew/opt/php@8.4/bin/php vendor/bin/phpunit --filter LayoutImportDesignTest`
Expected: `OK (3 tests, …)`.

- [ ] **Step 5: Verify export by hand (spec AC 11)**

Action: in L's editor, use Import / Export › Export.
Expected: the downloaded JSON's `layouts[0].design` equals L's meta. Exporting a layout with no meta has no `design` key.

- [ ] **Step 6: Lint and commit**

Run: `composer lint -- includes/Pro/Controllers/LayoutExportRestController.php includes/Pro/Services/LayoutImportReview.php includes/Pro/Controllers/LayoutImportRestController.php tests/Integration/Pro/LayoutImportDesignTest.php`
Expected: no errors.

Stage: the three files above and `tests/Integration/Pro/LayoutImportDesignTest.php`
Message: `feat(pro): carry the layout design layer through export and import`
