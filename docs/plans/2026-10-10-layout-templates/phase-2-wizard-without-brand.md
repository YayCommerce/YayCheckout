# Phase 2: Setup wizard without the Brand step

**Plan:** `README.md` in this directory, which holds the Status, constraints and interface contract
**Depends on:** Phase 1 Task 2 (`LayoutRepository::create( $title, $template_slug )`, and `SetupRestController` already passing the slug)
**Delivers:** the setup wizard is Scan → Start from → Finish. Start from's button creates the layout from the picked template. Setup never writes `yaycheckout_design`.
**Exit criterion:**
1. Run `wp option get yaycheckout_design --format=json > /tmp/before.json`.
2. Re-run the wizard from Settings → General, pick Classic two-column and apply.
3. Expected:
   - no Brand step appears;
   - `wp option get yaycheckout_design --format=json | diff /tmp/before.json -` prints nothing;
   - the new draft's `_yaycheckout_template` is `classic-two-column`.

### Task 4: Drop design from `POST /setup` and remove the Brand step

**Files:**
- Modify: `includes/Controllers/SetupRestController.php`. Remove the `design` arg, `DesignRepository`, `$this->design`, `prepare()` and `store()`. Validate with `LayoutTemplateCatalogue::assert_available()`, then `create()`, then `mark_setup_done()`. Return `[ 'layout' => $layout->to_array() ]`.
- Modify: `apps/admin/src/lib/schema/setup.schema.ts`. `setupResponseWireSchema` becomes `{ layout }` (strict), and `SetupResult = { layout: Layout }`.
- Modify: `apps/admin/src/lib/api/setup.api.ts`. `CompleteSetupPayload = { templateSlug: string; rerun?: boolean }`, and the body is `{ template_slug, rerun }`. Drop the design imports.
- Modify: `apps/admin/src/lib/queries/setup.queries.ts`. `onSuccess` only calls `invalidateListedLayouts( queryClient )`. Drop `DESIGN_QUERIES`.
- Modify: `apps/admin/src/components/setup-wizard/SetupWizard.tsx`:
  - `STEPS = [Scan, Start from]`;
  - remove `SetupWizardWithDesign`, the `design` prop, `brandForm`, and the `DESIGN_QUERIES` invalidation on 409;
  - `SetupWizard` renders `<SetupWizardDialog />` when `showSetupWizard`, and `RerunSetupWizard` renders `<SetupWizardDialog rerun onClose={onClose} />`;
  - `onApply()` takes no values;
  - step 1 passes `isApplying`, `error` and `onApply` to `StartFromStep`;
  - the docblock no longer says "Apply brand".
- Modify: `apps/admin/src/components/setup-wizard/StartFromStep.tsx`. It replaces `onContinue` with `onApply: () => void`, `isApplying: boolean` and `error: string | null`. The button label is "Create layout", disabled while `isApplying` or with nothing selected. The error shows under the button row, as `BrandStep` showed it. The docblock says the draft is created here.
- Modify: `apps/admin/src/components/setup-wizard/FinishStep.tsx:11` docblock ("the draft Start from created").
- Delete: `apps/admin/src/components/setup-wizard/BrandStep.tsx`.
- Modify: `apps/admin/src/pages/settings/components/GeneralTab.tsx`. The Setup wizard row no longer says the brand restyles every layout.
- Modify: `docs/architecture.md:41`. `POST /setup` validates the template, creates the draft from it and marks setup done; it no longer saves a design.

**Interfaces:**
- Consumes: `LayoutRepository::create( string $title, string $template_slug ): Layout` and `LayoutTemplateCatalogue::assert_available()` (README contract)
- Produces: from the README contract, the `POST /yaycheckout/v1/setup` args and response, `CompleteSetupPayload` and `SetupResult`

**Steps:**
1. `SetupRestController`: drop the `design` route arg, the `DesignRepository` import and property, and the `prepare()` / `store()` calls. `complete_setup()` keeps the 409 check, then `assert_available()` and `create( __( 'Checkout — draft', 'yaycheckout' ), $template_slug )` inside the `try`, then `mark_setup_done()`, and returns `[ 'layout' => $layout->to_array() ]`. The docblock reads `Body: { template_slug, rerun? }` and `@return array|\WP_Error { layout }`.
2. Run `composer lint -- includes/Controllers/SetupRestController.php`. Expect the Pro boundary line and phpcs with no errors.
3. `setup.schema.ts`, `setup.api.ts`, `setup.queries.ts`: change them as the Files list says. `completeSetup()` posts `{ template_slug, rerun }` and returns `{ layout: parseLayout( layout ) }`.
4. `StartFromStep.tsx`: replace `onContinue` with `onApply`, `isApplying` and `error`. The primary button becomes a `LoadingButton` labelled "Create layout", with `loading={ isApplying }` and `disabled={ !selected || isApplying }`. Back is disabled while applying. `{ error && <p className="text-destructive text-sm">{ error }</p> }` sits above the button row, where `BrandStep` had it.
5. `SetupWizard.tsx`: change it as the Files list says. Step 1's Back clears `applyError`. On a 409 it closes the wizard, invalidates the listed layouts and shows the toast.
6. Delete `BrandStep.tsx`. Update the `FinishStep.tsx` docblock and `docs/architecture.md:41`.
7. Run `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`. Expect exit 0.
8. Run `git grep -n "BrandStep\|DESIGN_QUERIES\|Apply brand" -- apps/admin/src/components/setup-wizard apps/admin/src/lib/queries/setup.queries.ts apps/admin/src/lib/api/setup.api.ts`. Expect no output.
9. Run `pnpm --filter @yaycheckout/admin-page lint`. Expect no failures beyond the ones `dev/edd` already has: the 4 eslint warnings, and prettier failing on `utils.ts`, `custom-header.css` and `tsconfig.node.json`.
10. REST check: `POST /setup` with `{ template_slug: "classic-two-column", rerun: true }` returns 200 with only `layout` in the body. `yaycheckout_design` is unchanged, and the new layout's `_yaycheckout_template` is `classic-two-column`. The same request with `split-express` returns 400 "This template is coming soon." and creates no layout.
11. Exit criterion, in the browser: re-run the wizard from Settings → General. Expect Scan → Start from → Finish, with no Brand step.
12. First-run check: `wp option delete yaycheckout_setup_done`, then reload the admin. Expect the wizard to open and complete. The first run also needs zero layouts (`LocalizeHelper`), so on a site that has layouts, force `showSetupWizard` in a local edit and revert it afterwards.
13. 409 check: a first-run apply after setup is already done (in a second tab, or the REST call without `rerun`) returns 409. In the SPA it shows "Setup was already completed." and adds no layout.
14. Delete the layouts the checks created, and leave layout 61 active.
15. Commit the files above.

Commit message: `feat(setup): remove the wizard's Brand step and create the draft from the template`
