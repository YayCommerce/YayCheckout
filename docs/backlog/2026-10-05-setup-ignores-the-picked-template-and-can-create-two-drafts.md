# Setup ignores the picked template, says it used it, and can create two drafts

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **The template.** `SetupRestController::complete_setup()` and `LayoutRestController::create_draft()` (source `library`) check `template_slug` with `LayoutTemplateCatalogue::assert_available()`, then create `LayoutRepository::default_markup()` whatever was picked. The docblocks of `create_draft()` and `LayoutTemplateCatalogue` call this a placeholder until templates have markup.
- **The copy.** The SPA says otherwise. The wizard's `apps/admin/src/components/setup-wizard/FinishStep.tsx` says the layout was "built from" the template and lists it under "Started from". The New layout modal's `apps/admin/src/pages/layouts/new-layout/LibraryStep.tsx` says existing fields and gateways "are mapped into the template you pick".
- **Two drafts.** `complete_setup()` checks `SettingsRepository::is_setup_done()`, creates the draft, saves the design, and only then marks setup done. Two requests that overlap both pass the check, so each creates a draft. That happens with two tabs, or when the merchant clicks Apply brand again after the SPA's 20-second request timeout (`apps/admin/src/lib/api/api.ts`) while the first request still runs (inferred).

Creating the default draft whatever the template predates the wizard (the placeholder docblock dates from `7c66d52`), and the mapping promise in `LibraryStep.tsx` came in `0164f0c`. `f35ff1e` added the wizard, its copy, the same mapping promise in `apps/admin/src/components/setup-wizard/StartFromStep.tsx`, and the setup route.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

The merchant picks a template, is told the draft was built from it, and opens the editor on the same default layout every template gives. Two drafts from one setup take two of Free's three slots.

## Suggested fix

Until templates have markup, make the copy true: drop "built from" and "Started from" from `apps/admin/src/components/setup-wizard/FinishStep.tsx` and the mapping promise from `apps/admin/src/pages/layouts/new-layout/LibraryStep.tsx`, or label the templates as previews. When templates get markup, build the draft from it in one place both routes call.

For the race, claim the setup flag atomically before the first write. `add_option()` fails when the row already exists, so the losing request can answer 409. Release the flag if a later step throws.

## Done when

- [ ] `apps/admin/src/components/setup-wizard/FinishStep.tsx` names a template only when the draft's markup came from it.
- [ ] `apps/admin/src/components/setup-wizard/StartFromStep.tsx` promises a mapping into the template only when the draft is built from the template.
- [ ] `apps/admin/src/pages/layouts/new-layout/LibraryStep.tsx` promises a mapping into the template only when `LayoutRestController::create_draft()` builds from the template.
- [ ] Two overlapping `POST /yaycheckout/v1/setup` requests create one draft, and the other answers 409.
