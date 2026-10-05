# The import review is implemented twice, and the two disagree

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

The editor's Import (`reviewFile` in `packages/blocks/src/checkout/import-export.tsx`) only parses the JSON, then asks `POST /layouts/import/review`, which runs `LayoutImportReview::from_envelope()` (`includes/Pro/Services/LayoutImportReview.php`). Tools → Import judges the file in the browser instead, with `parseExportFile()` and `reviewExportFile()` in `apps/admin/src/lib/api/tools.api.ts` and the strict `layoutExportWireSchema` (`apps/admin/src/lib/schema/tools.schema.ts`). The two differ:

- The SPA requires `plugin_version` and `exported_at` strings and rejects any unknown key. PHP checks only `format`, a single layout, and a string title and content.
- PHP refuses a file with no Checkout block. The SPA passes it, and `POST /layouts/import` refuses it after the review step.
- The SPA counts rule groups by shape. PHP keeps or drops them through `Layout::sanitize_rules()`, so Tools can show rules the import then drops.
- The editor caps a file at 5 MB (`MAX_IMPORT_BYTES`); Tools has no cap.
- A layout with no title fails in Tools with "This file is not a YayCheckout layout export." and in PHP with "The file must contain exactly one layout."

Tools' review came in `3c82a11`, the editor's in `e1fbcc8`. Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

A file exported by a newer version with one more key, or a hand-made file without `exported_at`, is refused by Tools → Import as "not a YayCheckout layout export", yet imports fine from the editor. The merchant gets two answers for one file, and support can't tell which is right. It also breaks `docs/conventions.md`: a rule the server can answer is asked, not copied.

## Suggested fix

Have Tools → Import ask `POST /layouts/import/review`, as the editor does, and keep only the JSON parse in the browser. Send the parsed JSON back unchanged, and read the review reply through a strict wire schema in `apps/admin/src/lib/schema/tools.schema.ts`. The reply carries `has_rules` but no group or condition counts, so either show `has_rules` or add the counts in `LayoutImportRestController::review_layout()`. Keep the 5 MB cap in one place both bundles import, such as `packages/blocks/src/shared/export-file.ts`. If `LayoutImportReview` changes, an integration test of it is warranted under CLAUDE.md's Tests rule.

## Done when

- [ ] `layoutExportWireSchema` is used only by `exportLayout()` in `apps/admin/src/lib/api/tools.api.ts`.
- [ ] Picking a file in Tools → Import sends `POST /yaycheckout/v1/layouts/import/review`, as the browser's network panel shows.
- [ ] An export file with an extra top-level key and no `exported_at` gets the same review in Tools → Import and in the editor's Import.
- [ ] Tools → Import refuses a file with no Checkout block at the review step, with "The file does not contain a checkout layout."
- [ ] Tools → Import and the editor's Import refuse a file over the same size, with the same message.
