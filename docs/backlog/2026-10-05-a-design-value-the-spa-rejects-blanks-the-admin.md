# A design value the SPA rejects replaces the whole admin with the error page

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`apps/admin/src/AppLayout.tsx` always renders `SetupWizard` (`apps/admin/src/components/setup-wizard/SetupWizard.tsx`), which calls `useDesignQuery()` whether or not the wizard is open. That query's `initialData` (`DESIGN_QUERIES.main` in `apps/admin/src/lib/queries/design.queries.ts`) runs `designResponseWireSchema.parse(window.yayCheckoutAdmin.design)` during render. The schema is strict at every level, with a hex regex, enums and ranges.

A throw there reaches the root route's `errorElement` in `apps/admin/src/router.tsx`. `ErrorPage` (`apps/admin/src/pages/500.tsx`) then replaces every screen, header included, and prints the zod issues as JSON. Its "Back to Layouts" button renders the same tree, which throws again.

PHP and zod can disagree. `Models\Design::is_valid()` (`includes/Models/Design.php`) checks a colour with `/^#[0-9A-Fa-f]{6}$/` and no `D` modifier, so PHP's `$` also matches before a final newline. `"#112233\n"` passes `Design::sanitize()` on a REST save and `project()` on read, then fails the zod regex. Who would send it is inferred: a script calling `POST /yaycheckout/v1/design`, or a `yaycheckout_localize_admin_data` filter. Drift between the PHP and TypeScript schemas has the same effect. The render-time parse came in `10a0f7b`, and the always-mounted wizard in `f35ff1e`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

One stored value the SPA rejects locks the merchant out of every YayCheckout screen, including Layouts, which never uses the design. The error page offers no way out, and only a developer can clear the value.

## Suggested fix

Keep a design failure on the screens that use the design. Read the design in the wizard only while it is open, or only in its Brand step. Parse the bootstrap with `safeParse`: on failure, log it and leave `initialData` undefined, so `getDesign` fetches and its error shows where the design is used. Give the design route its own `errorElement`. Add the `D` modifier to the colour pattern in `Models\Design::is_valid()`, so PHP refuses what zod refuses.

## Done when

- [ ] `SetupWizard` calls no design query while the wizard is closed.
- [ ] `DESIGN_QUERIES.main.initialData` uses `safeParse`, so a malformed bootstrap doesn't throw during render.
- [ ] With a `yaycheckout_localize_admin_data` filter that sets `design.tokens.colours.primary` to `"#112233\n"`, the Layouts, Tools and Settings pages render normally.
- [ ] A `POST /yaycheckout/v1/design` whose body is valid except that `tokens.colours.primary` is `"#112233\n"` returns 400 with "Invalid value for colours.primary."
