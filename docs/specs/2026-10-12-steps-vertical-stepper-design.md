# Steps Vertical Stepper Layout Design

**Status:** accepted 2026-10-12
**Related:** Design demo template "11 · Material stepper" (`data-screen-label="Vertical stepper"`) in `WooCommerce Checkout Templates_v1.1.html` (design handoff, not in the repo)

## Goal

A merchant can pick a third layout, "Vertical stepper", on the Steps block. Shoppers then see the checkout steps as one vertical timeline, with a numbered badge per step joined by a line, instead of one card per step. The steps behave exactly as the List layout does.

## Context

- `packages/blocks/src/steps/` owns the layout. `block.json` holds the `layout` attribute (`enum: [ "list", "single" ]`, default `list`). `edit.tsx` offers it as the Inspector "Layout" `SelectControl`. `render.php` prints it as `data-layout` on `.yaycheckout-steps`, and today forces any value other than `single` to `list`.
- `packages/blocks/src/steps/style.css` styles every layout. Step defaults sit in `:where()` with zero specificity, so the Step's colour, spacing and border supports and Global Styles override them. State comes from `data-state` (`current`, `locked`, `complete`) on `.yaycheckout-step`, which `step/render.php` sets and `steps/view.ts` changes.
- The rules for `single` and for `data-headings="hidden"` hide `.yaycheckout-step:not([data-state="current"])`. Editor steps have no `data-state`, so the editor wrapper prints no `data-layout` today, and every step stays open in the editor.
- Block spacing reaches the storefront as a fixed `gap: X` rule from `includes/Renderer/BlockGap.php`. CSS can't read that value.
- The step header is a grid (`.yaycheckout-step__header`): a 28px badge (`.yaycheckout-step__index`) in column 1, the title and summary in column 2, and Edit in column 3, with `column-gap: 12px`. The complete badge already uses `::after` for its check mark.
- No layout template in `templates/layouts/` uses `yaycheckout/steps`.

## Scope

**In:**
- A third value `stepper` for the Steps `layout` attribute, labelled "Vertical stepper" in the Inspector, in Free and Pro.
- Stepper styles on the storefront and in the editor canvas.
- In every layout, a step the shopper has already opened stays clickable after they leave it, as in the demo, where any step up to the furthest one reached can be reopened. Added 2026-10-12 after a browser check against the demo.

**Out:**
- Any change to step behaviour in `steps/view.ts` other than reopening a reached step. Also out: the Step block, Step button, Back button and Progress.
- The pill, right-aligned Continue and text Back buttons in the demo. These are button styling, not the layout.
- A white card around the whole stepper by default. Merchants add one with the Steps block's border, background and padding supports.
- Matching the demo's 500 title weight. The stepper keeps the existing 700.
- The demo's 8px space between a badge and the connector.
- Making the connector follow a custom Block spacing (see Edge cases).
- A layout template that uses the stepper.

## Acceptance criteria

1. Given a Steps block, when the merchant opens the Layout control, then it lists "List", "One step" and "Vertical stepper", in that order. Choosing the last one saves `"layout":"stepper"`.
2. Given `layout` `stepper`, when the storefront renders, then `.yaycheckout-steps` has `data-layout="stepper"`. Given a layout value outside `list`, `single` and `stepper`, it has `data-layout="list"`.
3. Given `layout` `stepper` and no Step style overrides, then each `.yaycheckout-step` has no border, no background and no border radius, and has `padding: 0 0 20px`. The current step has no coloured border.
4. Given `layout` `stepper`, then every step except the last shows a 1px `#ddd` vertical line through its badge centre: `inset-inline-start: 13.5px`, from `top: 14px` to `bottom: -26px`. Badges sit above the line (`z-index: 1`), and a locked badge has `background: var(--yaycheckout-color-background, #fff)`, the layout design's page colour. The last step (`.yaycheckout-step:not(:has(~ .yaycheckout-step))`) shows no line.
5. Given `layout` `stepper` and the default 12px Block spacing, then the line is unbroken from the first badge to the last, in every state: all locked except step 1, some complete with a summary, a reopened step.
6. Given `layout` `stepper`, then `.yaycheckout-step__body` has `margin-inline-start: 40px`, so the step's sections line up with its title.
7. Given `layout` `stepper` and "Show step headings" off, then no line shows, and one step at a time shows, as in the other layouts today.
8. Given `layout` `stepper` in the editor, then the canvas shows the line and the indented body, and every step stays open. Given `list` or `single` in the editor, the canvas looks as it does today.
9. Given `layout` `list` or `single`, then the storefront looks and behaves exactly as it does before this change, except for criterion 11.
10. End-to-end verification: `pnpm build:blocks` succeeds. Then, on yaycheckout.test, a checkout layout with a four-step Steps block set to "Vertical stepper" shows the following:
    - On load: step 1 open with a blue `1` badge, and steps 2 to 4 locked with grey badges, all joined by one line.
    - After filling step 1 and pressing Continue: step 1 shows a ✓, its summary and Edit, and step 2 opens.
    - After pressing Edit: step 1 reopens.

    Switching the block back to "List" brings back the card look. `composer lint -- packages/blocks/src/steps/render.php` passes.

11. In any layout, given the shopper has reached step 4 and then reopened step 1, when they click step 4's header, then step 1 is checked as it is today, and if it passes, step 4 becomes `current`. A step that has never been opened stays locked, and clicking it does nothing. A locked step that has been opened before has `data-reached="true"` and `cursor: pointer`, and its title keeps the text colour, not the muted locked colour.

## Constraints

- PHP must run on PHP 7.4 (CLAUDE.md hard rule 4).
- Edit only the sources in `packages/blocks/src/steps/`, never `assets/dist/blocks/` (hard rule 1).
- The layout is in Free and Pro: no `Plan::is_pro()` check, nothing under `includes/Pro/`.
- Stepper defaults that a Step support can set (border, background, radius, padding) go in `:where()`, so Step supports and Global Styles still override them.
- Use logical properties (`margin-inline-start`, `inset-inline-start`) so RTL mirrors the stepper.
- New UI strings are translatable in the `yaycheckout` text domain: `__( 'Vertical stepper', 'yaycheckout' )`.

## Approach

**Chosen:** a CSS-only layout behind `data-layout="stepper"`, with a fixed 12px connector overshoot.

**Architecture:** `stepper` is one more value of the existing `layout` attribute. `render.php` passes it through as `data-layout`, and a block in `style.css`, scoped to `.yaycheckout-steps[data-layout="stepper"]`, restyles the existing step markup. Behaviour is untouched, because `view.ts` never reads `data-layout`. Each step draws its own connector segment, which runs from its badge centre, past its bottom edge and through the 12px gap, to the next badge's centre. The opaque badges hide the overlap, so the line looks unbroken however tall a header is.

**Components:**
- `packages/blocks/src/steps/block.json`: `layout.enum` becomes `[ "list", "single", "stepper" ]`. The default stays `list`.
- `packages/blocks/src/steps/edit.tsx`: a third `SelectControl` option, `{ label: __( 'Vertical stepper', 'yaycheckout' ), value: 'stepper' }`. `useBlockProps` gets `'data-layout': layout` for every layout. `style.css` scopes the `single` and hidden-headings hide rule to `.yaycheckout-step[data-state]:not([data-state="current"])`, so editor steps, which have no `data-state`, stay open. Nothing changes on the storefront, where `step/render.php` always prints `data-state`.
- `packages/blocks/src/steps/render.php`: the line that sets `$yaycheckout_layout` becomes a whitelist: `in_array( $attributes['layout'] ?? '', [ 'list', 'single', 'stepper' ], true )`, falling back to `list`.
- `packages/blocks/src/steps/style.css`: the stepper block:
  - Step defaults in `:where()`: `border: 0; border-radius: 0; background: none; padding: 0 0 20px`.
  - `position: relative` on the step.
  - `::before` for the connector, with values as in criterion 4.
  - No connector on the last step, or when `data-headings="hidden"`.
  - Badge `position: relative; z-index: 1`. A locked badge gets `background: var(--yaycheckout-color-background, #fff)`.
  - Body `margin-inline-start: 40px`.
  - A `ponytail:` comment on the overshoot naming its ceiling and upgrade path.

**Reached steps:** `steps/view.ts` `setState()` sets `data-reached="true"` whenever a step becomes `current` or `complete`. That covers step 1, which starts current from the server and is first marked when it completes. `onClick()` reopens any step with `data-reached` that is not `current`. This one flag replaces the old `complete`-only check. `reopen()` and `park()` are unchanged, so leaving the open step still re-checks it. `style.css` gives a reached locked step `cursor: pointer` on its header and drops the muted title colour.

**Data and storage:** none new. The value lives in the block's `layout` attribute in the layout post content. Existing layouts keep `list` or `single`. Nothing to migrate or remove on uninstall.

**Integration points:** none. No hooks, REST fields or shared code change. `includes/Renderer/BlockGap.php` is unchanged.

**UI:** the Steps block's Inspector "Steps" panel, Layout `SelectControl` (Gutenberg components). Output is the existing `render.php` markup.

**Edge cases and failure modes:**
- **Block spacing above 12px:** the line breaks for `gap − 12px` between steps. This is an accepted ceiling, noted in the `ponytail:` comment. The upgrade is to have `BlockGap` also print a CSS variable that the overshoot can use.
- **Block spacing below 12px:** the line runs further into the next badge, which hides it.
- **The editor's block appender after the last step:** `:not(:has(~ .yaycheckout-step))` still finds the last step, where `:last-child` would not.
- **Page background from the layout design:** the locked badge takes `--yaycheckout-color-background`, so it matches the page. A Step background colour or a gradient page still shows the badge as a disc of the page colour (white for a gradient). Accepted, because the alternative is a line showing through the badge.
- **"Show step headings" off:** the header is visually hidden, so the connector is hidden too. The existing rule shows one step at a time.
- **Last step completed (Continue on the last step):** the existing rule keeps it on screen. It has no connector.
- **RTL:** the logical properties mirror the badge column, the line and the indent.

**Decision record:** none. No architectural choice is made or reversed.

## Alternatives considered

| Option | Why rejected |
|---|---|
| `BlockGap` prints a CSS variable that the connector uses | Changes a helper that every block with blockGap uses, for one block's connector. |
| The stepper forces `gap: 0` and spaces steps with padding | The Steps Block spacing control would do nothing in this layout. |
| Print `data-layout` in the editor only for `stepper` | It works around the `single` hide rule instead of fixing it. Scoping that rule to `[data-state]` is one selector, and lets every layout print its value (changed by /yaytidy, 2026-10-12). |
| Keep the demo's 8px space around the badge with a background-coloured halo | The halo shows when a Step has a background colour. |

## Rollout

None. It is a new option, and existing layouts keep their saved `layout` value, so nothing changes until a merchant picks "Vertical stepper".

## Open questions

None.
