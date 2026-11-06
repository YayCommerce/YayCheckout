#!/bin/bash
#
# First pass over a release range: screen each commit into DROP, PRO? or BULLET?.
# Usage, from anywhere in the repo:
#   bash .claude/skills/release/scripts/first-pass.sh v1.0.0..HEAD
#   bash .claude/skills/release/scripts/first-pass.sh -25
#
# It is a SCREEN, not the verdict. DROP is reliable: those paths cannot produce a
# user-visible change. PRO? and BULLET? are prompts to read the commit body and apply
# references/changelog-style.md — in particular, PRO? only means the commit involves a
# Pro path, and whether the bullet is Pro-only is settled by BlockRegistry::PRO_BLOCKS
# and the template catalogue's 'pro' flag, never by this script.
#
# Update when: the Pro path list changes, or a path is added that cannot produce a
#   user-visible change (add it to DROP_PATHS).
#

cd "$(git rev-parse --show-toplevel)" || exit 1

# Paths under which no change can reach a site owner: harness, CI, tooling, tests and
# generated output. Verified against the last 25 commits on dev.
DROP_PATHS='^(\.github/|\.claude/|\.vscode/|docs/|tests/|scripts/|languages/|editions/|vendor/|vendor-prefixed/|assets/dist/|CLAUDE\.md$|CLAUDE\.local\.md$|README\.md$|phpcs\.xml$|phpunit\.xml|composer\.json$|composer\.lock$|scoper\.inc\.php$|\.gitignore$|\.nvmrc$|pnpm-workspace\.yaml$|pnpm-lock\.yaml$|package\.json$|.*/preview\.html$|.*/thank-you-preview\.html$)'

# Pro-owned source. Touching one of these makes a commit worth checking against the
# authoritative Pro lists; it does not by itself make the bullet Pro-only.
PRO_PATHS='^(includes/Pro/|packages/blocks/src/pro/|templates/layouts/pro/)'

git log --no-merges --format='%h%x09%s' "$@" | while IFS=$'\t' read -r sha subj; do
    files=$(git show --pretty= --name-only "$sha")
    if [ -z "$files" ]; then
        printf 'EMPTY   %s  %s\n' "$sha" "$subj"
        continue
    fi
    if [ -z "$(printf '%s\n' "$files" | grep -vE "$DROP_PATHS")" ]; then
        printf 'DROP    %s  %s\n' "$sha" "$subj"
    elif printf '%s\n' "$files" | grep -qE "$PRO_PATHS"; then
        printf 'PRO?    %s  %s\n' "$sha" "$subj"
    else
        printf 'BULLET? %s  %s\n' "$sha" "$subj"
    fi
done
