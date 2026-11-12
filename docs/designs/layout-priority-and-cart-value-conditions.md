---
id: UX-1
title: Layout priority, cart value and country conditions
status: ready
from: BS-1
surfaces: [admin]
editions: [pro]
mockup:
created: 2026-11-12
---
# UX-1 Layout priority, cart value and country conditions

## Job
A Pro store admin with several active layouts decides which one a shopper gets when more than one layout's rules match, and can assign a layout by how big the cart is or which country the customer is in. Done means: the order they set on the Layouts page is the order the storefront tries, and a "Cart subtotal at least 100" rule serves its layout.

## Where it lives
- **Priority:** a "Priority" button in the Layouts page toolbar, next to "Add layout" (`apps/admin/src/pages/layouts/LayoutsPage.tsx`, toolbar at the `Add layout` button), opening a sheet like the Assignment drawer. Shown on Pro only, and only when two or more layouts are active (with one, there is nothing to order).
- **Conditions:** four more entries in the Assignment drawer's condition type menu (`ConditionGroup.tsx`, `AddConditionMenu` and the type `Select`), no new place.

## Flow
1. The merchant opens **Layouts** and clicks **Priority**.
2. The sheet lists the active layouts, top priority first, each with its assignment chips, and move up / move down buttons.
3. They move a layout, then click **Save priority**. A toast confirms and the sheet closes.
4. In a layout's **Assignment** drawer they add a condition, pick **Cart subtotal**, choose **at least**, type `100`, and save.
- *Stale list:* someone activated or deactivated a layout meanwhile; saving fails with the server's message and the sheet reloads the current list.
- *Free:* no Priority button, and the new condition types sit inside the already-locked drawer sample (nothing new is shown on Free).

## Screens

### Priority sheet
```
+------------------------------------------------------------+
| Priority                                                [x] |
| When several layouts match a shopper, the highest one wins. |
| Newly activated layouts go to the top.                      |
|------------------------------------------------------------|
| 1  Wholesale checkout        Customer role in list Whole... |
|                                                   [^] [v]   |
| 2  Big orders                Cart subtotal at least 500     |
|                                                   [^] [v]   |
| 3  Default checkout          Everyone                       |
|                                                   [^] [v]   |
|------------------------------------------------------------|
|                               [Cancel]  [Save priority]     |
+------------------------------------------------------------+
```
- **Sheet:** `Sheet`/`SheetContent`/`SheetHeader`/`SheetFooter` from `apps/admin/src/components/ui/sheet.tsx`, as `AssignmentDrawer.tsx` uses them; default width (no condition editors inside).
- **Row:** position number, layout title, then `AssignmentChips` (`apps/admin/src/pages/layouts/assignment/AssignmentChips.tsx`) so the merchant sees why each would match.
- **Move up / move down:** icon `Button`s (`components/ui/button.tsx`, `ArrowUp`/`ArrowDown` from `lucide-react`); up is disabled on the first row, down on the last.
- **Save priority:** `LoadingButton`, disabled until the order differs from the loaded one; sends the ids top first.
- **Cancel:** `SheetClose`, discards the change.

### Cart value condition row (Assignment drawer)
```
| [Cart subtotal  v] [at least   v] [ 100.00        ] [trash] |
| [Cart quantity  v] [less than  v] [ 20            ] [trash] |
```
- **Type select:** the existing type `Select`; two new entries, "Cart subtotal" and "Cart quantity", after "Categories".
- **Operator select:** the existing operator `Select`, with "at least" and "less than".
- **Value:** `NumericFormat` (react-number-format, what `NumberInputInput` wraps) over `Input` (`components/ui/input.tsx`), since `NumberInputRoot` always holds a number and can't be empty; subtotal takes up to 2 decimals and min 0, quantity whole numbers and min 0. Empty counts as incomplete, so **Save rules** stays disabled, as for an empty multi-select today.
- **Chip text** (`AssignmentChips`): "Cart subtotal at least 100", "Cart quantity less than 20".

### Country condition row (Assignment drawer)
```
| [Billing country  v] [in      v] [ Germany x  France x  v ] [trash] |
| [Shipping country v] [not in  v] [ Select countries      v ] [trash] |
```
- **Type select:** two more entries, "Billing country" and "Shipping country", after "Cart quantity".
- **Operator select:** the existing list operators, "in" and "not in" (as Customer role).
- **Value:** `OptionsMultiSelect` (`apps/admin/src/pages/layouts/assignment/OptionsMultiSelect.tsx`, as the role picker uses it) over `storeData().countries` (code => name from `WC()->countries->get_countries()`), searchable by name. Empty counts as incomplete, so **Save rules** stays disabled.
- **Help text** under the row (muted, one line): "Read from the customer's saved address when checkout opens. Changing the country in the form doesn't switch the layout."
- **Chip text:** "Billing country in Germany, France"; a code no longer in WooCommerce's list shows as the code.

## States
- Empty: the Priority button is hidden with fewer than two active layouts, so the sheet is never empty.
- Loading: skeleton rows (`components/ui/skeleton.tsx`) while the active list loads.
- Saving: Save priority shows its spinner; move buttons are disabled.
- Success: toast "Priority saved."; the sheet closes; the Layouts list refetches.
- Error: the server's message as a toast (the existing mutation error handling), the sheet stays open and refetches the active list.
- No permission: n/a; the Layouts page and every route already require `manage_woocommerce`.
- Pro in Free: n/a; Free shows no Priority button (one layout is active on Free, so priority has no meaning), and the new condition types appear only in Pro's drawer.

## Copy
- Button: "Priority"
- Sheet title: "Priority"
- Sheet description: "When several layouts match a shopper, the highest one wins. Newly activated layouts go to the top."
- Move buttons (aria-label): "Move %s up", "Move %s down" (%s: layout title)
- Save: "Save priority"; Cancel: "Cancel"
- Toast: "Priority saved."
- Load error: "The active layouts could not be loaded."
- Condition types: "Cart subtotal", "Cart quantity"
- Operators: "at least", "less than"
- Value placeholders: "Amount", "Items"
- Condition types: "Billing country", "Shipping country"
- Country picker: placeholder "Select countries", search "Search countries", empty "No countries found."
- Country help text: "Read from the customer's saved address when checkout opens. Changing the country in the form doesn't switch the layout."

## Accessibility
- Every move button has an aria-label naming the layout; after a move, focus stays on the same button of the moved row, so repeated presses keep moving it.
- The list is an ordered list (`<ol>`), so screen readers announce positions.
- The number inputs carry an `aria-label` of the condition type ("Cart subtotal amount", "Cart quantity items"); the country pickers are labelled "Billing countries" / "Shipping countries".
- Keyboard order: rows top to bottom, then Cancel, then Save priority. Toasts are announced by `sonner`.
- RTL: arrows are vertical, so nothing mirrors.

## Decisions
- Up/down buttons instead of drag and drop: the SPA has no drag-and-drop library, and buttons are keyboard-friendly without extra work.
- A sheet opened from the toolbar instead of a Priority column in the Active tab: the table pages and sorts, which makes reordering across pages confusing.
- Priority is the order of the active set, not a number per layout (BS-1 option A).
- Country is read once when checkout opens and the layout stays pinned (BL-6); the help text says so, so merchants aren't surprised when a shopper edits the country.
- No overlap warnings: whether two rule sets overlap isn't decidable cheaply, and the list order already answers "which wins".

## Acceptance
- With two active Pro layouts, the Layouts page shows a Priority button; with one, it doesn't.
- Moving the bottom layout to the top and saving, then reopening the sheet, shows the new order.
- Activating a third layout puts it at the top of the Priority sheet.
- In the Assignment drawer, "Cart subtotal" "at least" "100" saves, and the Layouts row chip reads "Cart subtotal at least 100".
- An empty cart value keeps Save rules disabled.
- "Billing country" "in" "Germany" saves, and the chip reads "Billing country in Germany"; an empty country list keeps Save rules disabled.
- On Free, the Layouts page has no Priority button.
