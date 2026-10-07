# Design settings have no effect on the storefront

**Purpose:** a follow-up left open by a review or a change, kept until every box under Done when is ticked.

**Update when:** a Done when condition is met (tick its box), the code it describes changes, or the suggested fix changes. Once every box is ticked, the item is done: remove the file.

**Source:** the 2026-10-05 architecture review, on `trace/docs/harness-renewal`.

## Today

`CheckoutPage::enqueue_scripts()` calls `DesignStyles::enqueue()`, which prints the Design page's tokens as `--yaycheckout-*` custom properties on `:root`, then the merchant's custom CSS, into the inline-only `ScriptName::STYLE_CHECKOUT` handle. Nothing reads the properties: `git grep 'var(--yaycheckout-'` finds nothing, and neither does a search of `assets/dist/`. `STYLE_CHECKOUT` has been inline-only since `75433a6`, and no stylesheet that reads the properties has reached `dev/edd`: a `checkout.css` that read them lived only on the branch `alan/apply-global-tokens-to-fe`, which deleted it again.

As of 2026-10-05, the only code that reads the properties is the new step blocks' style.css on `onyx/feat/accordion-block`. `alan/apply-global-tokens-to-fe`, the branch behind `e5e5642`, prints variables only. Found by reading the code; not reproduced.

## Why it matters

**Severity:** medium: it misleads a merchant or breaks a layout rule.

The Design page (`apps/admin/src/pages/design/DesignPage.tsx`, "Customize checkout colors, typography and spacing.") and the setup wizard's Brand step (`apps/admin/src/components/setup-wizard/BrandStep.tsx`) save settings that change nothing a shopper sees. Only custom CSS works. A merchant who picks colours and checks the storefront concludes the plugin is broken; one who doesn't check ships the theme's defaults believing they're branded.

## Suggested fix

Ship a storefront stylesheet that applies each token to the markup the blocks render (buttons, inputs, headings, borders, text), and load it under `ScriptName::STYLE_CHECKOUT` with the inline properties after it. Or have each block's style.css read the properties, as the step blocks on `onyx/feat/accordion-block` do. A section left at theme default prints no property, so every `var()` needs a fallback that leaves the theme's style in place.

## Done when

- [x] `git grep 'var(--yaycheckout-'` finds a stylesheet that the checkout page loads. — `packages/blocks/src/place-order/style.css` reads `var(--yaycheckout-primary)`, also progress, back-button, steps and step-button
- [ ] Every property `DesignStyles::vars()` can print is read by at least one rule that the checkout page loads.
- [ ] Changing the primary colour on the Design page changes the Place order button's background on the storefront.
- [ ] Changing the input size on the Design page changes the height of the checkout's text inputs on the storefront.
