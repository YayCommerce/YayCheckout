# Pitfalls from templates 01–03

Each of these shipped once and needed a follow-up fix. The commit that fixed it shows the right shape. Read this list before Phase 4 (writing the markup), and check it again in Phase 6.

## Markup

- **Use only layout types core knows.** These are `constrained`, `flex` and `default`. `"layout":{"type":"flow"}` crashed the editor with `getOrientation` (aee0584). Hand-typed block JSON is the usual source, which is one more reason to build in the editor.
- **Escape HTML inside attribute JSON the way the editor does:** `<`, `>` and `"`. A raw `"` or `<` in Place Order's `buttonText` broke the JSON parse, so the label lost its live total and its typography (f95c6b8, ccf4cdd). Split express repeated the mistake (8aae3b4, 8e19eff, then dbf84bc). To check, grep the attribute JSON of every block comment for a raw `<` or `\"`.
- **Keep the markup byte-for-byte as the editor saves it.** If one class or inline style differs from the comment JSON, the block shows "This block contains unexpected or invalid content". Drop the legacy `align` the editor writes beside `typography.textAlign` only if the editor does so itself.
- **Full width is `"dimensions":{"width":"var:preset|dimension|100"}`.** Add `textAlign: center` for a button (1a1c384). Split express used `"width":"100%"`, which differs from the other templates.
- **Give every button an explicit `fontSize`.** Otherwise the theme's button size leaks in; the Apply button did this in f95c6b8.

## Addresses

- **Handle all three Shipping destinations.** `CheckoutDifferentAddress::destination()` returns `shipping`, `billing` or `billing_only`. The order is fixed when the layout is created, so all three branches must be written (0e1e52f, 2499c13). Check the `different-address` block's `shipLabel` and `sameLabel` wording in each branch (1a1c384).
- **Print Billing and Shipping fields from one closure.** That closure is `$yaycheckout_address_fields( $type )` in `classic-two-column/markup.php`, so the two sections can't drift apart. Reordering fields, for example country first, must keep the same `fieldKey` values and `required` flags. Otherwise checkout loses a required field.
- **Hide the State column when it's empty.** Wrap it in `yaycheckoutVisibility` `show` when the `block` is `not_empty`. Each section needs its own `blockId` matching `BlockEmptiness::BLOCK_ID_PATTERN`: `bilstate` or `shpstate` (1a1c384).

## Separators and decorations

- **A decoration that belongs to a block hides with that block.** Example: the "OR" row under Express Pay. Give Express Pay `blockId` `exprspay` and hide the OR group when that block `is: empty` (2499c13).
- **Print policy links and the terms sentence only for pages that exist.** Use the shared preamble's `$yaycheckout_terms_sentence` and `$yaycheckout_policy_links` (8e19eff).

## Design tokens

- **`colours.background` is the page.** A coloured panel, such as Split express's `#1e1e1e` left column, sets its colour on its own column or group block. If the editor canvas shows the panel colour everywhere, the colour went into the tokens by mistake.
- **Set `inputs.background` only when the mock's inputs have their own fill.** An empty one is transparent and shows the page or the panel behind the field.
- **Leave `custom_css` empty.** The editor canvas never prints it, so the template would look different there than on the storefront. A look that needs CSS is an Out item: build it as a block setting or token first (Phase 3).
- **Write `design.json` with this skill's `scripts/design_json.sh`.** The schema gains keys over time: `inputs.focus` and `focus_style` in 8cbc7a8, the `checkboxes` section in 13ba74f. A hand-written file goes stale and fails `LayoutTemplateCatalogueTest`.
- **Take the checkbox values from the mock.**
- **The primary buttons' radius, weight and letter case are `buttons` tokens**, not Place Order or Step button block settings: a block's own border radius or typography beats the token, and Design → Buttons then does nothing. A pill is `buttons.radius: 32`. A Coupon button that differs from Place Order keeps its own settings.
- **A block colour that equals a design token is not hex.** Leave it unset when the block's default already paints that token: primary step, place-order and coupon buttons, shipping/payment borders and hover, the step card's white. Otherwise use the "Design: X" swatch. Core attributes store the slug (`"textColor":"yaycheckout-muted"`, `"borderColor":"yaycheckout-border"`, or `var:preset|color|yaycheckout-border` on one side of a border). Custom colour attributes store `var(--yaycheckout-design-<token>)` (badge, steps-progress track, Divider lines' border).
- **Text on core paragraphs and groups stays unset** unless it is a specific colour (primary, trust green, text on an inverted panel), so it takes the body text. Secondary text on YayCheckout blocks (terms, login, return to cart, summary labels, steps progress) uses "Design: Muted".
- **Shipping/payment radios get no block colour.** Their unticked ring reads Design → Radios border, then the Inputs border, as loose radios do. Set `radios.border` only when it differs from `inputs.border`.
- **Hex is only for colours the Design must not change:** inverted panels and what sits on them, the trust green, white cards on a grey page, soft greys.
- **Write a translucent colour as hex with alpha, never `rgb()` or `rgba()`.** The storefront runs block styles through WordPress's `safecss_filter_attr()`, which drops any `rgb()`/`rgba()` value, while the editor keeps it. So the canvas looks right and the storefront doesn't: Editorial fashion's 35% white dividers printed solid white. Alpha is the hex pair: 0.35 → `59`, 0.6 → `99`, 0.85 → `d9` (`#ffffff59`). To check, `git grep -n 'rgba\?(' -- 'templates/layouts/**/markup.php'` prints nothing.

## Interaction

- **With `inputs.label: "floating"`, click every checkbox and radio.** Test the terms, create-account and different-address checkboxes and the shipping and payment radios. A floating-label rule once made nested checkboxes unclickable (1415bc7).
- **In a flex row, button labels need `white-space: nowrap`.** Place Order's terms text squeezed the Back button (becacbb). Check the row at a narrow width.

## Icons

- **Icons must be filled outlines.** Core strips `stroke`, so the mock's line icons must be redrawn as filled paths. They go in `assets/images/icons/` and are listed in `IconRegistry` (340b2c7).
- **`wp_register_icon` exists only on WP 7.1+.** Guard each icon with `IconRegistry::has( '<name>' )`, then check that `core/icon` is registered, and fall back to `core/shield` or nothing. A parent block that hosts an icon needs `core/icon` in its `ALLOWED_BLOCKS` and SVG in its `render.php` kses allowlist.

## Fidelity

- **Every template so far needed a second pass against the mock** (6d7ac60, 8e19eff, 1a1c384). Budget for one: compare side by side, then fix each difference as its own small `fix(templates)` commit.
