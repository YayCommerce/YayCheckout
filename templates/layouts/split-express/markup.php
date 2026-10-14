<?php
/**
 * Split express: the block markup of template 03, printed by LayoutTemplateCatalogue::markup().
 * Its look is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * cart link and the footer labels (linked when their page exists). Keep the markup as the
 * editor saves it: a hand edit that differs by one class or style makes the editor flag the
 * block as invalid.
 *
 * Left column is the dark order panel; right column is the form. Shipping comes first as
 * Delivery; Different Address follows Payment. Billing and Shipping hold the structure
 * packages/blocks/src/shared/address-template.ts gives a new section (country first here);
 * change both together. Their keys come from packages/blocks/src/shared/address-fields.ts.
 * Order Summary holds the tree TEMPLATE in packages/blocks/src/order-summary/edit.tsx gives
 * a new one, plus the Coupon Form.
 */

defined( 'ABSPATH' ) || exit;

// Labels always print; a missing page stays plain text so the footer matches the mock.
$yaycheckout_terms_url   = wc_terms_and_conditions_page_id() > 0 ? (string) wc_get_page_permalink( 'terms' ) : '';
$yaycheckout_privacy_url = get_privacy_policy_url();
// WooCommerce creates the refund page as a draft, so it counts only once published.
$yaycheckout_refund_id  = wc_get_page_id( 'refund_returns' );
$yaycheckout_refund_url = $yaycheckout_refund_id > 0 && 'publish' === get_post_status( $yaycheckout_refund_id ) ? (string) get_permalink( $yaycheckout_refund_id ) : '';
// WooCommerce has no shipping-policy page id; a published page with slug "shipping" is enough.
$yaycheckout_shipping_page = get_page_by_path( 'shipping' );
$yaycheckout_shipping_url  = $yaycheckout_shipping_page instanceof WP_Post && 'publish' === $yaycheckout_shipping_page->post_status
    ? (string) get_permalink( $yaycheckout_shipping_page )
    : '';

$yaycheckout_link = static function ( string $url, string $label ): string {
    return '' === $url
        ? esc_html( $label )
        : sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
};

$yaycheckout_terms_link   = $yaycheckout_link( $yaycheckout_terms_url, __( 'Terms and Conditions', 'yaycheckout' ) );
$yaycheckout_privacy_link = $yaycheckout_link( $yaycheckout_privacy_url, __( 'Privacy Policy', 'yaycheckout' ) );

$yaycheckout_terms_sentence = sprintf(
    /* translators: 1: Terms and Conditions link or label, 2: Privacy Policy link or label */
    esc_html__( 'By proceeding with your purchase you agree to our %1$s and %2$s.', 'yaycheckout' ),
    $yaycheckout_terms_link,
    $yaycheckout_privacy_link
);

$yaycheckout_policy_refund   = $yaycheckout_link( $yaycheckout_refund_url, __( 'Refund policy', 'yaycheckout' ) );
$yaycheckout_policy_shipping = $yaycheckout_link( $yaycheckout_shipping_url, __( 'Shipping', 'yaycheckout' ) );
$yaycheckout_policy_privacy  = $yaycheckout_link( $yaycheckout_privacy_url, __( 'Privacy', 'yaycheckout' ) );
$yaycheckout_policy_terms    = $yaycheckout_link( $yaycheckout_terms_url, __( 'Terms', 'yaycheckout' ) );
?>
<!-- wp:group {"style":{"dimensions":{"minHeight":"100vh"}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="min-height:100vh"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"0px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"520px","style":{"color":{"background":"#1e1e1e","text":"#f0f0f0"},"spacing":{"padding":{"top":"32px","right":"40px","bottom":"32px","left":"40px"}}}} -->
<div class="wp-block-column has-text-color has-background" style="color:#f0f0f0;background-color:#1e1e1e;padding-top:32px;padding-right:40px;padding-bottom:32px;padding-left:40px;flex-basis:520px"><!-- wp:group {"style":{"position":{"type":"sticky","top":"0px"},"spacing":{"blockGap":"28px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"elements":{"link":{"color":{"text":"#ffffff"}}},"color":{"text":"#ffffff"}}} /-->
<?php endif; ?>

<!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Your order', 'yaycheckout' ); ?>","style":{"color":{"text":"#f0f0f0"}}} -->
<!-- wp:yaycheckout/order-summary-header -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/order-summary-toggle -->
<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"20px","fontWeight":"400"},"color":{"text":"#ffffff"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#ffffff;margin-top:0;margin-bottom:0;font-size:20px;font-weight:400"><?php esc_html_e( 'Your order', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->
<!-- /wp:yaycheckout/order-summary-toggle --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Edit cart', 'yaycheckout' ); ?>","arrow":"none","style":{"color":{"text":"#949494"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->
<!-- /wp:yaycheckout/order-summary-header -->

<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false,"quantityDisplay":"badge","badgeBackgroundColor":"#ffffff","badgeTextColor":"#1e1e1e"} /-->

<!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>","style":{"color":{"text":"#ffffff"}}} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent","text":"#ffffff"},"border":{"color":"#757575","width":"1px","style":"solid"},"typography":{"fontWeight":"500"}}} /-->
<!-- /wp:yaycheckout/coupon-form -->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"#2f2f2f","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"16px"}},"typography":{"fontSize":"14px"},"color":{"text":"#f0f0f0"},"yaycheckout":{"label":{"color":{"text":"#949494"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"showCurrencyCode":true,"style":{"border":{"top":{"color":"#2f2f2f","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"16px"}},"color":{"text":"#ffffff"},"yaycheckout":{"label":{"typography":{"fontSize":"16px","fontWeight":"600"},"color":{"text":"#ffffff"}},"value":{"typography":{"fontSize":"20px","fontWeight":"600"},"color":{"text":"#ffffff"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary -->

<!-- wp:paragraph {"style":{"color":{"text":"#949494"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#949494;margin-top:0;margin-bottom:0;font-size:12px"><?php esc_html_e( 'Encrypted, secure payment. 30-day free returns.', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"style":{"color":{"background":"#ffffff"}}} -->
<div class="wp-block-column has-background" style="background-color:#ffffff"><!-- wp:group {"style":{"spacing":{"padding":{"top":"32px","right":"40px","bottom":"80px","left":"40px"},"blockGap":"32px"}},"layout":{"type":"constrained","contentSize":"600px"}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:40px;padding-bottom:80px;padding-left:40px"><!-- wp:yaycheckout/express-pay /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"color":{"background":"#dddddd"},"spacing":{"margin":{"top":"0","bottom":"0"}},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<hr class="wp-block-separator has-text-color has-alpha-channel-opacity has-background" style="margin-top:0;margin-bottom:0;background-color:#dddddd;color:#dddddd"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php esc_html_e( 'OR', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:separator {"style":{"color":{"background":"#dddddd"},"spacing":{"margin":{"top":"0","bottom":"0"}},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<hr class="wp-block-separator has-text-color has-alpha-channel-opacity has-background" style="margin-top:0;margin-bottom:0;background-color:#dddddd;color:#dddddd"/>
<!-- /wp:separator --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"fontSize":"20px","fontWeight":"400"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:20px;font-weight:400"><?php esc_html_e( 'Contact information', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'Have an account? Log in', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Log in', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'you@example.com', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/shipping {"title":"<?php esc_html_e( 'Delivery', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/field {"fieldKey":"shipping_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"shipping_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"shipping_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_company","label":"<?php esc_html_e( 'Company', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_address_2","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Apartment, suite, unit, etc. (optional)', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:50%"><!-- wp:yaycheckout/field {"fieldKey":"shipping_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"shipping_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"shipping_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>"} /-->
<!-- /wp:yaycheckout/shipping -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"}}} -->
<p class="has-text-color" style="color:#757575;font-size:12px"><?php esc_html_e( 'Fields marked * are required.', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/shipping-methods {"label":"<?php esc_html_e( 'Shipping options', 'yaycheckout' ); ?>","layout":"grid","style":{"yaycheckout":{"background":"#ffffff","backgroundSelected":"rgba(56,88,233,0.04)","backgroundHover":"#f0f0f0","border":"#dddddd","radio":"#757575","radioSelected":"#3858e9","radius":"4px"}}} /-->

<!-- wp:yaycheckout/payment {"label":"<?php esc_html_e( 'Payment options', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'All transactions are secure and encrypted.', 'yaycheckout' ); ?>","layout":"box","style":{"color":{"background":"#ffffff"},"border":{"radius":"4px"},"yaycheckout":{"backgroundSelected":"rgba(56,88,233,0.04)","backgroundHover":"#f0f0f0","border":"#dddddd","radio":"#757575","radioSelected":"#3858e9"}}} /-->

<!-- wp:yaycheckout/different-address {"sameLabel":"<?php esc_html_e( 'Use shipping address as billing address', 'yaycheckout' ); ?>","shipLabel":"<?php esc_html_e( 'Use shipping address as billing address', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/billing {"title":"<?php esc_html_e( 'Billing address', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/field {"fieldKey":"billing_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"billing_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"billing_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"billing_company","label":"<?php esc_html_e( 'Company', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/field {"fieldKey":"billing_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"billing_address_2","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Apartment, suite, unit, etc. (optional)', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:50%"><!-- wp:yaycheckout/field {"fieldKey":"billing_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"billing_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"billing_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"billing_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>"} /-->
<!-- /wp:yaycheckout/billing -->

<!-- wp:group {"style":{"spacing":{"blockGap":"16px"}}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"color":{"background":"#dddddd"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<hr class="wp-block-separator has-text-color has-alpha-channel-opacity has-background" style="margin-top:0;margin-bottom:0;background-color:#dddddd;color:#dddddd"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"}}} -->
<p class="has-text-color" style="color:#757575;font-size:12px"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Pay', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px","fontWeight":"600","textAlign":"center"},"border":{"radius":"2px"},"dimensions":{"width":"100%"}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"16px"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php echo wp_kses_post( $yaycheckout_policy_refund ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php echo wp_kses_post( $yaycheckout_policy_shipping ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php echo wp_kses_post( $yaycheckout_policy_privacy ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php echo wp_kses_post( $yaycheckout_policy_terms ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
