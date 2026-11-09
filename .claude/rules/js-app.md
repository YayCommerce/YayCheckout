---
paths:
  - "apps/admin/**"
---

<!--
Purpose: the rules for the admin app, whose checks and types mirror PHP by hand.
Ownership: anything below the `your rules` marker at the foot is this repo's and is
  never regenerated; everything above it comes from the kit.
Update when: the mirror moves, or the package manager changes.
-->

- **A TypeScript copy of a PHP shape is a mirror maintained by hand.** Change both, or the
  wire format and the type drift silently and the failure appears far from the edit.
- **What the app shows is not what the server allows.** A plan flag in the client decides
  presentation only; the capability is withheld server-side, and putting real data or a real
  endpoint behind a client-side gate ships it to everyone.
- **Run the package manager the repo pins**, and check `git status` afterwards: an install
  can rewrite tracked files.

<!-- --- your rules --- -->

<!-- Carried wholesale from this repo's own .yay/archive/old-harness/.claude/rules/react-admin.md when the kit was adopted. The kit
     wrote none of it and has not checked any of it. Trim what the rules above now cover;
     keep every line they do not — that is why this was kept rather than replaced. -->

<!--
Purpose: rules for writing the admin SPA in apps/admin/: the patterns new code follows, and its couplings to PHP and to the blocks package that no check catches. Loads when Claude reads a matching file.
Update when: one of these patterns changes, a new coupling to PHP or to packages/blocks appears, or the lint, format or build config changes.
-->

# Admin SPA

How the SPA loads and calls WordPress is in [docs/architecture.md § SPA and WordPress](../../docs/architecture.md#spa-and-wordpress), and how Free and Pro split in [§ Free and Pro](../../docs/architecture.md#free-and-pro). Wire casing, dates, file names, logging and TypeScript copies of PHP are in [docs/conventions.md](../../docs/conventions.md). Hard rule 3 in `CLAUDE.md` governs Pro UI.

## Patterns for new code

- **Data layer:** each REST resource has three files: `apps/admin/src/lib/schema/<resource>.schema.ts` (strict zod `*WireSchema` and the camelCase app types), `apps/admin/src/lib/api/<resource>.api.ts` (one function per endpoint through `yayCheckoutApi` or `wordpressApi` from `apps/admin/src/lib/api/api.ts`, converting to and from the wire), and `apps/admin/src/lib/queries/<resource>.queries.ts` (`queryOptions` and the hooks). Pages and hooks never see a snake_case key.
- **Mutations:** a mutation that another component watches with `useIsMutating` exports its key as a `*_MUTATION_KEY` constant from its queries file, as `IMPORT_LAYOUT_MUTATION_KEY` does. Logging happens once, in `apps/admin/src/lib/query-client.ts`, which also toasts the message from `getErrorMsg()` (`apps/admin/src/lib/helpers/response.helper.ts`) unless the mutation sets `meta.skipGlobalError` and its screen reports the error itself.
- **Forms:** react-hook-form with `zodResolver` over the resource's zod schema.
- **Pro:** read the plan from `apps/admin/src/lib/plan.ts` (`isPro`). `<ProGate>` blurs its children for Free and still ships them; `<ProOnly>` hides them and still ships them. Neither is used today: Free's upsells are `ProBadge` and `UpgradeButton` teasers. To leave code out of the Free bundle, branch at the call site: `{isPro && <X />}`.
- **Components:** `apps/admin/src/components/ui/` holds shadcn-style primitives; there is no `components.json`, so no CLI regenerates them. Don't add page components there. A component used by one page lives in that page's `components/` folder, and moves to `apps/admin/src/components/shared/` when a second page needs it.
- **`FormItem` hides switches:** a vertical `FormItem` makes every direct child `w-auto`, so a `Switch` inside it shows as a bare thumb. Put a switch in a horizontal row (`SettingRow` defaults to one, see `apps/admin/src/pages/settings/components/GeneralTab.tsx`), or render it straight from `FormField`'s render prop without `FormItem`.

## Couplings no check catches

- The dev server's port 3040 is set in `apps/admin/vite.config.ts` and in `RegisterDev::DEV_SERVER` (`includes/Engine/Register/RegisterDev.php`). Change both together.
- A new `@wordpress/*` package goes in `apps/admin/package.json`, in `externalProd` in `apps/admin/vite.config.ts` (and in `externalDev` when WordPress sets up its state, as for i18n and hooks), and its script handle in `$deps` in `RegisterProd.php` and `RegisterDev.php`.
- `@blocks-shared` imports `packages/blocks/src/shared/`. A change there reaches both bundles: type-check the SPA and build the blocks.
