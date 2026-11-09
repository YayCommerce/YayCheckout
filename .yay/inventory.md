<!--
Purpose: the reusable surface of YayCheckout, so a change extends it instead of duplicating it.
Update when: a helper, service, option key or shared JS utility is added, renamed or
removed. `/yay:harness-scout --check` fails on a listed symbol that no longer exists.
-->

# YayCheckout — reusable surface

Read this before adding a function, helper, option key or utility. State what you reuse;
justify what you add. The plan-time reuse gate reads this file.

<!-- yay:generated:listing -->
## Helpers        (includes/Helpers/)
AdminUrlHelper::edit_layout()                       SPA editor URL for a layout
ButtonClassHelper::classes()                        button class list for block buttons
CheckoutHookScanHelper::scan()                      resolve checkout-hook callbacks to their plugin/theme
CheckoutSetupHelper::shipping_needs_setup() / payment_needs_setup() / cart_has_shippable_item() / *_settings_url()
DateHelper::to_iso8601() / parse() / now_iso8601()  wire timestamps
ExceptionHelper::message()                          decode an escaped exception message for JSON/logs
GatewayCompatibilityHelper::list()                  classic + block-only gateways
IntegrationsHelper::get_integrations()              integrations list (filter yaycheckout_integrations)
JsonElementHelper::html() / print()                 JSON <script> elements for view scripts
KsesHelper::storefront_html()                       kses for layout view markup
LocalizeHelper::get_admin_data() / get_meta()       window.yayCheckoutAdmin / window.yayCheckoutMeta
Logger::error() / warning() / info() / debug() / exception()   WooCommerce log, source yaycheckout
PluginBlockCatalogue::entries() / targets() / editor_data() / spots_in() / spot_for() / block()
PluginHeaderHelper::describe()                      plugin header facts
PreviewToken::mint() / parse() / checkout_url() / with_token()   signed preview links
StoreEnvironmentHelper::describe()                  versions, theme, block checkout, safe mode

## Services       (includes/Services/, includes/Pro/Services/)
LayoutMarkupReader::publish_error()                 the publish rules (mirrored in publish-guard.ts)
LayoutMarkupReader::slots() / wc_hooks() / fields() / extra_fields() / order_note() / first_block()
LayoutMarkupReader::has_checkout() / has_billing() / has_shipping() / has_different_address() / has_order_note() / order_modes()
LayoutMarkupReader::plain_text() / rich_text() / text() / extra_field_key() / allow_rgb_color() / options() / flatten()
LayoutAssignment::pick() / outlook() / reads_cart() / matches()   which layout a shopper gets (Pro rules)
RequiredFieldOverlay::apply() / apply_order_note() / is_required()
RequiredIndicator::html() / swap_in() / script_payload()
ExtraFieldSubmission::collect()
Disclosure::mode() / text() / open() / close() / open_if() / close_if() / chevron()
OrderSummaryCollapse::state() / of_layout() / has_toggle() / default_label()
Pro: LayoutImportReview::from_envelope() / rules_from_file() · AssignmentStoreData::get()

## Option keys    yaycheckout_settings, yaycheckout_settings_updated_at, yaycheckout_reviewed, yaycheckout_setup_done, yaycheckout_active_layout, yaycheckout_preview_order_id (Pro), yaycheckout_license_key / yaycheckout_license_info (Pro, admin-shell)
## Transients     yaycheckout_compatibility_catalogue
## Post meta      _yaycheckout_rules, _yaycheckout_self_test, _yaycheckout_design, _yaycheckout_template, _yaycheckout_logged_out_content, _yaycheckout_thank_you_content
## Order meta     _yaycheckout_extra_fields, _yaycheckout_preview_order (Pro)
## WC session     yaycheckout_extra_field_values, yaycheckout_layout_pin (+ cookie yaycheckout_layout_pin)
## Hooks exposed  see `.yay/api.yml` — not duplicated here
## JS utilities   packages/blocks/src/shared/{allowed-blocks,address-fields,address-template,design-css,editor-data,extra-field-keys,form-text,layout-editor,pro-blocks,register-dynamic-block,showable-fields,text-formats,visibility-rules}.ts(x) (also the SPA's @blocks-shared); apps/admin/src/lib/helpers/{date,response,form,layout,compatibility,clipboard,infinite-list}.helper.ts
<!-- /yay:generated:listing -->

## Annotations
<!-- Human-owned: the "ALWAYS use this" lines, one per symbol that has a wrong twin. -->

## NOT DERIVED

<!-- yay:generated:gaps -->
- Settings keys and design tokens are entries inside `Models\Settings::schema()` and `Models\LayoutDesign::schema()`, not separate symbols. Read the schema; it is the list.
- Hook names built at runtime: `yaycheckout_slot_<name>` (`packages/blocks/src/slot/render.php`), `woocommerce_thankyou_<gateway>`, and the 30 WooCommerce actions `Renderer\WcHooks` re-fires (`WcHooks::HOOKS`).
- Core-owned per-user editor preferences (`core/preferences` `yaycheckout` / `editorTourDone`, `featureToursSeen`) and Font Library posts the design-font installer creates: stored by core, not by a key here.
- Renderer/, Repositories/, Models/ and Engine/ public methods are not listed: they are entry points, not shared helpers.
<!-- /yay:generated:gaps -->

---
<!-- yay:generated:footer -->
Derived by `/yay:harness-scout` from yaycheckout at 062f7926 on 2026-11-09. 32 helper/service classes listed, 0 annotated, 4 surfaces not derived.
<!-- /yay:generated:footer -->
