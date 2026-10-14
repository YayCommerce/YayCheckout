# Template Picker Previews Design

**Status:** accepted 2026-10-14
**Related:** `~/Downloads/WooCommerce Checkout Templates_v1.1.html` (the template mocks); [Layout Templates Design](2026-10-10-layout-templates-design.md)

## Goal

When a merchant picks a template, in the New layout modal's library step or the setup wizard's Start from step, they see large cards showing the real mock page of each template. On hover a card scrolls down through the whole page, so the merchant can see what a template is before picking it.

## Context

- `apps/admin/src/components/shared/template-picker/TemplatePicker.tsx` is the shared picker, used by:
  - `pages/layouts/new-layout/LibraryStep.tsx`, inside `NewLayoutModal`'s `DialogContent className="sm:max-w-2xl"`;
  - `components/setup-wizard/StartFromStep.tsx`, inside `SetupWizard`'s `DialogContent` (`sm:max-w-220`).
- Pro gets a 5-column grid (`h-84`) with category chips. Free gets 3 unlocked tiles plus a dashed, `opacity-45` strip of 4-column locked thumbnails with `ProTag` and `UpgradeButton`. Unpickable tiles (`!isPickable`) are `opacity-50`.
- Each tile draws a hand-made 120×80 SVG sketch:
  - `TemplateThumbnail.tsx`
  - `TemplateThumbnailPrimitives.tsx`
  - `template-thumbnail-sketches.tsx` (`SKETCHES`, `FALLBACK`)

  The sketches only roughly match the templates.
- `LayoutTemplateCatalogue::catalogue()` lists 11 templates (slug, name, category). `templates()` adds `locked` and `available`, and `GET /yaycheckout/v1/layouts/templates` returns them. The SPA parses them with `lib/schema/template.schema.ts` in `lib/api/template.api.ts`.
- Template files live in `templates/layouts/<slug>/`. `is_available()` requires both `markup.php` and `design.json`. The release copies all of `templates/` into the zip (`scripts/run.sh` `cmd_release`).
- The mock is a bundler page. Each template is a template (`{{ bindings }}`, `<sc-if>`, `<sc-for>`) rendered by React from unpkg plus a `DCLogic` class, so it shows nothing without JS. `.claude/skills/build-checkout-template/scripts/extract_mock.py --mock <path> <id> --out <dir>` writes one template's page as `t<id>.html`, and `--list` lists the 11 ids and names (01–09, 11, 12). The names match the catalogue's names.
- Headless Chrome (`--headless=new --dump-dom --virtual-time-budget=8000`) renders `t01.html` correctly. With its `<script>` elements removed, the result is 61 KB and self-contained: system fonts, `data:` images, 7 `<style>` blocks.

## Scope

**In:**
- A generated, script-free `templates/layouts/<slug>/preview.html` for all 11 catalogue slugs, captured from the mock in its initial state.
- `scripts/capture-template-previews.py` and the `./scripts/run.sh previews [mock-path]` command that writes them.
- `preview_url` on each entry of `GET /layouts/templates`.
- The redesigned shared `TemplatePicker`: 3-column large cards, each showing its preview in a sandboxed, scaled iframe that scrolls on hover. Used by both the New layout library step and the wizard's Start from step.
- Deleting the SVG sketch components.
- Doc updates: CLAUDE.md hard rule 1, `docs/architecture.md`, the build-checkout-template skill, `./scripts/run.sh help`.

**Out:**
- A click-to-enlarge or full-screen preview.
- Capturing every step or state of the multi-step templates. They show their initial state.
- Previews of the real built layouts (`markup.php` rendered).
- Any change to the New layout source step, the Default choice, the plan lock or availability rules.
- Running any of the mock's JavaScript in wp-admin.

## Acceptance criteria

1. Given the mock at `~/Downloads/WooCommerce Checkout Templates_v1.1.html`, when `./scripts/run.sh previews` runs, then it writes exactly 11 files, `templates/layouts/<slug>/preview.html`, one for each slug in `LayoutTemplateCatalogue::catalogue()`, and exits 0.
2. Every written `preview.html` contains:
   - no `<script` and no `<noscript`;
   - no element with an id starting `__bundler`;
   - no attribute matching ` on[a-z]+=`;
   - no `{{`;
   - no `src=` or `url(` whose value starts with anything other than `data:` or `#`.

   The script checks this and fails, without writing that file, if any check fails.
3. If a mock template's name matches no catalogue name, or a catalogue slug gets no preview, the script exits non-zero and names it.
4. `GET /yaycheckout/v1/layouts/templates` returns `preview_url` on every entry:
   - when the file exists: `YAYCHECKOUT_PLUGIN_URL . 'templates/layouts/<slug>/preview.html?ver=' . YAYCHECKOUT_VERSION`;
   - when it doesn't: `''`.

   `available` and `locked` are unchanged.
5. In the New layout modal's library step, the dialog is `sm:max-w-5xl` (the source step stays `sm:max-w-2xl`). The grid has 3 columns with a 16px gap (`gap-4`) and scrolls inside `max-h-[60vh]`, so the name field and footer stay in view.
6. In the setup wizard's Start from step, the same picker shows with 3 columns and `max-h-[60vh]`.
7. Each card shows its preview in a 4:3 frame:
   - an `<iframe>` with `sandbox="allow-same-origin"` (no `allow-scripts`), `loading="lazy"`, `tabIndex={-1}`, `aria-hidden="true"` and `pointer-events: none`;
   - 1280px wide, scaled by `frameWidth / 1280` from the top left, where a `ResizeObserver` measures `frameWidth`;
   - its height set to the preview document's `scrollHeight` on `load`.

   The top of the page fills the frame.
8. On card `:hover` or `:focus-visible`, the preview moves up by `pageHeight × scale − frameHeight` with `transition: transform` over `clamp(2s, pageHeight / 1000 × 1.5s, 6s)` `ease-in-out`, so the bottom of the page ends at the bottom of the frame. On leave or blur it returns in `0.4s`. Under `prefers-reduced-motion: reduce` it doesn't move.
9. Locked and Coming soon cards:
   - show their preview at full opacity, with hover-scroll;
   - cannot be selected (`disabled`, `cursor-not-allowed`, no `opacity-50`);
   - show `ProTag` (locked) or the "Coming soon" badge (Coming soon) in the name row.

   Their `title` tooltips are unchanged.
10. Pro: the category chips and the "N templates" count stay, and all templates show as cards in one grid. A filter that hides the current selection still calls `onSelect(null)`.
11. Free shows, in order:
    - the 3 unlocked cards;
    - a row with `ProTag`, "8 more templates" (`_n`) and `UpgradeButton`;
    - the 8 locked cards, at the same size.

    The dashed greyed strip no longer exists.
12. When `previewUrl` is `''` or the iframe fires `error`, the frame shows a `bg-muted` box with the template name centred, and no iframe.
13. While templates load, the picker shows 6 skeleton cards in 3 columns, each a 4:3 box plus a name bar.
14. `TemplateThumbnail.tsx`, `TemplateThumbnailPrimitives.tsx` and `template-thumbnail-sketches.tsx` are deleted, and no file in `apps/` imports them.
15. `pnpm --filter @yaycheckout/admin-page exec tsc --noEmit` passes, and `composer lint` passes on the changed PHP files.
16. End-to-end verification:
    - Run `./scripts/run.sh previews`, then `./scripts/run.sh dev`.
    - In wp-admin → YayCheckout → Layouts → New layout → From library, the dialog is wide with 3 columns of cards, each showing its template's mock page; Classic two-column shows the "Storefront" header, the contact form and the order summary card.
    - Hover Classic two-column: the card scrolls smoothly to the Place order area and returns on leave. DevTools' Network tab shows only `preview.html` requests (no unpkg, no blob scripts), and the iframes' consoles log nothing.
    - Pick it and Create layout: the layout is created as before.
    - Restart dev with `YAYCHECKOUT_PLAN=free ./scripts/run.sh dev` (the Vite server defaults to `pro`): the 8 locked cards show under the Upgrade row at full opacity and can't be selected.
    - Re-run the setup wizard from Settings: Start from shows the same cards.

## Constraints

- Product PHP runs on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. Everything here is Free code and must pass `scripts/check-pro-boundary.sh`. A preview holds only the mock's sample data, so shipping Pro templates' previews in Free puts no real data or endpoint behind an upsell (hard rule 3).
- `preview.html` is generated output: never hand-edited, changed only by re-running `./scripts/run.sh previews`.
- The wire is snake_case (`preview_url`). `lib/api/template.api.ts` converts it to `previewUrl`, and `template.schema.ts`'s wire schema is strict.
- The catalogue stays the only code that builds template file paths (`LayoutTemplateCatalogue::path()`).
- The capture script needs Python 3 and Google Chrome. The Chrome path defaults to `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome` and can be overridden with `--chrome <path>`. Run Python with `-I`, as the skill's scripts are.
- No JavaScript from the mock runs in wp-admin. The iframe sandbox has no `allow-scripts`.

## Approach

**Chosen:** static HTML snapshots of the rendered mock in sandboxed, scaled iframes.

**Architecture:**
- A dev-only Python script renders each mock template once in headless Chrome and saves the rendered DOM without scripts, as `templates/layouts/<slug>/preview.html`. The file is committed and ships with the plugin.
- The catalogue adds each file's URL to the template list.
- The picker shows each preview in a script-less sandboxed iframe at desktop width (1280px), scaled to the card. The iframe's height is the page height, read on `load` (`allow-same-origin` makes it readable).
- Hover-scroll is a CSS transform on the iframe in our own SPA code, so the mock contributes only HTML and CSS.

**Components:**
- `scripts/capture-template-previews.py`. Arguments: `--mock <path>` (default `~/Downloads/WooCommerce Checkout Templates_v1.1.html`) and `--chrome <path>`. It:
  1. runs `extract_mock.py --list` and maps each name to a catalogue slug;
  2. extracts each template into a temp dir and runs Chrome `--headless=new --disable-gpu --virtual-time-budget=8000 --window-size=1280,1800 --dump-dom`;
  3. strips `<script>`, `<noscript>`, `__bundler_*` nodes and `on*` attributes;
  4. runs the checks of criterion 2 and writes the file.

  It reads the slug list by parsing `catalogue()` in `includes/Controllers/LayoutTemplateCatalogue.php` for `'slug' => … 'name' => …` pairs.
- `scripts/run.sh`: a `previews [mock-path]` command and its help line.
- `includes/Controllers/LayoutTemplateCatalogue.php`: `templates()` adds `preview_url`. A private `preview_url( string $slug ): string` uses `path( $slug, 'preview.html' )` and `YAYCHECKOUT_PLUGIN_URL`. The `@return` shape is updated.
- `apps/admin/src/lib/schema/template.schema.ts` and `lib/api/template.api.ts`: wire `preview_url: z.string()`, mapped to `previewUrl` on `Template`. Today `getTemplateLibrary()` returns the parsed wire as-is, since every key was one word; it gains the mapping.
- `apps/admin/src/components/shared/template-picker/TemplatePreview.tsx` (new): the 4:3 frame, the iframe, the scale and height measuring, the hover-scroll CSS variables (`--preview-shift`, `--preview-duration`) and the muted fallback.
- `apps/admin/src/components/shared/template-picker/TemplatePicker.tsx`: card grid, Free and Pro layouts, and the skeleton as in criteria 5–13. It uses `TemplatePreview` in place of `TemplateThumbnail`.
- `apps/admin/src/pages/layouts/new-layout/NewLayoutModal.tsx`: on the library step the dialog width is `sm:max-w-5xl`.
- Deleted: `TemplateThumbnail.tsx`, `TemplateThumbnailPrimitives.tsx`, `template-thumbnail-sketches.tsx`.

**Data and storage:**
- No options, meta or tables.
- New committed files: `templates/layouts/<slug>/preview.html` × 11, about 60 KB each (about 0.7 MB in the zip).
- The folders for the 8 Coming soon slugs hold only `preview.html`. `is_available()` still needs `markup.php` and `design.json`, so their availability doesn't change.
- Uninstall: nothing to remove.

**Integration points:**
- REST `GET /yaycheckout/v1/layouts/templates`: each entry gains `preview_url`. No new hooks, filters or routes.
- Doc updates:
  - CLAUDE.md hard rule 1 adds `templates/layouts/*/preview.html` (rebuilt by `./scripts/run.sh previews`);
  - the `docs/architecture.md` templates paragraph names `preview.html` and `preview_url`;
  - `.claude/skills/build-checkout-template/SKILL.md:116` and `references/checklist.md:14,18` replace "the picker sketch" / `SKETCHES` with re-running `./scripts/run.sh previews` for a new slug.

**UI:** the shared `TemplatePicker` in the New layout modal's library step and the setup wizard's Start from step, built with the SPA's existing stack (Tailwind, shadcn `Badge` and `Skeleton`, `ProTag` and `UpgradeButton`).

**Edge cases and failure modes:**
- Preview file missing (a new slug before the script is re-run): `preview_url` is `''` and the card shows the muted name box (criterion 12).
- The iframe load fails or is blocked: the fallback box.
- `contentDocument` unreadable (for example a host that serves `templates/` with headers that change the origin): the iframe keeps a 1280×960 height and the card shows the top with no scroll.
- Windows or Linux without `-apple-system`: system-font fallback. Accepted; the page shapes are what matters.
- 11 iframes at once: `loading="lazy"` loads only the visible rows, and each file makes no further requests.
- Narrow dialog (wizard, 880px, about 260px cards): scale ≈ 0.2. Text is unreadable, but the layout reads, and hover-scroll shows the whole page.
- Mock updated: re-run the script and commit the diff. A renamed template fails criterion 3 until the catalogue matches.

**Decision record:** None. It adds generated files next to existing template files and changes no architectural choice.

## Alternatives considered

| Option | Why rejected |
|---|---|
| Pre-rendered WebP screenshots with CSS hover-scroll | The user chose HTML snapshots; screenshots are pixels of one render, not the mock's own HTML. |
| Live render of each built layout through preview links | The user chose the mock as the source; Coming soon templates have no markup to render. |
| Shipping the mock bundle and running its JS in the iframe | It loads React from unpkg and a foreign runtime into wp-admin, about 700 KB of JS; no mock JS should run. |
| Keeping the SVG sketches | They don't match the templates, which is the problem being fixed. |
| Big cards in the New layout modal only | The wizard would keep the sketches, so two preview systems to maintain. |
| Keeping Free's greyed locked strip | The user chose the same big cards for locked templates; the preview is the upsell. |
| Capturing every step of multi-step templates | The user chose the initial state only. |

## Rollout

None. The plugin is unreleased (1.0.0). The previews ship in both the Free and Pro zips through the existing `templates/` copy.

## Open questions

None.
