# Nothing checks the PHP 7.4 floor

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 code audit for the harness renewal (`docs/plans/2026-10-05-harness-renewal.md`), on `trace/docs/harness-renewal`.

## Today

`yaycheckout.php` declares `Requires PHP: 7.4`. `phpcs.xml` applies the WordPress standard with no PHPCompatibility ruleset and no `testVersion`, and the dev tooling runs on PHP 8.2 or later, where `php -l` accepts PHP 8-only syntax. Hard rule 4 in `CLAUDE.md` therefore rests on review alone.

## Why it matters

One `match`, `?->` or promoted constructor property in a shipped file is a parse error on a PHP 7.4 store, which takes the site down when the plugin loads.

## Suggested fix

Add `phpcompatibility/phpcompatibility-wp` as a dev dependency and `<config name="testVersion" value="7.4-"/>` with the `PHPCompatibilityWP` rule to `phpcs.xml`. Record any findings it reports on `dev/edd` in `docs/known-issues.md` before fixing them. Then drop "only review catches it" from hard rule 4.

## Done when

- [ ] `composer lint` reports a `match` expression added to a file under `includes/` as an error.
- [ ] Hard rule 4 in `CLAUDE.md` names the check instead of review.
