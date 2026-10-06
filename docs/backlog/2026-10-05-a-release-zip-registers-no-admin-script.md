# A release zip registers no admin script unless the site turns development mode off

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 code audit for the harness renewal (`docs/plans/2026-10-05-harness-renewal.md`), on `trace/docs/harness-renewal`.

## Today

`yaycheckout.php` defines `YAYCHECKOUT_IS_DEVELOPMENT` as `true` unless `wp-config.php` already did. `Engine\Register\RegisterFacade` registers `RegisterDev` while the constant is true and `RegisterProd` otherwise, each only if the class exists. `cmd_release` in `scripts/run.sh` deletes `includes/Engine/Register/RegisterDev.php` from every zip. So in an installed zip the constant is true, `RegisterDev` doesn't exist, and neither registrar runs. Found by reading the code; not yet reproduced on an installed zip.

## Why it matters

A merchant who installs the zip gets a blank YayCheckout admin page, and nothing tells them to define a constant. The default was turned to `true` for development in `a627fb5`, after `ca80b09` had set it to `false` for this reason.

## Suggested fix

Default the constant to `false`, and have developers define it as `true` in `wp-config.php`; or have the release step write `false` into the zip's `yaycheckout.php`. Then update the onboarding steps in `README.md` and the known issue "The admin page is blank without the dev server".

## Done when

- [ ] A Free zip from `./scripts/run.sh release --no-prompt`, installed on a site whose `wp-config.php` doesn't define `YAYCHECKOUT_IS_DEVELOPMENT`, shows the YayCheckout admin page.
- [ ] A dev checkout still loads the SPA from the Vite dev server with the documented setup.
