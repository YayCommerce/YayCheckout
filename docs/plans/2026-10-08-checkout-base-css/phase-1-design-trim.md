# Phase 1: Design trim

**Plan:** `README.md` in this directory: the Status, constraints and interface contract
**Depends on:** nothing. Branch `Trace/refactor/design-token-trim` from `dev/edd`.
**Delivers:**
- The Design page shows Colours, Typography, Inputs and Custom CSS, with no presets card and no Save-as-preset.
- Reset restores the schema defaults.
- The setup wizard's Brand step shows Primary colour, Font and (Pro) Custom CSS.
- The Pro build has no design-preset routes.

**Exit criterion:** all of the following pass.
1. **REST response:** in wp-admin's browser console, `await wp.apiFetch({ path: '/yaycheckout/v1/design' })` returns an object whose keys are exactly `tokens, custom_css, updated_at, defaults`, and whose `tokens` keys are exactly `colours, typography, inputs`.
2. **Design page:** Save, Reset and the "N overrides" count work (Task 2, Steps 6–7).
3. **Wizard:** a re-run completes (Task 2, Step 8).
4. **Type-check:** `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` exits 0.
5. **Lint:** `composer lint` exits 0.

Between Task 1 and Task 2 the SPA's strict wire schema rejects the new payload, so the Design page shows a parse error. Commit Task 1 only on the feature branch, and finish Task 2 before opening the PR.

### Task 1: Trim the Design model, REST and Pro

**Files:**
- Modify: `includes/Models/Design.php`
- Modify: `includes/Repositories/DesignRepository.php`
- Modify: `includes/Controllers/DesignRestController.php:9,40`
- Modify: `includes/Controllers/SetupRestController.php:63`
- Modify: `includes/Renderer/DesignStyles.php:81-88`
- Modify: `includes/Pro/ProServiceProvider.php:4,37`
- Delete: `includes/Pro/Controllers/DesignPresetRestController.php`
- Modify: `docs/architecture.md:40`
- Create: `docs/plans/2026-10-08-checkout-base-css/` (this plan, committed with this task)

**Interfaces:**
- Consumes: nothing.
- Produces: the README contract's "Design wire" and "REST bodies".

- [ ] **Step 1: Trim `includes/Models/Design.php`**
  - **Remove from `schema()`:** the `spacing` and `buttons` sections.
  - **Remove:**
    - constants: `SPACING_SCALES`, `PRESET_NAME_MAX_CHARS`
    - properties: `$presets`, `$preset_id`
    - methods: `presets()`, `user_presets()`, `has_preset()`, `user_preset_index()`, `sanitize_preset()`, `is_id()`
    - `coerce()`, whose only job was the float spacing enum, and its two call sites
  - **`from_option( $saved ): self`:** reads only `tokens`, `custom_css` and `updated_at`.
  - **`sanitize_input( array $input )`:** still an instance method, but it now requires only `tokens` and `custom_css` and returns `[ 'tokens', 'custom_css' ]`. Other keys are ignored, not rejected.
  - **`to_option()`:** returns `[ 'tokens', 'custom_css', 'updated_at' ]`.
  - **`to_array()`:** returns `[ 'tokens', 'custom_css', 'updated_at', 'defaults' => self::defaults() ]`.
  - **Docblocks:** update the class docblock and `schema()`'s docblock. Neither should mention presets or spacing any more.

- [ ] **Step 2: Trim `includes/Repositories/DesignRepository.php`**
  - Remove `add_preset()` and `delete_preset()`.
  - `prepare()` stops setting `preset_id`.
  - Update the docblocks to the body `{ tokens, custom_css }`. The class docblock no longer mentions user presets.

- [ ] **Step 3: Update the REST docblocks and remove the Pro preset routes**
  - `DesignRestController.php:9`: the response is `{ tokens, custom_css, updated_at, defaults }`.
  - `DesignRestController.php:40`: the body is `{ tokens, custom_css }`.
  - `SetupRestController.php:63`: the body is `{ template_slug, design: { tokens, custom_css }, rerun? }`.
  - Delete `includes/Pro/Controllers/DesignPresetRestController.php`, plus its `use` line and its `get_instance()` call in `ProServiceProvider.php`.

- [ ] **Step 4: Drop the spacing and buttons blocks from `DesignStyles::vars()`**

  Delete the `spacing-scale` and `button-radius` branches (`DesignStyles.php:81-88`). Nothing reads either variable.

- [ ] **Step 5: Update `docs/architecture.md:40`**

  Replace the sentences about presets with this: "The design's custom CSS is typed in `Models\Design` itself; the wire also carries `defaults`, the schema defaults that the SPA's Reset restores."

- [ ] **Step 6: Verify the REST shape and an old stored option**
  1. Seed an old-shape option with WP-CLI from Local's site shell:
     ```sh
     wp option update yaycheckout_design '{"tokens":{"colours":{"enabled":true,"primary":"#ff0000","text":"#171719","background":"#ffffff","border":"#e4e4e7"},"spacing":{"enabled":true,"scale":2},"buttons":{"enabled":true,"style":"outline","radius":4}},"preset_id":"dark","presets":[{"id":"mine","name":"Mine","tokens":{}}],"custom_css":""}' --format=json
     ```
  2. Then, in the console:
     - `await wp.apiFetch({ path: '/yaycheckout/v1/design' })`
       Expected: keys `tokens, custom_css, updated_at, defaults`; `tokens.colours.primary === '#ff0000'`; `tokens` has no `spacing` or `buttons`.
     - `await wp.apiFetch({ path: '/yaycheckout/v1/design', method: 'POST', data: { tokens: (await wp.apiFetch({ path: '/yaycheckout/v1/design' })).tokens, custom_css: '', preset_id: 'dark' } })`
       Expected: 200 with the same four keys.
  3. Finally, in the site shell: `wp option get yaycheckout_design --format=json`
     Expected: top-level keys `tokens, custom_css, updated_at` only, and `tokens` has no `spacing` or `buttons`.

- [ ] **Step 7: Verify lint and the Pro boundary**

  Run: `composer lint`
  Expected: exit 0, and `check-pro-boundary.sh` reports no violations.

- [ ] **Step 8: Commit**
  - Stage:
    - `includes/Models/Design.php`
    - `includes/Repositories/DesignRepository.php`
    - `includes/Controllers/DesignRestController.php`
    - `includes/Controllers/SetupRestController.php`
    - `includes/Renderer/DesignStyles.php`
    - `includes/Pro/ProServiceProvider.php`
    - `includes/Pro/Controllers/DesignPresetRestController.php` (deletion)
    - `docs/architecture.md`
    - `docs/specs/2026-10-08-checkout-base-css-design.md`
    - `docs/plans/2026-10-08-checkout-base-css/README.md`
    - `docs/plans/2026-10-08-checkout-base-css/phase-1-design-trim.md`
    - `docs/plans/2026-10-08-checkout-base-css/phase-2-base-css.md`
  - Message: `refactor(design): drop button and spacing tokens and presets from the model and REST`

### Task 2: Trim the SPA's design data, the Design page and the setup wizard

**Files:**
- Modify: `apps/admin/src/lib/schema/design.schema.ts`
- Modify: `apps/admin/src/lib/api/design.api.ts`
- Modify: `apps/admin/src/lib/api/setup.api.ts`
- Modify: `apps/admin/src/lib/queries/design.queries.ts`
- Modify: `apps/admin/src/lib/helpers/design.helper.ts`
- Modify: `apps/admin/src/pages/design/DesignPage.tsx`
- Modify: `apps/admin/src/pages/design/components/TokenGroups.tsx`
- Modify: `apps/admin/src/pages/design/components/ResetConfirmModal.tsx:29-34`
- Modify: `apps/admin/src/pages/design/components/TokenField.tsx:16`
- Delete: `apps/admin/src/pages/design/components/{SpacingGroup,ButtonsGroup,PresetsCard,SavePresetModal}.tsx`
- Modify: `apps/admin/src/components/setup-wizard/SetupWizard.tsx:10,96`
- Modify: `apps/admin/src/components/setup-wizard/BrandStep.tsx`
- Modify: `apps/admin/src/components/shared/PreviewPane.tsx`
- Modify: `docs/conventions.md:21`

**Interfaces:**
- Consumes: the README contract's "Design wire" and "REST bodies".
- Produces: the README contract's "SPA design types".

- [ ] **Step 1: Trim `design.schema.ts`**
  - **Remove:**
    - spacing: `SPACING_SCALE_OPTIONS`, `SpacingScale`, `spacingScaleSchema`
    - buttons: `buttonStyleSchema`, `BUTTON_STYLE_LABELS`
    - presets: `DEFAULT_PRESET_ID`, `designPresetWireSchema`, `DesignPresetWire`, `DesignPreset`
    - the `spacing` and `buttons` entries in `designTokensWireSchema` and `designTokensSchema`
  - **`designResponseWireSchema`:** becomes strict `{ tokens, custom_css, updated_at, defaults: designTokensWireSchema }`.
  - **`DesignResponse`:** becomes the README contract type. Its `defaultTokens` comment reads "Schema defaults from the server: what Reset restores."
  - **`designFormSchema`'s comment:** drop its reference to presets.

- [ ] **Step 2: Trim `design.api.ts`, `setup.api.ts` and `design.queries.ts`**
  - **`tokensFromWire` / `tokensToWire`:** drop `spacing` and `buttons`.
  - **`designResponseFromWire`:** maps `defaults` to `defaultTokens` through `tokensFromWire`.
  - **`designToWire( form: DesignForm )`:** returns `{ tokens, custom_css }`.
  - **`postDesign( form: DesignForm )`:** loses its `presetId` parameter.
  - **Remove:** `presetFromWire`, `postDesignPreset`, `deleteDesignPreset`.
  - **`setup.api.ts`:** drops `presetId` from its params and from the `designToWire` call.
  - **`design.queries.ts`:** remove `useSaveDesignPresetMutation` and `useDeleteDesignPresetMutation`. `useSaveDesignMutation`'s `mutationFn` takes `DesignForm`.

- [ ] **Step 3: Trim `design.helper.ts` and the Design page**
  - **`design.helper.ts`:** remove `activePresetIdFor` and keep `countTokenDiffs`.
  - **`DesignPage.tsx`:**
    - Remove `presetId` state, `savePresetOpen`, `applyPreset`, `deletePreset`, `deletePresetMutation`, `<PresetsCard>` and `<SavePresetModal>`.
    - `save( values: DesignForm ): Promise<boolean>`.
    - `resetToTheme()` saves `{ tokens: data.defaultTokens, customCss }`.
    - `DESCRIPTION` becomes `__('Customize checkout colors and typography.', 'yaycheckout')`.
  - **`TokenGroups.tsx`:** remove the `spacing` and `buttons` entries and their imports. Reword the comment at line 88 so it doesn't mention presets.
  - **Delete:** `SpacingGroup.tsx`, `ButtonsGroup.tsx`, `PresetsCard.tsx`, `SavePresetModal.tsx`.
  - **`ResetConfirmModal.tsx`:**
    - Title: `__('Reset all tokens to defaults?', 'yaycheckout')`.
    - Body: `__('Colours, typography and inputs go back to their defaults and save immediately. Custom CSS is kept.', 'yaycheckout')`.
  - **`TokenField.tsx:16`'s comment:** "Revert restores the last save."

- [ ] **Step 4: Trim the setup wizard**
  - `SetupWizard.tsx`: remove the `activePresetIdFor` import and the `presetId` argument at line 96.
  - `BrandStep.tsx`: remove `CornerRadiusField`, `ButtonStyleField` and the imports only they used (`RADIUS_RANGE` and `BUTTON_STYLE_LABELS` in this file, plus `Slider` and `Segmented` if nothing else in the file uses them). `RADIUS_RANGE` stays exported from `design.schema.ts`, because `InputsGroup.tsx` uses it.

- [ ] **Step 5: Fix `PreviewPane.tsx` and `docs/conventions.md`**
  - **`PreviewPane.tsx`:**
    - Drop `spacing` and `buttons` from the destructure.
    - `const gap = 10;`
    - `buttonStyle` always uses the fill look with `borderRadius: NEUTRAL.radius`.
  - **`docs/conventions.md:21`:** the example becomes `` `/** Mirrors DesignStyles::INPUT_HEIGHTS in PHP. */` ``.

- [ ] **Step 6: Verify type-check and lint**
  - Run: `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit`
    Expected: exit 0.
  - Run: `pnpm --filter @yaycheckout/admin-page lint`
    Expected: the same 4 eslint warnings and 3 prettier files as on `dev/edd`, and none in files this task touched.

- [ ] **Step 7: Verify the Design page** (with `./scripts/run.sh dev` running)
  1. Open `/wp-admin/admin.php?page=yaycheckout#/design`.
     Expected: groups Colours, Typography, Inputs and Custom CSS only; no Presets card; description "Customize checkout colors and typography."
  2. Turn Colours on, set primary `#ff0000` and click Save.
     Expected: toast "Design saved.", and `#ff0000` is still there after a reload.
  3. Check the actions bar.
     Expected: it shows a nonzero override count. Clicking Reset shows "Reset all tokens to defaults?", and confirming leaves every group showing "Theme default" and the count at 0.

- [ ] **Step 8: Verify a setup-wizard re-run**
  1. From Settings, re-run the setup wizard.
     Expected: the Brand step shows Primary colour, Font and Custom CSS (Pro) or its locked mock (Free), with no Corner radius and no Button style.
  2. Finish the wizard.
     Expected: no error, a new draft layout appears in Layouts, and the Design page shows the picked primary colour.

- [ ] **Step 9: Commit**
  - Stage every file in this task's Files list, including the four deletions.
  - Message: `refactor(admin): remove button, spacing and preset UI from Design and setup`
