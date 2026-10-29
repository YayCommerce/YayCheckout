# Keep each edition's identity on its own branch

**Purpose:** why Pro and Free are built from two branches that differ only in the plugin adapter and the plugin header, and why Pro's licensing is the admin-shell's.

**Update when:** never edit it. Supersede it with a new record if the editions stop being told apart this way.

## Status

Accepted, 2026-10-19, by Trace Bui.

## Context

- Pro has to be licensed and updated from yaycommerce.com, and Free must not carry licensing. Until now one adapter, a `PluginMenuAdapter`, served both, so Pro never turned licensing on. Release renamed Pro by rewriting its header with `sed`.
- The admin-shell already licenses every YayCommerce Pro plugin: an adapter that implements `LicenseConfigAdapter` gets the Licenses card, activation, the daily check and the EDD updater.
- YayReviews keeps its editions on two branches, `dev` (Pro) and `dev-free`, which differ only in the adapter and the header. The team wants YayCheckout to work the same way.
- Choosing the adapter at runtime from `includes/Pro/` would avoid a second branch, but it adds a seam and a load-order constraint only to swap one file, and no sibling plugin works that way.

## Decision

- `dev/edd` is Pro. Its `YayCheckoutPluginAdapter.php` implements `LicenseConfigAdapter` (slug `yaycheckout`, EDD item 1412), and its header names YayCheckout Pro.
- `dev/lite` is Free. Its adapter is a `PluginMenuAdapter` with a "Go Pro" link, and its header has no Pro.
- Everything else is shared. `dev/edd` is merged into `dev/lite`, and the two edition files keep their own versions.
- `release` reads the edition from the adapter and refuses a `--plan` that disagrees. It no longer rewrites the header.
- [The build-time split](2026-10-05-build-time-free-pro-split.md) still applies: the Free zip deletes `includes/Pro/` and the shell's licensing, and `Plan::is_pro()` still describes the build, not the license.
- When both editions are active, Pro wins: a copy outside `yaycheckout-pro/` stands down while that one is active.

## Consequences

- Building a release takes a checkout of the right branch, and `release` fails fast on the wrong one.
- A merge into `dev/lite` can conflict on the adapter or the header. Keep `dev/lite`'s side.
- A dev tree on `dev/edd` opens on YayCommerce → Licenses until a key is activated. Work on `dev/lite` to avoid that.
- Licensing gates only the admin menu and updates. The storefront and Pro features never read the license.
