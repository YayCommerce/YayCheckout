# The admin shows raw request errors to merchants and logs request payloads

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- `apps/admin/src/pages/layouts/LayoutsPage.tsx` renders `countsQuery.error.message` and `listError.message`, and `apps/admin/src/pages/compatibility/CompatibilityPage.tsx` renders `query.error.message`, without `getErrorMsg()`. For a failed request, ky's `HTTPError` message reads "Request failed with status code 500 Internal Server Error: GET" and the full URL. For a reply that fails its schema, zod's message is the list of issues as JSON.
- `getErrorMsg()` in `apps/admin/src/lib/helpers/response.helper.ts` falls back to an untranslated "An Error has occurred!". For a `ZodError`, a ky `TimeoutError` or a failed fetch ("Failed to fetch") it returns the raw `message`.
- `apps/admin/src/pages/500.tsx` shows the raw `error.message` as its heading, or an untranslated "Something went wrong". The "Page Not Found" heading in `apps/admin/src/pages/404.tsx` is untranslated.
- The mutation cache in `apps/admin/src/lib/query-client.ts` calls `logger.error(…, error, variables)`. `error` stays on in production (`apps/admin/src/lib/logger.ts`), so every failed save prints its whole payload to the console: an import envelope, the design with its custom CSS, the settings or a layout's rules. The logging came in `2601385`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

A merchant sees a URL or a JSON dump where a sentence should be, can't act on it, and on a non-English site sees English. The payload logging fills the console screenshots support asks for with the merchant's own content. `docs/conventions.md`'s logging rule wants the cause in the console, not the request body.

## Suggested fix

Render every query error through `getErrorMsg()`. Give `getErrorMsg()` translated sentences for its fallback, a reply that fails its schema, a timeout and a network failure, and keep the raw error for the log. Wrap the 404 and 500 headings in `__()`, and leave the raw error on the 500 page to the log. Log the mutation key and the error, not `variables`.

## Done when

- [ ] With a `rest_pre_dispatch` filter that returns a 500 `WP_Error` for every `/yaycheckout/v1/` route, the Layouts and Compatibility pages show that error's message, not ky's "Request failed …" text.
- [ ] `getErrorMsg()` returns a translated sentence, not the error's `message`, for a `ZodError`, a ky `TimeoutError` and a failed fetch.
- [ ] The fallback text in `getErrorMsg()` and the headings in `apps/admin/src/pages/404.tsx` and `apps/admin/src/pages/500.tsx` all go through `__()`.
- [ ] `apps/admin/src/lib/query-client.ts` passes no `variables` to `logger`.
