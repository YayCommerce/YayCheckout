<?php
/**
 * Soft steps: the block markup of template 09, printed by LayoutTemplateCatalogue::markup(). Its look is
 * design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * cart icon's guard, the policy links and the address sections. Keep the markup as the editor saves it: a
 * hand edit that differs by one class or style makes the editor flag the block as invalid.
 *
 * One 560px column of filled, borderless fields under a full-width header bar that is the collapsible Order
 * Summary's header (logo, step dots, cart icon and total); the total opens a grey band below the bar. One step at a time (Delivery, Options, Payment), each
 * headed by a large question; Continue / Back / Place Order follow the step as pill buttons.
 * The address order follows WooCommerce's Shipping destination when the layout is created: with shipping
 * main, Step 1 asks for Shipping and Step 3 offers "Billing address is the same as shipping"; with billing
 * main, Step 1 asks for Billing and offers a different shipping address there, before the rates in Step 2;
 * with shipping forced to billing, Billing alone.
 */

defined( 'ABSPATH' ) || exit;

// The block markup prints as the editor saves it, so a PHP tag inside a branch or a function can't be indented.
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

// Each link prints only when its page exists, and the sentences read correctly without it.
$yaycheckout_terms_url   = wc_terms_and_conditions_page_id() > 0 ? (string) wc_get_page_permalink( 'terms' ) : '';
$yaycheckout_privacy_url = get_privacy_policy_url();

$yaycheckout_link = static function ( string $url, string $label ): string {
    return '' === $url ? '' : sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
};

$yaycheckout_terms_link   = $yaycheckout_link( $yaycheckout_terms_url, __( 'Terms and Conditions', 'yaycheckout' ) );
$yaycheckout_privacy_link = $yaycheckout_link( $yaycheckout_privacy_url, __( 'Privacy Policy', 'yaycheckout' ) );

if ( '' !== $yaycheckout_terms_link && '' !== $yaycheckout_privacy_link ) {
    /* translators: 1: Terms and Conditions link, 2: Privacy Policy link */
    $yaycheckout_terms_sentence = sprintf( esc_html__( 'By proceeding with your purchase you agree to our %1$s and %2$s.', 'yaycheckout' ), $yaycheckout_terms_link, $yaycheckout_privacy_link );
} elseif ( '' !== $yaycheckout_terms_link . $yaycheckout_privacy_link ) {
    /* translators: %s: Terms and Conditions or Privacy Policy link */
    $yaycheckout_terms_sentence = sprintf( esc_html__( 'By proceeding with your purchase you agree to our %s.', 'yaycheckout' ), $yaycheckout_terms_link . $yaycheckout_privacy_link );
} else {
    $yaycheckout_terms_sentence = '';
}

// WooCommerce's Shipping destination when the layout is created. Step 2's "Delivering to" line reads the main address.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();
$yaycheckout_main        = 'shipping' === $yaycheckout_destination ? 'shipping' : 'billing';

// Shipping and Billing hold the same fields; $type is 'shipping' or 'billing'. Labels are hidden, so every field has a placeholder.
$yaycheckout_address_fields = static function ( string $type ): void {
    // The State column shows only while its State field isn't empty: a country without states leaves no gap.
    // One blockId per section, since a layout can hold both; each matches BlockEmptiness::BLOCK_ID_PATTERN.
    $yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
    ?>
<!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"top":"12px","left":"12px"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_2","label":"<?php esc_html_e( 'Apartment, suite, etc.', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Apartment, suite, etc.', 'yaycheckout' ); ?>","disclosure":"link","disclosureText":"<?php esc_html_e( '+ Add apartment, suite, etc.', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"verticalAlignment":"bottom","isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"top":"12px","left":"12px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom is-not-stacked-on-mobile"><!-- wp:column {"verticalAlignment":"bottom"} -->
<div class="wp-block-column is-vertically-aligned-bottom"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"29.41%","yaycheckoutVisibility":{"action":"show","rules":[{"type":"block","block":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","is":"not_empty"}]}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:29.41%"><!-- wp:yaycheckout/field {"blockId":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","fieldKey":"<?php echo esc_attr( $type ); ?>_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"29.41%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:29.41%"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Phone (optional)', 'yaycheckout' ); ?>"} /-->
<?php
};
?>
<!-- wp:yaycheckout/checkout -->
<!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Order summary', 'yaycheckout' ); ?>","collapsible":true,"style":{"border":{"bottom":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"blockGap":"0"}}} -->
<!-- wp:yaycheckout/order-summary-header -->
<!-- wp:group {"style":{"layout":{"selfStretch":"fill","flexSize":null},"spacing":{"padding":{"left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1080px"}} -->
<div class="wp-block-group" style="padding-right:24px;padding-left:24px"><!-- wp:group {"style":{"dimensions":{"minHeight":"56px"},"spacing":{"blockGap":{"top":"8px","left":"16px"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:56px"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":24} /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"17px","fontWeight":"600","letterSpacing":"-0.01em"}}} /-->
<?php endif; ?>

<!-- wp:yaycheckout/steps-progress {"variant":"dots","trackColor":"var(--yaycheckout-design-border)","upcomingTextColor":"#949494","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/order-summary-toggle {"showChevron":false,"style":{"spacing":{"blockGap":"8px"},"typography":{"fontSize":"14px"}}} -->
<?php if ( \YayCheckout\Blocks\IconRegistry::has( 'cart' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/cart","style":{"dimensions":{"width":"18px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px","fontWeight":"600"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:14px;font-weight:600"><span class="yaycheckout-live-value" data-yaycheckout-value="cart_total" data-fallback="" data-prefix="" data-suffix=""><?php esc_html_e( 'Order total', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph -->
<!-- /wp:yaycheckout/order-summary-toggle --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-header -->

<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"color":{"background":"#f5f5f7"},"border":{"top":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"20px","right":"24px","bottom":"20px","left":"24px"}}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"12px"},"typography":{"fontSize":"14px"}},"layout":{"type":"constrained","contentSize":"560px"}} -->
<div class="wp-block-group" style="font-size:14px"><!-- wp:yaycheckout/order-summary-items {"showLabels":false,"thumbnailSize":52,"thumbnailRadius":12} /-->

<!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/coupon-button {"backgroundColor":"yaycheckout-text","textColor":"yaycheckout-background","buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","style":{"border":{"radius":"12px"},"typography":{"fontSize":"14px","fontWeight":"600"},"spacing":{"padding":{"left":"18px","right":"18px"}}}} /-->
<!-- /wp:yaycheckout/coupon-form -->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"}},"typography":{"fontSize":"14px"},"yaycheckout":{"label":{"color":{"text":"var:preset|color|yaycheckout-muted"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"style":{"yaycheckout":{"label":{"typography":{"fontSize":"16px","fontWeight":"600"}},"value":{"typography":{"fontSize":"16px","fontWeight":"600"}}}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"56px","right":"24px","bottom":"80px","left":"24px"},"blockGap":"40px"}},"layout":{"type":"constrained","contentSize":"560px"}} -->
<div class="wp-block-group" style="padding-top:56px;padding-right:24px;padding-bottom:80px;padding-left:24px"><!-- wp:yaycheckout/steps {"layout":"one-at-a-time","showHeadings":false} -->
<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Delivery', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"},"blockGap":"32px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"34px","fontWeight":"600","lineHeight":"1.15","letterSpacing":"-0.02em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:34px;font-weight:600;letter-spacing:-0.02em;line-height:1.15"><?php esc_html_e( 'Where should we deliver?', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:yaycheckout/login {"textColor":"yaycheckout-muted","text":"<?php esc_html_e( 'Have an account? Sign in for a faster checkout.', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Sign in', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px"}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'Email', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /-->

<?php if ( 'shipping' === $yaycheckout_destination ) : ?>
<!-- wp:yaycheckout/shipping {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping --><?php else : ?>
<!-- wp:yaycheckout/billing {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing --><?php endif; ?>
<?php if ( 'billing' === $yaycheckout_destination ) : ?>


<!-- wp:yaycheckout/different-address {"shipLabel":"<?php esc_html_e( 'Ship to a different address', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/shipping {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping --><?php endif; ?></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Options', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"},"blockGap":"32px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"34px","fontWeight":"600","lineHeight":"1.15","letterSpacing":"-0.02em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:34px;font-weight:600;letter-spacing:-0.02em;line-height:1.15"><?php esc_html_e( 'When do you need it?', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"4px"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"15px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:15px"><?php esc_html_e( 'Delivering to', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/field-value {"textColor":"yaycheckout-muted","fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_address_1","<?php echo esc_attr( $yaycheckout_main ); ?>_city","<?php echo esc_attr( $yaycheckout_main ); ?>_postcode"],"style":{"typography":{"fontSize":"15px"}}} /-->

<!-- wp:yaycheckout/go-to-step {"buttonText":"<?php esc_html_e( 'Change', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px","fontWeight":"400"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/shipping-methods {"showTitle":false,"layout":"checkmark","style":{"spacing":{"blockGap":"12px"},"yaycheckout":{"background":"#f5f5f7","border":"#f5f5f7","borderHover":"#949494","borderWidth":"2px","radius":"16px"}}} /-->

<!-- wp:yaycheckout/order-note {"placeholder":"<?php esc_html_e( 'Notes about your order.', 'yaycheckout' ); ?>","disclosure":"checkbox","disclosureText":"<?php esc_html_e( 'Add a note to your order', 'yaycheckout' ); ?>"} /-->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Payment', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"},"blockGap":"32px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"34px","fontWeight":"600","lineHeight":"1.15","letterSpacing":"-0.02em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:34px;font-weight:600;letter-spacing:-0.02em;line-height:1.15"><?php esc_html_e( 'How would you like to pay?', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"4px"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"15px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:15px"><?php esc_html_e( 'Shipping', 'yaycheckout' ); ?> · <span class="yaycheckout-live-value" data-yaycheckout-value="cart_shipping_total" data-fallback="<?php esc_attr_e( 'Free', 'yaycheckout' ); ?>." data-prefix="" data-suffix="."><?php esc_html_e( 'Shipping', 'yaycheckout' ); ?>.</span></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/go-to-step {"step":2,"buttonText":"<?php esc_html_e( 'Change', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"15px","fontWeight":"400"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/payment {"showTitle":false,"layout":"grid","style":{"spacing":{"blockGap":"12px"},"color":{"background":"#f5f5f7"},"border":{"radius":"16px"},"yaycheckout":{"backgroundSelected":"#f5f5f7","backgroundHover":"#f5f5f7","border":"#f5f5f7","borderHover":"#949494","borderWidth":"2px"}}} /-->
<?php if ( 'shipping' === $yaycheckout_destination ) : ?>

<!-- wp:yaycheckout/different-address {"sameLabel":"<?php esc_html_e( 'Billing address is the same as shipping', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/billing {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php endif; ?>
<?php if ( '' !== $yaycheckout_terms_sentence ) : ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"13px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:13px"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:yaycheckout/terms {"textColor":"yaycheckout-muted","style":{"typography":{"fontSize":"13px"}}} /-->
<!-- /wp:yaycheckout/step -->
<!-- /wp:yaycheckout/steps -->

<!-- wp:group {"style":{"spacing":{"blockGap":"14px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"dimensions":{"width":"var:preset|dimension|100"},"typography":{"textAlign":"center","fontSize":"16px"},"spacing":{"padding":{"top":"15px","bottom":"15px"}}},"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":1}]}} /-->

<!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue to payment', 'yaycheckout' ); ?>","style":{"dimensions":{"width":"var:preset|dimension|100"},"typography":{"textAlign":"center","fontSize":"16px"},"spacing":{"padding":{"top":"15px","bottom":"15px"}}},"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":2}]}} /-->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Place Order', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022 · \u0022 data-suffix=\u0022\u0022\u003e <?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","style":{"typography":{"fontSize":"16px","textAlign":"center"},"spacing":{"padding":{"top":"15px","bottom":"15px"}},"dimensions":{"width":"var:preset|dimension|100"}},"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":"last"}]}} /-->

<!-- wp:yaycheckout/back-button {"buttonText":"<?php esc_html_e( 'Back', 'yaycheckout' ); ?>","firstStep":"hide","style":{"dimensions":{"width":"var:preset|dimension|100"},"typography":{"textAlign":"center","fontSize":"15px","fontWeight":"500"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"12px","bottom":"12px"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/checkout -->
