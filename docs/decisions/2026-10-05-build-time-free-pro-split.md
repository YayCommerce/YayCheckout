# Split Free and Pro at build time, and check every zip

**Purpose:** why one tree builds both editions, why the edition is decided by which files a zip contains rather than by a flag, why the SPA may show Pro UI in Free, and why every zip is checked.

**Update when:** never edit it. Supersede it with a new record if the editions stop being split this way.

## Status

Accepted, 2026-10-05, by Trace Bui, who confirmed the reasons. Backfilled: the decision was made on 2026-09-09, in `48ea118` (the removable boundary and the derived plan), `cd40a19` (the SPA's build-time plan) and `4b12c42` (the Free and Pro zips from one release script).

## Context

- YayCheckout ships a Free edition and a paid Pro one from one codebase. Free may be listed on wordpress.org, and Pro's paid code must not ship in a free download.
- A sibling plugin keeps its paid code in a `Premium/` folder that can't actually be removed, because its container imports those classes directly. Nothing but a mechanical check prevents that drift.
- A declared edition flag can claim Pro while the Pro files are missing, and the first Pro call then fatals.
- A build that drops files fails silently: a zip that still carries code it was meant to drop, or lacks code it was meant to keep, installs and looks correct in a dev tree that always contains everything.
- The SPA is a marketing surface too: showing Free merchants what Pro offers is wanted, as long as nothing Pro works without Pro's PHP.

## Decision

- **PHP:** Free is Pro without `includes/Pro/`. Code outside that directory names a `YayCheckout\Pro` class in one place only, the guarded `YayCheckout::boot_pro()`, and Pro attaches through hooks and filters from `Pro\ProServiceProvider`. `scripts/check-pro-boundary.sh`, run by `composer lint`, fails any other reference.
- **The plan is derived:** `Support\Plan::is_pro()` reports whether the provider booted. It describes the build, not a licence: an expired Pro licence is still the Pro plan.
- **SPA:** the plan is the build-time constant `__YAYCHECKOUT_PLAN__`, set in `apps/admin/vite.config.ts` from `YAYCHECKOUT_PLAN`; the dev server defaults to `pro` and builds to `free`. PHP doesn't send the plan to the browser. `<ProGate>` may show Pro UI to Free, blurred, because Free's PHP has no matching endpoint; real data or a real endpoint goes behind `ProOnly` or an `isPro &&` branch.
- **Zips:** `./scripts/run.sh release --plan=free|pro` builds each edition, Free by default and failing on any other value. Pro installs into `yaycheckout-pro/`, so a wordpress.org update for slug `yaycheckout` can't overwrite a paid install. Release checks the zip it just built for `includes/Pro/` entries against the plan.

## Consequences

- The Free build is a directory deletion plus a build flag, with no strip markers in shared files.
- Every Pro feature needs a seam: a hook core fires, or a filter core applies with a working default.
- The Free SPA bundle carries Pro UI code. That is safe only while every Pro capability is withheld in PHP, which review has to keep true.
- Free and Pro can both be installed and active at once, so the plugin has to stand one copy down: see the backlog item on a second active copy.
- A mistake in the split shows at release, when the zip is checked, not while developing.
