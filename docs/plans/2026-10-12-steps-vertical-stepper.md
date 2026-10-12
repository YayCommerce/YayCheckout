# Steps Vertical Stepper Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-12
**Goal:** Add a third Steps layout, "Vertical stepper" (`stepper`), that draws the steps as one timeline with a connector between badges and keeps List's behaviour.
**Spec:** `docs/specs/2026-10-12-steps-vertical-stepper-design.md`
**Architecture:** `stepper` is one more value of the existing `layout` attribute. `render.php` prints it as `data-layout`, and a scoped block in `style.css` restyles the existing step markup, so `view.ts` and the Step blocks stay unchanged. The connector extends a fixed 12px past each step, the default gap, instead of reading Block spacing, which would mean changing the shared `BlockGap` helper.
**Touches:** `packages/blocks/src/steps/block.json`, `packages/blocks/src/steps/edit.tsx`, `packages/blocks/src/steps/render.php`, `packages/blocks/src/steps/style.css`, `packages/blocks/src/steps/view.ts` (Task 3, every layout). No shared code.
**Tests:** none. The change is a block option and CSS, and none of it is logic the CLAUDE.md test rule lists. Each task has an exact manual check instead.

## Global constraints

- Product PHP must run on PHP 7.4: no enums, `match`, `readonly`, promoted constructor properties, `?->`, named arguments or union types.
- Free names Pro only in `boot_pro()`. The layout is in Free and Pro: no `Plan::is_pro()` check, nothing under `includes/Pro/`. The code must pass `scripts/check-pro-boundary.sh`, which `composer lint` runs.
- Never hand-edit `assets/dist/`. Rebuild with `pnpm build:blocks`. Edit `render.php` in `packages/blocks/src/steps/`.
- Every new string uses the `yaycheckout` text domain: `__( 'Vertical stepper', 'yaycheckout' )`.
- An added attribute value needs a fallback in `render.php`, because stored layouts keep the old markup (`.claude/rules/blocks.md`).
- Stepper defaults that a Step support can set (border, background, radius, padding) go in `:where()`, so Step supports and Global Styles still override them.
- Use logical properties (`margin-inline-start`, `inset-inline-start`) so RTL mirrors the stepper.
- Never test edits on an existing draft layout, because autosave writes them. Create a new draft for every editor check.

## Review focus

- **The editor canvas with `single` or `list`:** every step stays open, as today. The `single` hide rule must skip editor steps, which have no `data-state`. Checked in Task 1 Step 3.
- **The block appender after the last editor step:** the last step still shows no connector. Checked in Task 2 Step 3.
- **A completed step, whose header is taller because of its summary:** the connector stays unbroken from badge to badge. Checked in Task 2 Step 4.
- **"Show step headings" off:** no connector shows, and one step shows at a time. Checked in Task 2 Step 4.

---

### Task 1: The `stepper` layout value

**Files:**
- Modify: `packages/blocks/src/steps/block.json` (`attributes.layout.enum`)
- Modify: `packages/blocks/src/steps/edit.tsx` (`useBlockProps` call and the Layout `SelectControl` options)
- Modify: `packages/blocks/src/steps/render.php:19`
- Modify: `packages/blocks/src/steps/style.css` (the `single` and hidden-headings hide rule)

**Interfaces:**
- Consumes: nothing.
- Produces: `data-layout="stepper"` on `.yaycheckout-steps`, on the storefront and in the editor. Task 2 styles that selector.

- [ ] **Step 1: Add the value**

`block.json`: `"enum": [ "list", "single", "stepper" ]`. The default stays `"list"`.

`edit.tsx`: add `{ label: __( 'Vertical stepper', 'yaycheckout' ), value: 'stepper' }` as the third option, after "One step". Pass `'data-layout': layout` to `useBlockProps`. In `style.css`, scope the `single` and hidden-headings hide rule to `.yaycheckout-step[data-state]:not([data-state="current"])`, so editor steps, which have no `data-state`, stay open.

`render.php` line 19: `$yaycheckout_layout = in_array( $attributes['layout'] ?? '', [ 'list', 'single', 'stepper' ], true ) ? $attributes['layout'] : 'list';`

- [ ] **Step 2: Build and lint**

Run: `pnpm build:blocks`
Expected: the build finishes with no errors.

Run: `composer lint -- packages/blocks/src/steps/render.php`
Expected: no errors.

- [ ] **Step 3: Verify on yaycheckout.test**

Action: in a new draft layout with a Steps block, open Layout.
Expected: the options are "List", "One step", "Vertical stepper", in that order.

Action: choose "Vertical stepper", then inspect the canvas wrapper.
Expected: `.yaycheckout-steps` has `data-layout="stepper"`, and all steps are open.

Action: switch to "One step", then to "List".
Expected: the wrapper has `data-layout="single"`, then `data-layout="list"`, and all steps stay open.

Action: preview with "Vertical stepper".
Expected: the storefront `.yaycheckout-steps` has `data-layout="stepper"`, and it still looks like List, since the CSS comes in Task 2.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/steps/block.json`, `packages/blocks/src/steps/edit.tsx`, `packages/blocks/src/steps/render.php`, `packages/blocks/src/steps/style.css`
Message: `feat(blocks): add Vertical stepper layout value to Steps`

### Task 2: Stepper styles

**Files:**
- Modify: `packages/blocks/src/steps/style.css` (new block after the `data-headings="hidden"` rules)

**Interfaces:**
- Consumes: `data-layout="stepper"` from Task 1.
- Produces: nothing other tasks use.

- [ ] **Step 1: Write the stepper block in `style.css`**

Every selector is scoped under `.yaycheckout-steps[data-layout="stepper"]`. Values are from spec criteria 3 to 7:

- The step defaults, in `:where( .yaycheckout-steps[data-layout="stepper"] .yaycheckout-step )`: `border: 0; border-radius: 0; background: none; padding: 0 0 20px`.
  - The existing `:where(.yaycheckout-step[data-state="current"])` border colour must not show. With `border: 0` it doesn't, so check it.
- The step has `position: relative`.
- The connector is `.yaycheckout-step::before`:
  - `content: ""; position: absolute; inset-inline-start: 13.5px; top: 14px; bottom: -26px; width: 1px; background: #ddd`.
  - Put a `ponytail:` comment here naming the ceiling and the upgrade: a Block spacing above 12px breaks the line, and `BlockGap` printing a gap variable would fix it.
- No connector on `.yaycheckout-step:not(:has(~ .yaycheckout-step))::before`, or when the wrapper also has `[data-headings="hidden"]`.
- `.yaycheckout-step__index` has `z-index: 1`. It is already `position: relative`.
- The locked badge, `.yaycheckout-step[data-state="locked"] .yaycheckout-step__index`, has `background: var(--yaycheckout-color-background, #fff)`.
- `.yaycheckout-step__body` has `margin-inline-start: 40px`.

- [ ] **Step 2: Build**

Run: `pnpm build:blocks`
Expected: the build finishes with no errors.

- [ ] **Step 3: Verify in the editor**

Action: in a new draft layout, set a four-step Steps block to "Vertical stepper", and select it so the appender shows.
Expected:
- No card around any step.
- A line runs through badges 1 to 4 and stops at badge 4. There is no line under the last step, even with the appender showing.
- Each step's sections start in line with its title.

Action: switch to "List".
Expected: the card look is back, unchanged from before.

- [ ] **Step 4: Verify on the storefront (spec criterion 10)**

Action: publish or preview the layout with "Vertical stepper".
Expected:
- On load: step 1 is open with a blue `1` badge, and steps 2 to 4 are locked with grey badges on a white fill, all joined by one unbroken line.
- After filling step 1 and pressing Continue: step 1 shows ✓, its summary and Edit, and step 2 opens. The line stays unbroken past the taller completed header.
- After pressing Edit on step 1: step 1 reopens.

Action: turn "Show step headings" off and preview again.
Expected: only the open step shows, and no line is visible.

Action: set the layout to "List", then to "One step", and preview each.
Expected: each looks and behaves as before this change.

- [ ] **Step 5: Commit**

Stage: `packages/blocks/src/steps/style.css`
Message: `feat(blocks): style the Steps Vertical stepper layout`

### Task 3: Reopen a reached step (every layout)

Added 2026-10-12 after a browser check against the demo: spec criterion 11.

**Files:**
- Modify: `packages/blocks/src/steps/view.ts` (`setState()`, `onClick()`)
- Modify: `packages/blocks/src/steps/style.css` (the complete-header cursor rule and the locked-title rule)

**Interfaces:**
- Consumes: nothing.
- Produces: `data-reached="true"` on a `.yaycheckout-step` once `setState()` has made it `current` or `complete`.

- [ ] **Step 1: Mark and reopen reached steps**

`setState()`: when `state !== LOCKED`, set `step.dataset.reached = 'true'`. `onClick()`: reopen any step with `step.dataset.reached === 'true'` whose state is not `CURRENT`. `reopen()` and `park()` stay unchanged.

`style.css`: the header `cursor: pointer` rule matches `[data-reached="true"]:not([data-state="current"])`, and the muted locked-title rule skips `[data-reached="true"]`.

- [ ] **Step 2: Build**

Run: `pnpm build:blocks`
Expected: the build finishes with no errors.

- [ ] **Step 3: Verify on the storefront**

Action: in the demo layout's preview (Vertical stepper, 4 steps), Continue through steps 1 to 3, click step 1's header, then click step 4's header.
Expected: step 1 is `complete` again, and step 4 is `current`.

Action: reload, then click step 4's header before doing anything else.
Expected: nothing changes. Step 4 stays `locked`, with no pointer cursor.

Action: repeat the first check with the layout set to "List".
Expected: the same result.

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/steps/view.ts`, `packages/blocks/src/steps/style.css`
Message: `fix(blocks): let shoppers reopen a step they already reached`
