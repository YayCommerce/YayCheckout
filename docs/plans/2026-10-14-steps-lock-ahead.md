# Steps lock ahead of the open step Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. There is no spec; the Requirements below are the contract.

**Status:** active
**Goal:** Every step after the open one is always inactive (`locked`), so going back to any earlier step resets every step after it.
**Spec:** none, planned from the request
**Architecture:** `packages/blocks/src/steps/view.ts` gets one `open( step )` that makes a step `current` and locks every step after it; Continue, Back and reopen all go through it. Because a step after the open one can no longer be `complete`, the `data-completed` and `data-reached` flags and `park()`'s re-check have nothing left to do and are removed, rather than kept alongside a second rule. Steps Progress and Go to Step need no script change: they already mirror `data-state` and only reopen a `complete` step (`canReopenStep()`).
**Touches:** `packages/blocks/src/steps/view.ts`, `packages/blocks/src/steps/style.css`, `packages/blocks/src/steps-progress/style.css`, `packages/blocks/src/shared/checkout-root.ts` (shared, comment only; consumers `steps/view.ts`, `steps-progress/view.ts`, `go-to-step/view.ts`)
**Tests:** none. The repo has no JS test suite and CLAUDE.md's test rule does not cover step state, so each task ends in a browser check.

## Requirements

- **Goal:** On the storefront, the open step and every step before it are the only ones a shopper sees as reached; every step after the open one is locked and looks never opened, in the Steps block and in Steps Progress.
- **Out of scope:** the editor (its steps have no `data-state`), `step/render.php` and `CheckoutStepsProgress.php` (they already print step 1 `current` and the rest `locked`), form values (a reset step keeps what the shopper typed), Block visibility rules, the Steps Progress and Go to Step scripts.
- **Acceptance criteria:**
  1. In a 4-step layout, after Continue on steps 1–3 (step 4 open), clicking step 2's header, its Edit, its Steps Progress item or a Go to Step button for step 2 leaves step 1 `complete`, step 2 `current`, and steps 3 and 4 `locked`, with no tick, no summary and no Edit shown on 3 and 4.
  2. Back from step 3 to step 2 locks step 3 (and 4); before this change step 3 stayed `complete` if it had been completed.
  3. A locked step is never reopenable: clicking its header, its Steps Progress item or a Go to Step button for it does nothing, and its Steps Progress button and Go to Step button are `disabled`.
  4. A reset step looks like a never-opened one: muted title (`#6b7280` in Steps unless the step sets a text colour; Steps Progress's upcoming-text colour), outlined number badge.
  5. Leaving the open step for an earlier step does not validate it: going from an open step 3 with an empty required field back to step 1 succeeds.
  6. Continue from the reopened step 2 checks step 2, marks it `complete`, and opens step 3 as `current`; step 3 must pass Continue again before step 4 opens.
  7. End to end: on the checkout with a 4-step layout, run criteria 1, 6 and then Place order from step 4; the order is placed with every field the shopper entered, including those on the steps that were reset.
- **Decided with the user:** a reset step looks exactly like a never-opened step (drop the "reached" title colour), not "locked but keeps its title colour".

## Global constraints

- Run from the repo root on Node 22 (`nvm use`), pnpm only.
- Never hand-edit `assets/dist/`; change `packages/blocks/src/` and run `pnpm build:blocks`.
- Branch from `dev/edd` as `trace/refactor/steps-lock-ahead`; one PR into `dev/edd`, squash merge.
- Commit messages follow Conventional Commits: `type(scope): summary`, with CLAUDE.md's trailer.

## Review focus

- A `yaycheckout:step-reopen` event naming a locked step (from a Go to Step button the script hasn't disabled yet, or a stale event). Expected: nothing changes. Task 1 Step 2 (d).
- A reopened step with a field emptied, left for an earlier step: today `park()` blocks that; now it must not. Task 1 Step 2 (e).
- Headings hidden or one-at-a-time layout, where only the current step is shown: going back must still show exactly one step. Task 1 Step 2 (f).
- Steps Progress bar and segments fill after going back. Expected: the bar is at `2/4` and only segments 1–2 are filled, with step 2 open. Task 1 Step 2 (c).
- Previous (Back button) outside a step follows the new open step after going back. Task 1 Step 2 (f).

---

### Task 1: Lock every step after the open one

**Files:**
- Modify: `packages/blocks/src/steps/view.ts` (`onClick`, `advance`, `reopen`, `park`, `setState`, `retreat`)
- Modify: `packages/blocks/src/steps/style.css:61-64`
- Modify: `packages/blocks/src/steps-progress/style.css:121-124`
- Modify: `packages/blocks/src/shared/checkout-root.ts:11-17` (comment only)

**Interfaces:**
- Consumes: `canReopenStep( step ): boolean` from `shared/checkout-root.ts`, unchanged.
- Produces: `open( step: HTMLElement ): void` (module-private in `steps/view.ts`). Invariant other scripts may rely on: every `.yaycheckout-step` after the `current` one in its `.yaycheckout-steps` has `data-state="locked"`; every one before it is `complete`.

- [ ] **Step 1: Route every state change through `open()`**

In `packages/blocks/src/steps/view.ts`:

- Add `open( step: HTMLElement ): void`: `setState( step, CURRENT )` first (so `currentStep()` and `syncBackButtons()` find the new step while the rest are locked), then `setState( later, LOCKED )` for each following sibling `.yaycheckout-step` (walk with `siblingStep( x.nextElementSibling )`) whose state is not already `locked`.
- `advance()`: after `complete( step )` passes, call `open( next )` in place of `setState( next, CURRENT )`; keep `revealStep( next )`.
- `retreat()`: replace both `setState` calls with `open( previous )`; keep `revealStep( previous )`. Update the comment: Back leaves the open step without checking it, and the step and everything after it lock.
- `reopen()`: return early unless `canReopenStep( step )` (this also covers a `step-reopen` event naming a locked step or the open step). Drop the `blocked` / `park()` loop and call `open( step )`; keep the header focus and `revealStep( step )`.
- Delete `park()`.
- `setState()`: delete the `data-completed` and `data-reached` writes and their comment. Summary and Edit visibility stay as they are (`hidden` unless `complete`).
- `onClick()`: change the comment on the header-click branch to say only a completed step reopens, and every completed step is before the open one.

In `packages/blocks/src/steps/style.css`, change the muted-title rule to `.yaycheckout-step[data-state="locked"]:not(.has-text-color) .yaycheckout-step__title` and its comment to "Muted until the text colour support sets a colour."

In `packages/blocks/src/steps-progress/style.css`, delete the `[data-state="locked"]:has(> button:enabled)` rule and its comment: a locked item's button is now always disabled.

In `packages/blocks/src/shared/checkout-root.ts`, extend `canReopenStep()`'s doc comment: completed steps are always the ones before the open step, because `steps/view.ts` locks every step after it.

Then confirm nothing else reads the removed flags: `git grep -n "completed\|reached" -- packages/blocks/src/steps packages/blocks/src/steps-progress packages/blocks/src/shared/checkout-root.ts` prints no `dataset.completed`, `dataset.reached`, `data-completed` or `data-reached`.

- [ ] **Step 2: Build and verify on the storefront**

Run: `pnpm build:blocks`. Expected: webpack finishes with no errors.

Then on `http://yaydevs.local/?page_id=9` (cart not empty; preview an inactive layout with a `PreviewToken` rather than editing an existing draft), with a layout whose Steps block has 4 steps and a Steps Progress block (Numbered circles) and a Go to Step button for step 2:

- (a) Fill steps 1–3 and Continue until step 4 is open. Click step 2's header. Expected in the console: `[...document.querySelectorAll('.yaycheckout-step')].map(s => s.dataset.state)` returns `["complete","current","locked","locked"]`; steps 3 and 4 show no tick, no summary line, no Edit; their titles are grey; Steps Progress items 3 and 4 are grey outlined circles and `document.querySelectorAll('.yaycheckout-steps-progress__item button:disabled').length` is `3` (items 2, 3, 4). Repeat from step 4 via step 2's Edit, its Steps Progress item and the Go to Step button: same result each time.
- (b) From the state in (a), click step 3's and step 4's headers and their Steps Progress items. Expected: nothing changes.
- (c) Switch Steps Progress to Progress bar, then to Segmented bar, and repeat (a). Expected: the bar's fill is half the track and reads "Step 2 of 4"; only segments 1 and 2 are filled.
- (d) With step 2 open, run `document.querySelector('.yaycheckout-checkout').dispatchEvent(new CustomEvent('yaycheckout:step-reopen', {bubbles: true, detail: {step: '4'}}))`. Expected: states unchanged.
- (e) Reach step 4, click step 3's header, empty one required field on step 3, click step 1's header. Expected: step 1 opens (before this change the empty field blocked it) and states are `["current","locked","locked","locked"]` (criterion 5).
- (f) Set the Steps layout to one step at a time, with a Previous block outside the Steps block. Reach step 3 and click Previous. Expected: step 2 is the only step shown, states are `["complete","current","locked","locked"]` (criterion 2), and Previous's button is still shown. Click Previous again: step 1 alone shows and the cart link replaces the button.
- (g) With step 2 open, Continue. Expected: step 2 is `complete` with its summary and step 3 `current`. Empty one required field on step 3 and Continue. Expected: the browser's required message shows and step 4 stays `locked` (criterion 6).

- [ ] **Step 3: Verify end to end**

From (g), fill step 3, Continue to step 4, Place order. Expected: the order-received page loads, and the order in WooCommerce → Orders holds the billing and shipping values typed on steps 2 and 3 before they were reset (criterion 7).

- [ ] **Step 4: Commit**

Stage: `packages/blocks/src/steps/view.ts`, `packages/blocks/src/steps/style.css`, `packages/blocks/src/steps-progress/style.css`, `packages/blocks/src/shared/checkout-root.ts`, `docs/plans/2026-10-14-steps-lock-ahead.md`
Message: `refactor(blocks): lock every step after the open one`
