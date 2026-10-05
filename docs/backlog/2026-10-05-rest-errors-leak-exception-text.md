# REST errors return raw exception text and log the wrong method

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

- **Raw text.** `BaseRestController::exec_read()` and `exec_write_wp()` (`includes/Controllers/BaseRestController.php`) turn any `Throwable` into a 500 whose message is `$ex->getMessage()`. A PHP 8 `TypeError` message names the file and line of the call, so a server path reaches the client.
- **Wrong method.** `exec_read()` logs every failure as `GET <route>`, though two POST routes use it: `LayoutRestController::create_preview_link()` and Pro's `LayoutImportRestController::review_layout()`.
- **Refusals as faults.** `LayoutRepository::archive()`, `restore()` and `delete()` throw `RuntimeException` when WordPress refuses the trash, untrash or delete, for example through another plugin's `pre_trash_post` filter. The controllers catch only `InvalidArgumentException`, so the refusal becomes a 500, and `Helpers\Logger::exception()` logs it as a fault. `Logger`'s docblock says expected refusals stay out of the log.
- **400 for missing.** `DesignRepository::delete_preset()` throws `InvalidArgumentException` for an unknown id, so `DELETE /yaycheckout/v1/design/presets/<id>` answers 400, not 404.
- **Untranslated.** The default messages of `BaseRestController`'s `error_*()` helpers, the status errors in `LayoutQuery` and the `RuntimeException` messages in `LayoutRepository` reach the client without `__()`.

The hard-coded `GET` came in `840a572`.

Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

A 500 can show a merchant a server path or a type error instead of a sentence they can act on. The log names the wrong method and counts expected refusals as faults, which buries real ones. A 400 for a missing preset says the request was malformed when the preset is simply gone.

## Suggested fix

In `exec_read()` and `exec_write_wp()`, log the whole exception and return a fixed, translated 500 message, and log `$request->get_method()` in both. Have the archive, restore and delete routes catch `RuntimeException` and answer 409 with a translated message, without logging. Have the preset route tell "not found" apart from "invalid", for example with a check in `DesignPresetRestController::delete_preset()`, and answer 404. Wrap the remaining messages in `__()`.

## Done when

- [ ] A 500 from `BaseRestController::exec_read()` or `exec_write_wp()` carries a fixed, translated message, not the exception's.
- [ ] A failure in `POST /yaycheckout/v1/layouts/<id>/preview-link` is logged as `POST`.
- [ ] An archive, restore or delete that WordPress refuses answers a 4xx and writes nothing to the `yaycheckout` log.
- [ ] `DELETE /yaycheckout/v1/design/presets/<unknown-id>` answers 404.
- [ ] The default messages of `BaseRestController`'s `error_*()` helpers are wrapped in `__()`.
- [ ] The messages `LayoutQuery` and `LayoutRepository` throw that reach a response are wrapped in `__()`.
