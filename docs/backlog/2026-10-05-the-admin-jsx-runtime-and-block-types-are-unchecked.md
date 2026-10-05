# The admin's JSX runtime disagrees with its type-check, and nothing type-checks the blocks

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`apps/admin/vite.config.ts` builds JSX with `react({ jsxRuntime: 'classic' })`, which turns each tag into `React.createElement`. `apps/admin/tsconfig.json` sets `"jsx": "react-jsx"`, the automatic runtime, under which `tsc` never asks for `React` in scope. 97 of the 132 `.tsx` files in `apps/admin/src` bind no `React`, so the built `assets/dist/admin/js/main.js` calls the global `React.createElement`. That works only because `externalProd` maps `react` to `window.React`, and `react` is in `$deps` in `RegisterProd` and `RegisterDev`, so WordPress loads it first. In dev, `externalDev` leaves `react` bundled, so JSX uses WordPress's React and hooks use the bundled one. Both are React 18.3.1 today, in WordPress 7.1.2 and in `apps/admin/package.json`. The runtime has been set this way since `9b620fe`.

`packages/blocks/tsconfig.json` exists, but no script runs `tsc` with it, and wp-scripts' Babel strips the types without checking them. Only `packages/blocks/src/shared/export-file.ts` is checked, because the SPA imports it through `@blocks-shared`. `.claude/rules/blocks.md` records that nothing type-checks the blocks' TypeScript. Found by reading the code; not reproduced.

## Why it matters

**Severity:** low: a maintenance or robustness cost.

A passing `tsc --noEmit` says nothing about whether a file's JSX has a `React` to call, since the check and the build assume different runtimes. Once WordPress ships a different React major than the bundled copy, the dev build mixes two Reacts and can break (inferred). In the blocks, a type error such as a wrong attribute type or a misspelled store selector reaches the editor unnoticed.

## Suggested fix

Use one JSX runtime in both files. The automatic runtime matches the code as written; React's `jsx-runtime` module then needs mapping to WordPress's `react-jsx-runtime` script where the supported WordPress has it (the plugin requires 6.5), or bundling. Otherwise keep the classic runtime and set `"jsx": "react"`, so `tsc` flags each file that uses JSX without `React` in scope. Make dev and production resolve `react` the same way. Add a type-check script for `packages/blocks` and run it from `./scripts/run.sh build`. Record any errors it finds on `dev/edd` in `docs/known-issues.md` before fixing them.

## Done when

- [ ] `react()` in `apps/admin/vite.config.ts` and `"jsx"` in `apps/admin/tsconfig.json` select the same JSX runtime.
- [ ] In dev, JSX and hooks use one copy of React: `externalDev` maps `react` as `externalProd` does, or the dev bundle reads no global `React`.
- [ ] A package script type-checks `packages/blocks/src` with `packages/blocks/tsconfig.json`, and it exits 0 on `dev/edd`.
- [ ] `./scripts/run.sh build` runs that script before it builds the blocks.
- [ ] `.claude/rules/blocks.md` names that script instead of saying nothing type-checks the blocks' TypeScript.
