# 2026-09-22 — Template catalogue v1.1 + layout sketch thumbnails

Branch `dev/edd`, commit `7c66d52`. Plan: `plans/260922-1131-checkout-template-catalogue-and-thumbnails`. Brainstorm: `plans/reports/brainstorm-260922-1131-checkout-template-catalogue-and-thumbnails-report.md`.

## What changed

- `LayoutTemplateCatalogue`: 18 made-up fixtures → the 11 templates from the design bundle (`WooCommerce Checkout Templates_v1.1.html`). Order is the Free-plan mechanism: Classic two-column, Split express, Editorial fashion sit first so the untouched `FREE_UNLOCKED_COUNT = 3` rule unlocks them. New `LayoutTemplateCatalogueTest` pins order, count, lock sets, categories.
- New layout picker draws a 120×80 inline-SVG sketch per slug (`TemplateThumbnail`, `TemplateThumbnailPrimitives`, `template-thumbnail-sketches.tsx`) instead of a grey box; the Free "N more templates" strip shows the real locked sketches faded. Unknown slug → generic fallback.
- Still out of scope: `create_draft` seeds identical markup for every slug.

## Decisions

- SVG in the SPA over image assets or Tailwind divs: theme-aware via tokens, no schema/REST/release-list change, dashed/zig-zag/pill shapes trivial.
- Brand colours (terracotta, PayPal yellow) flattened to `primary` — these sketch layout, not theme.
- Component files PascalCase per `docs/code-standards.md`; the sketch table stays kebab as a data module.

## Gotchas worth remembering

- The design bundle is a double-nested artifact: an outer manifest of gzipped base64 pages, each itself a bundle with a `__bundler/template` JSON string. Two small Python passes + headless Chrome (`--headless=new --screenshot`) gave clean reference renders; screenshots kept under `plans/reports/assets/checkout-templates-v1.1/`.
- `--muted` (#f4f4f5) is invisible as a *fill* on a white card. Sketches use `bg-muted` on the wrapper as the "page" and `fill-background` for surfaces — the same relationship the real screenshots have.
- `Record<string, ReactNode>[slug]` reaches `Object.prototype` for slugs like `constructor`; `Object.hasOwn` guard costs one line.
- The gitignored `phpunit.xml` had vanished; recreate from `.dist` with password `root` and the Local socket host before `composer test`.
- Verifying the Free SPA branch needs the dev server restarted with `YAYCHECKOUT_PLAN=free` (compile-time constant), which means taking over the user's `pnpm dev` process — ask first.
