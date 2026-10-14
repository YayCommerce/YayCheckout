# Template Picker Previews Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** active
**Goal:** Replace the template picker's SVG sketches with large cards that show each template's real mock page in a sandboxed iframe that scrolls on hover.
**Spec:** [docs/specs/2026-10-14-template-picker-previews-design.md](../specs/2026-10-14-template-picker-previews-design.md)
**Architecture:**
- A dev-only Python script renders each mock template once in headless Chrome and commits the rendered DOM, without scripts, as `templates/layouts/<slug>/preview.html`.
- The catalogue adds the file's URL to `GET /layouts/templates`.
- The SPA shows it in a script-less iframe, scaled from 1280px, and the hover-scroll is a CSS transform in our own code.
- Chosen over screenshots by the user; the mock's JS never reaches wp-admin.

**Touches:**
- `scripts/`
- `includes/Controllers/LayoutTemplateCatalogue.php` (shared: consumed by `LayoutRestController::get_templates()` at line 286 and by `assert_available()`)
- `apps/admin/src/lib/{schema,api}/template.*`
- `apps/admin/src/components/shared/template-picker/` (shared: consumed by `pages/layouts/new-layout/LibraryStep.tsx` and `components/setup-wizard/StartFromStep.tsx`)
- `apps/admin/src/pages/layouts/new-layout/NewLayoutModal.tsx`
- `templates/layouts/*/preview.html` (new, generated)
- CLAUDE.md, `docs/architecture.md`, `.claude/skills/build-checkout-template/`

**Tests:** none. CLAUDE.md's test rule warrants tests only for logic that silently breaks a shopper's checkout, or for a fixed bug. This change is admin-only and visual. Every task carries an exact manual check instead.

Branch: `trace/feat/template-picker-previews` from `dev/edd`. One PR into `dev/edd`.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro in exactly one place, the guarded `boot_pro()`. Everything here is Free code and must pass `scripts/check-pro-boundary.sh` (run by `composer lint`).
- Never hand-edit generated output: `vendor/`, `vendor-prefixed/`, `node_modules/`, `assets/dist/`, `release/`, and now `templates/layouts/*/preview.html` (rebuilt by `./scripts/run.sh previews`).
- Everything on the wire is snake_case (`preview_url`). The SPA's TypeScript is camelCase. They meet only in `apps/admin/src/lib/api/template.api.ts`, which parses with a strict `*WireSchema` from `apps/admin/src/lib/schema/` and converts.
- The catalogue is the only code that builds template file paths (`LayoutTemplateCatalogue::path()`).
- No JavaScript from the mock runs in wp-admin. The iframe sandbox never includes `allow-scripts`.
- The capture script runs with `python3 -I` and needs Google Chrome. The default Chrome path is `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`, and `--chrome <path>` overrides it.
- Comments follow `docs/conventions.md` § Comments: a docblock opens with what the code does for its caller, and an inline comment only carries a why. Never cite a doc, plan or spec.
- Commit messages are Conventional Commits, `type(scope): summary`, with CLAUDE.md's trailer. Stage files by name.
- The admin lint fails on `dev/edd` (docs/known-issues.md). Check only changed files: `npx --no-install eslint <files>` and `npx --no-install prettier --check <files>` from `apps/admin`, with no new findings.

## Review focus

- **The rendered DOM still references the network** (unpkg React, `blob:` scripts, an image URL). A preview must make no requests. The script's checks refuse the file (Task 1, Step 3).
- **The mock renders asynchronously and Chrome dumps too early.** The page would be half-built or show the bundler's "requires JavaScript" placeholder. Each file must contain its template's visible content (Task 1, Step 3: grep for each name).
- **`contentDocument` is null or the height reads 0** before layout. The card must keep the 1280×960 fallback height, never collapse to 0px (Task 3, Step 5).
- **Disabled card buttons and hover.** Locked and Coming soon cards are `disabled`, yet the merchant expects them to scroll on hover too (Task 3, Step 5).
- **The New layout modal resets on close.** Widening the dialog must not move the `step` state out of `NewLayoutForm`, or reopening would land on the library step (Task 3, Step 5).

---

### Task 1: Capture script and the 11 previews

**Files:**
- Create: `scripts/capture-template-previews.py`
- Create: `templates/layouts/<slug>/preview.html` × 11 (generated)
- Modify: `scripts/run.sh` (header comment lines 6–15, `cmd_help()` at 465, `main()` dispatch at 495)
- Modify: `CLAUDE.md` (hard rule 1)
- Modify: `.claude/skills/build-checkout-template/SKILL.md:116`
- Modify: `.claude/skills/build-checkout-template/references/checklist.md:14,18`
- Add: `docs/specs/2026-10-14-template-picker-previews-design.md`, this plan

**Interfaces:**
- Consumes: `.claude/skills/build-checkout-template/scripts/extract_mock.py`, called by subprocess with `--mock <path> --list` (lines `<id>  <name>`) and `--mock <path> <id> --out <dir>` (writes `t<id>.html`).
- Produces: `templates/layouts/<slug>/preview.html` for every slug in `LayoutTemplateCatalogue::catalogue()`, and the `./scripts/run.sh previews [mock-path]` command.

- [ ] **Step 1: Write `scripts/capture-template-previews.py`**

CLI: `capture-template-previews.py [--mock PATH] [--chrome PATH]`. Defaults:
- `--mock`: `~/Downloads/WooCommerce Checkout Templates_v1.1.html`
- `--chrome`: `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`

Use only the standard library.

What it does:
1. Read the catalogue's `(slug, name)` pairs from `includes/Controllers/LayoutTemplateCatalogue.php` with the regex `'slug'\s*=>\s*'([^']+)',\s*'name'\s*=>\s*'([^']+)'`.
2. Run `extract_mock.py --list` and map each mock name to a slug by exact name.
3. Exit 1 and name the problem when a mock name has no slug, or a slug gets no mock template (spec criterion 3).
4. For each template, inside a `tempfile.TemporaryDirectory()`:
   1. extract it;
   2. run Chrome with `--headless=new --disable-gpu --hide-scrollbars --user-data-dir=<tmp>/profile --virtual-time-budget=8000 --window-size=1280,1800 --dump-dom file://<tmp>/t<id>.html`, with stdout captured and stderr discarded (Chrome prints allocator noise there);
   3. strip `<script\b.*?</script>` and `<noscript\b.*?</noscript>` (DOTALL), any element whose `id` starts with `__bundler`, and every ` on[a-z]+="[^"]*"` attribute.
5. Check the result against spec criterion 2:
   - no `<script` or `<noscript`;
   - no `id="__bundler`;
   - no ` on[a-z]+=`;
   - no `{{`;
   - every `src="…"` and `url(…)` value (quotes optional) starts with `data:` or `#`.

   On a failure, print the slug and the first offending match, skip writing, and exit 1 at the end.
6. Write each file that passes to `templates/layouts/<slug>/preview.html` (creating the folder) with a leading `<!-- Generated by scripts/capture-template-previews.py from the checkout-templates mock. Do not edit; re-run ./scripts/run.sh previews. -->`.
7. Print one line per slug with its size in KB.

- [ ] **Step 2: Add the `previews` command to `scripts/run.sh`**

1. Add `cmd_previews()`. It runs `python3 -I "$PLUGIN_DIR/scripts/capture-template-previews.py"`, plus `--mock "$1"` when an argument is given.
2. Add the dispatch line `previews) cmd_previews "$@" ;;`.
3. Add the help line `previews [mock-path]          Capture templates/layouts/*/preview.html from the HTML mock`.
4. Add the matching header comment line.

- [ ] **Step 3: Generate and verify**

Run: `./scripts/run.sh previews`

Expected:
- 11 lines, one per slug, each about 50–80 KB, and exit 0.
- `ls templates/layouts/*/preview.html | wc -l` prints `11`.
- `grep -lE '<script|<noscript|__bundler|\{\{| on[a-z]+=' templates/layouts/*/preview.html` prints nothing.
- `grep -oE '(src="|url\()[^d#"][^"]{0,30}' templates/layouts/*/preview.html` prints nothing.
- For each slug, its catalogue name appears in its file. For example, `grep -c 'Contact information' templates/layouts/classic-two-column/preview.html` is ≥ 1, and `grep -c 'requires JavaScript' templates/layouts/*/preview.html` is 0 for every file.
- Opening `templates/layouts/classic-two-column/preview.html` in Chrome with JS disabled (DevTools → Command menu → "Disable JavaScript") looks like the mock: the "Storefront" header, Contact information, and the Order summary card.

- [ ] **Step 4: Update the docs that name sketches or generated output**

- **CLAUDE.md hard rule 1:** add `templates/layouts/*/preview.html` to the list, and add "a template's `preview.html` is rebuilt with `./scripts/run.sh previews`".
- **`.claude/skills/build-checkout-template/SKILL.md:116`:** "**New slug:** also add the catalogue entry, then run `./scripts/run.sh previews` so the picker has its preview."
- **`references/checklist.md:14`:** replace "and its thumbnail (`SKETCHES` …)" with "and its preview (`templates/layouts/<slug>/preview.html`, from `./scripts/run.sh previews`)".
- **`references/checklist.md:18`:** replace the `SKETCHES`/`FALLBACK` bullet with "a `preview.html`; without one the card shows the template's name on a plain box."
- **`./scripts/run.sh help`:** already updated in Step 2.

Verify: `./scripts/check-harness.sh` reports no new findings (it fails on `dev/edd` with 24 lines of missing backticked paths; the sorted output must be identical), and `git grep -n "SKETCHES\|template-thumbnail" -- .claude CLAUDE.md` prints nothing.

- [ ] **Step 5: Commit**

Stage:
- `scripts/capture-template-previews.py`
- `scripts/run.sh`
- `templates/layouts/*/preview.html` (by name, all 11)
- `CLAUDE.md`
- `.claude/skills/build-checkout-template/SKILL.md`
- `.claude/skills/build-checkout-template/references/checklist.md`
- `docs/specs/2026-10-14-template-picker-previews-design.md`
- `docs/plans/2026-10-14-template-picker-previews.md`

Message: `feat(templates): capture script-free mock previews for every template`

### Task 2: `preview_url` on the template list

**Files:**
- Modify: `includes/Controllers/LayoutTemplateCatalogue.php` (`templates()` and its `@return` shape at lines 21–31; new private method)
- Modify: `apps/admin/src/lib/schema/template.schema.ts`
- Modify: `apps/admin/src/lib/api/template.api.ts`
- Modify: `docs/architecture.md:46` (the templates paragraph)

**Interfaces:**
- Consumes: Task 1's `preview.html` files.
- Produces:
  - Wire field `preview_url: string` on each entry of `GET /yaycheckout/v1/layouts/templates`.
  - SPA type `Template = { slug: string; name: string; category: string; locked: boolean; available: boolean; previewUrl: string }`. `templateSchema`/`templateLibrarySchema` keep their export names for `Template` and `TemplateLibrary` consumers.

- [ ] **Step 1: Add `private static function preview_url( string $slug ): string` to `LayoutTemplateCatalogue`**

It returns `YAYCHECKOUT_PLUGIN_URL . 'templates/layouts/' . $slug . '/preview.html?ver=' . YAYCHECKOUT_VERSION` when `is_file( self::path( $slug, 'preview.html' ) )`, else `''`.

`templates()` sets `$template['preview_url'] = self::preview_url( $template['slug'] );`. Add `preview_url: string` to the `@return` array shape. The class docblock gains "and preview.html, the picker's preview, which availability ignores".

- [ ] **Step 2: Wire schema and conversion**

In `template.schema.ts`:
- `templateWireSchema = z.object({ slug, name, category, locked, available, preview_url: z.string() }).strict()`;
- `templateLibraryWireSchema = z.object({ templates: z.array(templateWireSchema) }).strict()`;
- `Template` is a hand-written camelCase type with `previewUrl: string`, and `TemplateLibrary = { templates: Template[] }`.

Keep the "Mirrors `LayoutTemplateCatalogue::templates()`" comment on the wire schema. Remove `templateSchema` and `templateLibrarySchema`; `git grep` shows their only consumer is `template.api.ts`.

In `template.api.ts`, add `function templateFromWire(wire: TemplateWire): Template`. It maps `preview_url` to `previewUrl` and copies the rest. `getTemplateLibrary()` parses with `templateLibraryWireSchema` and maps `templates` through it, following `layoutFromWire` in `layout.api.ts`.

- [ ] **Step 3: Update `docs/architecture.md:46`**

After "A template missing either file is listed as Coming soon (`available: false`) and refused.", add: "Each template also has a `preview.html`, the mock's page with its scripts stripped, captured by `./scripts/run.sh previews`; `templates()` lists its URL as `preview_url` (`''` without one), and the picker shows it in a script-less sandboxed iframe. Availability ignores it."

- [ ] **Step 4: Verify**

Run:
```
php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/DmdPSy05S/mysql/mysqld.sock" /opt/homebrew/bin/wp eval 'foreach ( YayCheckout\Controllers\LayoutTemplateCatalogue::templates( true ) as $t ) { echo $t["slug"], " ", $t["preview_url"], "\n"; }'
```
(Match the socket's run id by `siteurl` if it changed.)

Expected: 11 lines, each `http://yaydevs.local/wp-content/plugins/yaycheckout/templates/layouts/<slug>/preview.html?ver=1.0.0`.

Then:
- `curl -sI "http://yaydevs.local/wp-content/plugins/yaycheckout/templates/layouts/classic-two-column/preview.html"` returns `200` with `Content-Type: text/html`.
- `composer lint -- includes/Controllers/LayoutTemplateCatalogue.php` exits 0.
- `npx --no-install tsc --noEmit` from `apps/admin` exits 0. The picker still compiles because it reads no new field yet.

- [ ] **Step 5: Commit**

Stage:
- `includes/Controllers/LayoutTemplateCatalogue.php`
- `apps/admin/src/lib/schema/template.schema.ts`
- `apps/admin/src/lib/api/template.api.ts`
- `docs/architecture.md`

Message: `feat(templates): list each template's preview URL`

### Task 3: Large preview cards in the picker

**Files:**
- Create: `apps/admin/src/components/shared/template-picker/TemplatePreview.tsx`
- Modify: `apps/admin/src/components/shared/template-picker/TemplatePicker.tsx`
- Modify: `apps/admin/src/pages/layouts/new-layout/NewLayoutModal.tsx:31,90`
- Delete:
  - `apps/admin/src/components/shared/template-picker/TemplateThumbnail.tsx`
  - `apps/admin/src/components/shared/template-picker/TemplateThumbnailPrimitives.tsx`
  - `apps/admin/src/components/shared/template-picker/template-thumbnail-sketches.tsx`

**Interfaces:**
- Consumes: Task 2's `Template.previewUrl`.
- Produces: `export function TemplatePreview({ template }: { template: Template }): JSX.Element`, used only by `TemplatePicker`. `TemplatePicker`'s props (`selectedSlug`, `onSelect`) are unchanged, so `LibraryStep` and `StartFromStep` need no edits.

- [ ] **Step 1: Write `TemplatePreview.tsx`**

Constants: `PAGE_WIDTH = 1280` and `FALLBACK_PAGE_HEIGHT = 960`.

Structure:
- **Frame:** a `div`, `relative aspect-4/3 w-full overflow-hidden rounded-sm border bg-muted`. A `ResizeObserver` hook reads its `clientWidth`, and `scale = frameWidth / PAGE_WIDTH`.
- **Mover:** inside the frame, a `div` with `transform: translateY(0)`, `transition: transform 400ms ease-in-out`. When the card (`group`, see Step 2) is hovered or `focus-visible`, it moves to `translateY(calc(var(--preview-shift) * -1))` over `var(--preview-duration)`. Use `group-hover:` and `group-focus-visible:` with arbitrary values, and `motion-reduce:` to keep it at `translateY(0)` with no transition.
- **Iframe:** inside the mover, `<iframe src={template.previewUrl} title={template.name} sandbox="allow-same-origin" loading="lazy" tabIndex={-1} aria-hidden="true" className="pointer-events-none origin-top-left border-0">`, styled `width: 1280px; height: pageHeight px; transform: scale(scale)`.
- **Height:** `pageHeight` starts at `FALLBACK_PAGE_HEIGHT`. `onLoad` sets it to `iframe.contentDocument?.documentElement.scrollHeight` when that is > 0.
- **CSS variables**, set on the frame:
  - `--preview-shift: max(0, pageHeight × scale − frameWidth × 3/4)` in px;
  - `--preview-duration: clamp(2, pageHeight / 1000 × 1.5, 6)` in s, computed in TS.
- **Fallback:** when `template.previewUrl === ''` or the iframe fires `onError`, render no iframe. Show the template name centred in `text-muted-foreground text-xs` on the `bg-muted` frame.

Docblock: one line on what it renders, plus the why for `allow-same-origin` without `allow-scripts`: it lets us read the page height, while nothing in the preview can run.

- [ ] **Step 2: Rework `TemplateTile` in `TemplatePicker.tsx`**

- Replace the thumbnail block with `<TemplatePreview template={template} />`, and keep `ComingSoonBadge` absolutely placed on its corner.
- The button gets `group`.
- Unpickable cards keep `disabled` and `cursor-not-allowed`, and lose `opacity-50`.

The name row always holds the name, plus `ProTag` when `template.locked`. Drop the `withProTag` prop, since every grid now shows it. `tileTitle()` is unchanged.

Disabled buttons still match `:hover` in Chromium, Firefox and Safari, so `group-hover` scrolls locked cards. Verify this in Step 5.

- [ ] **Step 3: Grids and skeleton**

- **Wrapper:** the picker's outer `div` keeps its border. Its card area is a single `max-h-[60vh] overflow-y-auto` scroll container holding the grid(s).
- **Card grid:** `grid grid-cols-3 gap-4 content-start` everywhere.
- **`ProTemplateGrid`:** the category chips stay above the scroll container, and the `h-84` fixed height and `grid-cols-5` go away.
- **`FreeTemplateGrid`, in order:**
  1. the unlocked cards' grid;
  2. a row `flex items-center gap-2` with `<ProTag />`, the existing `_n( 'more template', 'more templates' … )` text and `<UpgradeButton />`;
  3. the locked cards as `TemplateTile`s in the same 3-column grid. `onSelect` is passed but never fires, because they are disabled.

  The dashed `opacity-45` strip is removed.
- **`PickerSkeleton`:** 6 cards in `grid grid-cols-3 gap-4`, each a 4:3 `Skeleton` plus an `h-4 w-2/3` bar, for both plans.

- [ ] **Step 4: Widen the New layout dialog on the library step only**

In `NewLayoutModal.tsx`, the `<form>` gets `data-step={step}`. `DialogContent`'s className becomes `sm:max-w-2xl sm:has-data-[step=library]:max-w-5xl`.

The `step` state stays in `NewLayoutForm`, so Radix's unmount on close still resets it. Delete the three sketch files.

Verify: `git grep -n "TemplateThumbnail\|template-thumbnail-sketches\|SKETCHES" -- apps` prints nothing.

- [ ] **Step 5: Verify in wp-admin**

Run `./scripts/run.sh dev` (it may rewrite `composer.json` and `scoper.inc.php`; check `git status` before committing). Open `http://yaydevs.local/wp-admin/admin.php?page=yaycheckout`, go to Layouts → New layout → From library.

- **Dialog and grid:** the dialog is about 1024px wide, with 3 columns of cards. Each shows its template's mock page top, and Classic two-column shows "Storefront", Contact information and the Order summary card. The name field and footer stay visible while the grid scrolls.
- **Network:** DevTools Network, filtered on "Doc", shows only `preview.html?ver=1.0.0` requests. There are no unpkg or `blob:` requests, and each iframe's console (frame selector) is empty.
- **Hover:**
  - Hovering Classic two-column scrolls it smoothly to Place order in about 2–6 s, and leaving returns it in 0.4 s.
  - Tabbing to a card does the same.
  - With DevTools → Rendering → `prefers-reduced-motion: reduce`, hovering doesn't move.
- **Unpickable cards:** hovering Split express (Coming soon) scrolls it at full opacity, with the Coming soon badge, and clicking does nothing.
- **Fallback:** temporarily renaming `templates/layouts/live-receipt/preview.html` and reloading shows the "Live receipt" name box on a muted frame, with no iframe. Rename it back.
- **Height fallback:** in the Elements panel, an iframe whose `contentDocument` is unavailable keeps `height: 960px`, never `0px`. Check by forcing `sandbox=""` on one iframe in DevTools: it stays 960px tall and doesn't scroll on hover.
- **Reset on close:** close the dialog and click New layout again. It opens on the source step at the narrow width.
- **Create:** pick Classic two-column and Create layout. The toast says "Layout created.", as before.
- **Free plan:** stop dev and run `YAYCHECKOUT_PLAN=free ./scripts/run.sh dev`. The library shows 3 unlocked cards, the Pro row with "8 more templates" and Upgrade, then 8 locked cards at full opacity with Pro tags. They scroll on hover and can't be selected.
- **Setup wizard:** Settings → re-run setup wizard → Start from shows the same 3-column cards in the 880px dialog. The default pick is Classic two-column.

Then:
- `npx --no-install tsc --noEmit` from `apps/admin` exits 0.
- `npx --no-install eslint src/components/shared/template-picker src/pages/layouts/new-layout/NewLayoutModal.tsx src/lib/api/template.api.ts src/lib/schema/template.schema.ts` and the same `prettier --check` both report no findings.

- [ ] **Step 6: Commit**

Stage:
- `apps/admin/src/components/shared/template-picker/TemplatePreview.tsx`
- `apps/admin/src/components/shared/template-picker/TemplatePicker.tsx`
- `apps/admin/src/pages/layouts/new-layout/NewLayoutModal.tsx`
- the three deleted sketch files

Message: `feat(admin): show template previews as large hover-scroll cards`

### Task 4: End-to-end verification

Spec criterion 16, after Tasks 1–3 are on the branch:

- [ ] **Step 1:** Run `./scripts/run.sh previews`. Expected: exit 0, and `git status --short templates/` shows no change, so the committed previews match a fresh capture.
- [ ] **Step 2:** Run `./scripts/run.sh dev` and repeat Task 3 Step 5's Pro checks end to end: wide dialog, 3 columns, Classic two-column hover-scrolls to Place order, Network shows only `preview.html`, and Create layout succeeds.
- [ ] **Step 3:** Confirm the zip will carry the previews without running a release (`.claude/rules/release.md`: run `release` only to cut one): `grep -n 'cp -r "$PLUGIN_DIR/templates"' scripts/run.sh` prints the copy line in `cmd_release`, and its strip step removes nothing under `templates/`.
- [ ] **Step 4:** When the user asks, push and open the PR into `dev/edd`, titled `feat(admin): template picker previews from the mock`. In the description, link the spec and list the manual checks run.
