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
- Modify: `docs/architecture.md:41`. `POST /setup` validates the template, creates the draft from it and marks setup done; it no longer saves a design.

**Interfaces:**
- Consumes: `LayoutRepository::create( string $title, string $template_slug ): Layout` and `LayoutTemplateCatalogue::assert_available()` (README contract)
- Produces: from the README contract, the `POST /yaycheckout/v1/setup` args and response, `CompleteSetupPayload` and `SetupResult`

Steps: expand after Phase 1 lands. Verification must cover:
- `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` exits 0;
- `git grep -n "BrandStep\|DESIGN_QUERIES" -- apps/admin/src/components/setup-wizard apps/admin/src/lib/queries/setup.queries.ts` prints nothing;
- `composer lint -- includes/Controllers/SetupRestController.php` is clean;
- the exit criterion above;
- a first-run check: `wp option delete yaycheckout_setup_done`, reload the admin, and the wizard opens and completes;
- a 409 check: apply in two tabs, and the second shows "Setup was already completed."

Commit message: `feat(setup): remove the wizard's Brand step and create the draft from the template`
