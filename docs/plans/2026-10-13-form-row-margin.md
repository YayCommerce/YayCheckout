# Form Row Margin Implementation Plan

> **For agentic workers:** execute tasks in order, one at a time. Steps use `- [ ]` for tracking. Read the spec linked below before starting; the plan argues from it.

**Status:** done 2026-10-13
**Goal:** Remove the browser's 16px `<p>` margin from WooCommerce field rows on the storefront, so the layout's own spacing decides how far apart fields are.
**Spec:** [docs/specs/2026-10-13-form-row-margin-design.md](../specs/2026-10-13-form-row-margin-design.md)
**Architecture:** Two rules in `checkout-base.css`. The first zeroes the block margin of a `.form-row` that sits directly in one of our wrappers. The second puts 12px between consecutive rows in the two wrappers that hold several rows and have no gap. We keep the `<p>` tag, because swapping it would mean rewriting WooCommerce's HTML (rejected in the spec).
**Touches:** `assets/css/checkout-base.css` (shared: loaded on the storefront by `Renderer\CheckoutPage` and in the editor canvas by `Engine\Admin\LayoutEditorCanvas`, both through `Renderer\CheckoutBaseStyles`)
**Tests:** none (CSS only; no case in CLAUDE.md's test rule applies)

## Global constraints

- CSS only, in `assets/css/checkout-base.css`. No PHP or block markup changes.
- Selectors start with `.yaycheckout-checkout` and use no `@layer`, as the file's header requires.
- Set `margin-block`, never `margin`, so a theme's or block's inline margins stay intact.
- No `!important`.
- Never hand-edit generated output (`assets/dist/`, `vendor/`, …). `assets/css/` is source, not generated.
- Branch from `dev/edd` as `trace/<type>/<topic>`. Conventional Commits. Stage files by name.

## Review focus

- **A Field in a Columns row:** it must still sit flush with its neighbour. That rule is untouched and only the inner `<p>` changes. Covered by Task 1, Step 2.
- **An Order note hidden behind its toggle:** its row sits in `.yaycheckout-disclosure__panel`, not the block wrapper, and must also lose its margin once opened. Covered by Task 1, Step 3.
- **Rows in Additional fields and Create account:** they have no gap container and would touch once the margin goes, so they need the 12px rule. Covered by Task 1, Step 4.
- **The login dialog:** its rows must keep their 1rem. Covered by Task 1, Step 5.
- **Browser cache:** the stylesheet's version is `YAYCHECKOUT_VERSION` (`Renderer\CheckoutBaseStyles:23`), which doesn't change during development. Every check needs a hard reload, or DevTools open with "Disable cache" on.

---

### Task 1: Scoped row-margin reset

**Files:**
- Modify: `assets/css/checkout-base.css`, directly after the `/* Field rows. … */` rule at line 147 (`.yaycheckout-checkout :is(.form-row-first, .form-row-last, .form-row-wide)`)

**Interfaces:**
- Consumes: nothing
- Produces: nothing other code relies on

- [x] **Step 1: Add the two rules**

Rule 1, with a one-line comment saying WooCommerce prints each row as a `<p>` whose browser margin would add to the layout's spacing:
`.yaycheckout-checkout :is(.form-row, .yaycheckout-disclosure__panel, .yaycheckout-different-address, .yaycheckout-create-account, .yaycheckout-create-account > .create-account, .woocommerce-additional-fields__field-wrapper) > .form-row { margin-block: 0; }`

Rule 2, with a one-line comment saying these wrappers hold several rows and have no gap:
`.yaycheckout-checkout :is(.yaycheckout-create-account > .create-account, .woocommerce-additional-fields__field-wrapper) > .form-row + .form-row { margin-block-start: 12px; }`

- [x] **Step 2: Verify field spacing on the storefront (spec AC 1–4)**

Setup: in wp-admin, create a layout from the Classic two-column template; don't reuse layouts 1251 or 1253, which still have the old `"type":"flow"` group. Mint a preview token with `\YayCheckout\Helpers\PreviewToken::mint( <id>, 60 )` through wp-cli (the socket flag is in `docs/known-issues.md`). Open `http://yaydevs.local/?page_id=9&yaycheckout_preview=<token>` and hard-reload.
Run in the console:
```js
const b = document.querySelector('.yaycheckout-billing');
[...b.children].filter(k => k.offsetParent).map((k, i, a) => i && Math.round((k.querySelector('label') || k).getBoundingClientRect().top - (a[i-1].querySelector('input,select,textarea') || a[i-1]).getBoundingClientRect().bottom)).slice(1)
```
Expected: every value 12 (±1). Before the change it is 28–45.
Then, in the editor, set the Billing block's Block spacing to 24px, save, and reload the preview. Expected: every value 24 (±1). Afterwards set Block spacing back to empty.

- [x] **Step 3: Verify a toggle-hidden Order note (spec AC 5)**

Action: in the editor, set the Order note block to hide behind its toggle and save. Reload the preview and click the toggle.
Run: `getComputedStyle(document.querySelector('.yaycheckout-disclosure__panel > .form-row')).margin`
Expected: `"0px"`

- [x] **Step 4: Verify Additional fields rows (spec AC 6)**

Setup: create a temporary `wp-content/mu-plugins/yc-order-fields.php` (outside the repo). It adds two text fields to `$fields['order']` through the `woocommerce_checkout_fields` filter. In the editor, add an Additional fields block to the test layout and save. Reload the preview.
Run:
```js
const r = [...document.querySelectorAll('.woocommerce-additional-fields__field-wrapper > .form-row')];
[r.length, getComputedStyle(r[0]).marginTop, getComputedStyle(r.at(-1)).marginBottom, Math.round(r[1].getBoundingClientRect().top - r[0].getBoundingClientRect().bottom)]
```
Expected: `[n ≥ 2, "0px", "0px", 12]` (±1 on the last value). Afterwards delete `yc-order-fields.php`.

- [x] **Step 5: Verify the login dialog and the editor (spec AC 7–8)**

Action: on the preview, open the Login dialog.
Run: `getComputedStyle(document.querySelector('.yaycheckout-login__dialog .form-row')).marginBottom`
Expected: `"16px"`
Action: open the test layout in the editor and compare the Billing fields' spacing with a screenshot taken before Step 1.
Expected: no visible change.

- [x] **Step 6: End-to-end check (spec AC 9)**

Action: hard-reload the preview from Step 2 and run:
```js
[...document.querySelectorAll('.yaycheckout-checkout :is(.form-row, .yaycheckout-disclosure__panel) > .form-row')].filter(r => parseFloat(getComputedStyle(r).marginTop) || parseFloat(getComputedStyle(r).marginBottom)).length
```
Expected: `0`. By eye: the Contact information heading sits 12px above the Email label, and the Billing fields are 12px apart.

- [x] **Step 7: Commit**

Stage: `assets/css/checkout-base.css`, and `docs/plans/2026-10-13-form-row-margin.md` with its Status set to `done YYYY-MM-DD`
Message: `fix(checkout): drop the browser margin from WooCommerce field rows`
