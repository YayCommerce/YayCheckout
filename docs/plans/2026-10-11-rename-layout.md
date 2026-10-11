# Rename a layout Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the backlog item before starting.

**Status:** done 2026-10-11
**Goal:** The merchant can rename a layout from the admin app's layouts list.
**Spec:** none, planned from `docs/backlog/2026-10-07-rename-layout-in-spa.md`
**Architecture:** A new `LayoutRepository::rename()`, the single writer of layouts, updates `post_title`. A new EDITABLE method on the existing `/layouts/{id}` route takes `name`, the same arg and validation as create. The admin app adds a Rename item to the row's `⋯` menu. The item opens a form dialog that reuses `NameField`. The editor's title field stays hidden, as the backlog item asks.
**Touches:**
- `includes/Repositories/LayoutRepository.php`
- `includes/Controllers/LayoutRestController.php`
- `apps/admin/src/lib/schema/layout.schema.ts`
- `apps/admin/src/lib/api/layout.api.ts`
- `apps/admin/src/lib/queries/layout.queries.ts`
- `apps/admin/src/pages/layouts/LayoutsPage.tsx`
- new `apps/admin/src/pages/layouts/RenameModal.tsx`
- `docs/architecture.md`

**Tests:** `tests/Integration/Controllers/LayoutRenameRestTest.php`, suite Integration. It guards that `PUT /layouts/{id}` renames, that an empty name is refused with 400, and that a missing layout gives 404.

## Requirements

- **Goal:** a merchant can fix or change a layout's name after creating it.
- **Out of scope:**
  - The editor's title field stays hidden.
  - No rename in the archived tab.
  - No change to search.
- **Acceptance criteria:**
  1. `PUT yaycheckout/v1/layouts/{id}` with `{ "name": "New name" }` returns the layout with `title: "New name"`. A blank or whitespace-only `name` returns 400 `rest_invalid_param`. An unknown id returns 404.
  2. The listed tab's `⋯` menu has a Rename item. It opens a dialog prefilled with the current name. Saving shows the toast "Layout renamed." and the new name in the list.
  3. End to end: after a rename, searching the list for the new name finds the layout, and searching for the old name doesn't.
- **Decided with the user:** Tests: the rename route test in `**Tests:**` is accepted, even though CLAUDE.md's test rule doesn't require it.

## Global constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- **Wire casing:** the request body and response are snake_case, and the app's TypeScript is camelCase. They meet only in `layout.api.ts`.
- **REST:** the route lives under `yaycheckout/v1` and needs `LayoutPostType::CAPABILITY`. Validate in `args`, write through `exec_write_wp`, and answer expected failures with the `error_*()` helpers. A merchant-caused 4xx is not logged.
- `LayoutRepository` is the only writer of layouts.
- **Admin app:** each resource keeps the schema, api and queries split. Forms use react-hook-form with `zodResolver`. A modal that shows its own error sets `meta.skipGlobalError`. Strings use `__()` with the `yaycheckout` domain.
- `pnpm --filter @yaycheckout/admin-page lint` already fails on `dev/edd` (docs/known-issues.md), so the expected result is no new errors in the changed files.
- Branch from `dev/edd` as `<name>/feat/rename-layout`.

## Review focus

- **Renaming an active or published layout** must keep its status, active flag and `published_at`. Covered by Task 1's test and Task 2 Step 4.
- **Titles with HTML or surrounding spaces** must be stored as `sanitize_text_field()` makes them, and a title of only spaces must be refused. Covered by Task 1's test.
- **The open detail cache:** after a rename, the list and `GET /layouts/{id}` must show the new name. Covered by Task 2 Step 4.

---

### Task 1: Rename in the repository and REST route

**Files:**
- Modify: `includes/Repositories/LayoutRepository.php`
- Modify: `includes/Controllers/LayoutRestController.php` (the `/layouts/(?P<id>\d+)` registration, plus a new handler)
- Test: `tests/Integration/Controllers/LayoutRenameRestTest.php`

**Interfaces:**
- Produces: `LayoutRepository::rename( int $id, string $title ): Layout`. It throws `\InvalidArgumentException` when the layout is missing, the trimmed title is empty, or `wp_update_post` returns a `WP_Error`.
- Produces: `PUT|PATCH yaycheckout/v1/layouts/{id}` (`WP_REST_Server::EDITABLE`). It takes the body `{ name: string }` and returns `Layout::to_array()`.

- [ ] **Step 1: Write the failing test**

```php
class LayoutRenameRestTest extends WP_UnitTestCase {
    private function put( int $id, array $body ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'PUT', "/yaycheckout/v1/layouts/{$id}" );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $body ) );
        return rest_do_request( $request );
    }

    public function test_renames_layout(): void {
        LayoutFactory::as_shop_manager();
        $id = LayoutFactory::create_draft();
        $response = $this->put( $id, [ 'name' => '  Spring <b>sale</b> ' ] );
        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 'Spring sale', $response->get_data()['title'] );
        $this->assertSame( 'Spring sale', get_post( $id )->post_title );
        $this->assertSame( 'draft', get_post_status( $id ) );
    }

    public function test_refuses_blank_name(): void {
        LayoutFactory::as_shop_manager();
        $id = LayoutFactory::create_draft();
        $response = $this->put( $id, [ 'name' => '   ' ] );
        $this->assertSame( 400, $response->get_status() );
        $this->assertNotSame( '   ', get_post( $id )->post_title );
    }

    public function test_unknown_layout_is_404(): void {
        LayoutFactory::as_shop_manager();
        $this->assertSame( 404, $this->put( 999999, [ 'name' => 'X' ] )->get_status() );
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `composer test -- --filter LayoutRenameRestTest`
Expected: FAIL. The status is 404 `rest_no_route` instead of 200 in `test_renames_layout`.

- [ ] **Step 3: Implement `LayoutRepository::rename( int $id, string $title ): Layout`**

Follow `reset()`: `find()` or throw "Layout %d not found.", then refuse a trimmed empty title with a translated message. Then call `wp_update_post( wp_slash( [ 'ID' => $id, 'post_title' => $title ] ), true )`, throw on a `WP_Error`, and return `find( $id )`.

- [ ] **Step 4: Add the EDITABLE method to `/layouts/(?P<id>\d+)` with handler `rename_layout( \WP_REST_Request $request )`**

- **Args:** `id_args()` plus `name`, copied from `create_draft`'s arg (required, `sanitize_text_field`, non-empty `validate_callback`).
- **Callback:** wrap it in `exec_write_wp`.
- **Handler:** shaped like `duplicate_layout()`. Return `error_not_found( __( 'Layout not found.', 'yaycheckout' ) )` when the layout is missing, `rename()->to_array()` on success, and `error_invalid_arguments()` on `InvalidArgumentException`.

- [ ] **Step 5: Run the test and lint**

Run: `composer test -- --filter LayoutRenameRestTest`. Expected: PASS, 3 tests.
Run: `composer lint -- includes/Repositories/LayoutRepository.php includes/Controllers/LayoutRestController.php tests/Integration/Controllers/LayoutRenameRestTest.php`. Expected: no errors.

- [ ] **Step 6: Commit**

Stage the three files above.
Message: `feat(layouts): add a rename route for layouts`

### Task 2: Rename action in the admin app

**Files:**
- Modify: `apps/admin/src/lib/schema/layout.schema.ts`
- Modify: `apps/admin/src/lib/api/layout.api.ts`
- Modify: `apps/admin/src/lib/queries/layout.queries.ts`
- Create: `apps/admin/src/pages/layouts/RenameModal.tsx`
- Modify: `apps/admin/src/pages/layouts/LayoutsPage.tsx` (`LayoutsPageBody`, `LayoutsTable`, `LayoutTableRow`, `LayoutRowActions`)

**Interfaces:**
- Consumes: the Task 1 route.
- Produces:
  - `renameLayoutFormSchema`: `z.object({ name: z.string().trim().min(1, __('Name is required', 'yaycheckout')) })`, plus its inferred type `RenameLayoutForm`.
  - `renameLayout(id: number, name: string): Promise<Layout>`, which calls `api.put(\`layouts/${id}\`, { json: { name } })` and passes the result through `parseLayout`.
  - `useRenameLayoutMutation()`, with `mutationKey: ['layouts', 'rename']` and `meta: { skipGlobalError: true }`. On success it invalidates `LAYOUT_KEYS.list()` and `LAYOUT_KEYS.detail(id)`.

- [ ] **Step 1: Add the schema, the api function and the mutation listed above**

- [ ] **Step 2: Create `RenameModal({ layout, onOpenChange }: { layout: LayoutSummary | null; onOpenChange: (open: boolean) => void })`**

Follow `new-layout/NewLayoutModal.tsx`:
- The form sits inside `DialogContent` and uses `NameField`, with `defaultValues: { name: layout.title }`.
- **Title:** "Rename layout". **Buttons:** Cancel, and Save with "Saving…" while pending.
- **Saving:** `mutateAsync`, then the toast `__('Layout renamed.', 'yaycheckout')`, then close.
- **On error:** `form.setError('root', { message: await getErrorMsg(error) })`.

- [ ] **Step 3: Wire it into `LayoutsPage.tsx`**

- Add `const [renamingLayout, setRenamingLayout] = useState<LayoutSummary | null>(null)` in `LayoutsPageBody`.
- Thread an `onRename(layout)` prop through `LayoutsTable`, `LayoutTableRow` and `LayoutRowActions`.
- In the listed tab's `⋯` menu, add `<DropdownMenuItem onSelect={() => onRename(layout)}>{__('Rename', 'yaycheckout')}</DropdownMenuItem>` before Duplicate.
- Render `<RenameModal layout={renamingLayout} onOpenChange={(open) => !open && setRenamingLayout(null)} />`.

- [ ] **Step 4: Verify**

Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expected: no errors.
Run: `pnpm --filter @yaycheckout/admin-page lint`. Expected: no errors in the changed files (the base failure is recorded in docs/known-issues.md).

Open: wp-admin → YayCheckout → Layouts, with `./scripts/run.sh dev` running. Action and expected result:
1. On the active layout, open `⋯` → Rename. The dialog shows the current name.
2. Clear the name and save. "Name is required" appears inline and nothing is sent.
3. Enter "Renamed active" and save. The toast "Layout renamed." appears, the row shows "Renamed active", and the row is still Active.
4. Open Edit on the layout. The embed opens normally.

- [ ] **Step 5: Commit**

Stage the five files above.
Message: `feat(layouts): rename a layout from the layouts list`

### Task 3: Docs, end-to-end check, close the backlog item

**Files:**
- Modify: `docs/architecture.md`, line 86, the `LayoutEditorCanvas` sentence. Change "so a layout's name is set when it is created and can't be changed after" to say that the layout's name is changed from the list's Rename action (`PUT /layouts/{id}`).
- Delete: `docs/backlog/2026-10-07-rename-layout-in-spa.md`, once its boxes are ticked.

- [ ] **Step 1: End-to-end check**

In Layouts, rename a layout from "Checkout A" to "Spring checkout". Then search "Spring" and "Checkout A".
Expected: "Spring" lists the layout and "Checkout A" doesn't.

- [ ] **Step 2: Update `docs/architecture.md` as listed above**

- [ ] **Step 3: Close the backlog item**

Run `/yaybacklog check rename layout`, tick the two boxes it reports `met`, and remove the item. Set this plan's Status to `done YYYY-MM-DD`.

- [ ] **Step 4: Commit**

Stage: `docs/architecture.md`, the backlog removal, and the plan file.
Message: `docs(layouts): document layout rename and close its backlog item`
