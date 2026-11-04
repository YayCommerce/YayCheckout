# Releases go through the release kit, copied from YayCommerce/Yay-Release-Kit

**Purpose:** why YayCheckout builds, versions and tags its releases with the shared release kit, where the kit's one copy is changed, and this repo's choices in its config.

**Update when:** never edit it. Supersede it with a new record when the kit's canonical copy moves, the rule for copying it changes, or this repo stops using it.

## Status

Accepted, 2026-11-04, by Trace Bui. It restates [YayLoyalty's release kit record](https://github.com/YayCommerce/YayLoyalty/blob/dev/edd/docs/decisions/2026-11-04-release-kit-canonical-copy.md) for YayCheckout. It supersedes no decision: [the harness record](2026-10-05-harness-with-review-backlog.md) still holds for the five planning and review skills.

## Context

- Releasing was `./scripts/run.sh release` by hand on each edition branch, with nothing recording which commit produced which zip, or what a tester or customer has.
- The release kit (the nine release skills, the `yayrelease-worker` agent, `.claude/release-kit/` and `RELEASING.md`) wraps that script. It never replaces or edits it. It adds test and release builds from exact commits, QA gates, changelogs, release branches, tags only once a release is live, and hotfix and support builds. Every YayCommerce plugin repo uses the same kit.
- Neither edition is released yet: YayCheckout is not on WordPress.org and has no store sale. Both start as first releases.
- Options turned down:
  - keeping releases manual: no record of which commit is live, and no guard against shipping untested dev work in a fix;
  - a changelog in `CHANGELOG.md` for Pro (the kit's default for store editions): this repo already keeps release notes in `readme.txt`, which both zips ship.

## Decision

- Releases of both editions go through the release skills. The canonical copy of the kit is YayCommerce/Yay-Release-Kit: a change lands there first, then this repo's copy is replaced whole with its `install` script, never edited here.
- This repo's facts live in `.claude/release.config.json`:
  - Pro (`pro`) is built from `dev/edd` for the EDD store as `yaycheckout-pro`, and Free (`free`) from `dev/lite` for WordPress.org as `yaycheckout`;
  - both share one version number;
  - release notes for both go in `readme.txt`;
  - the kit lists fixes that are in Pro and not in Free, and offers a cherry-pick from `dev/edd` to `dev/lite`;
  - each edition's live code is recorded on `released/pro` and `released/free`, created when it first goes live.
- `main` keeps its role in the promotion practice in the README.

## Consequences

- Every zip records the commit, branch and admin-shell revision it came from, and a tag `yaycheckout-<edition>/vX.Y.Z` marks exactly what customers have.
- The kit's copy lags until someone runs `install` and commits the update; `/yaystatus` shows an old copy.
- The Free zip ships its build source, and the kit's config allows it. When `run.sh` changes what it ships or checks, update the edition's `zip_rules` in the config too.
