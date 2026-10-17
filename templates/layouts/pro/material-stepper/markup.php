<?php
/**
 * Material stepper: the block markup of template 11, printed by LayoutTemplateCatalogue::markup(). Its look is
 * design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * lock icon's guard, the policy links and the address sections. Keep the markup as the editor saves it: a
 * hand edit that differs by one class or style makes the editor flag the block as invalid.
 *
 * A white header (logo or site title with "Checkout", Secure checkout) over a two-column layout: left a white
 * card with a vertical-timeline Steps block (Contact, address, Shipping options, Payment options) and right a
 * sticky Order Summary card. The address order follows WooCommerce's Shipping destination when the layout is
 * created: with shipping main, Step 2 is Shipping and Step 4 offers "Billing address is the same as shipping"
 * with the Billing fields; with billing main, Step 2 is Billing and Step 4 offers "Shipping address is the
 * same as billing", ticked, with the Shipping fields; with shipping forced to billing, Billing alone.
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

// WooCommerce's Shipping destination when the layout is created.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();
$yaycheckout_step2_title = 'shipping' === $yaycheckout_destination ? __( 'Shipping address', 'yaycheckout' ) : __( 'Billing address', 'yaycheckout' );

// Shipping and Billing hold the same fields; $type is 'shipping' or 'billing'. The mock puts Phone on the Contact
// step, but a Field must sit in an address section, and a billing_phone there is blanked when billing copies shipping.
$yaycheckout_address_fields = static function ( string $type ): void {
	// The State column shows only while its State field isn't empty: a country without states leaves no gap.
	// One blockId per section, since a layout can hold both; each matches BlockEmptiness::BLOCK_ID_PATTERN.
	$yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
	?>
<!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"left":"16px"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_2","label":"<?php esc_html_e( 'Apartment, suite, etc.', 'yaycheckout' ); ?>","disclosure":"link","disclosureText":"<?php esc_html_e( '+ Add apartment, suite, etc.', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"verticalAlignment":"bottom","isStackedOnMobile":false,"style":{"spacing":{"blockGap":{"left":"16px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom is-not-stacked-on-mobile"><!-- wp:column {"verticalAlignment":"bottom","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:50%"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%","yaycheckoutVisibility":{"action":"show","rules":[{"type":"block","block":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","is":"not_empty"}]}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"blockId":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","fieldKey":"<?php echo esc_attr( $type ); ?>_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom"} -->
<div class="wp-block-column is-vertically-aligned-bottom"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>"} /-->
	<?php
};
?>
<!-- wp:group {"tagName":"header","style":{"color":{"background":"#ffffff"},"border":{"bottom":{"color":"#dddddd","width":"1px","style":"solid"}},"spacing":{"padding":{"left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1080px"}} -->
<header class="wp-block-group has-background" style="border-bottom-color:#dddddd;border-bottom-style:solid;border-bottom-width:1px;background-color:#ffffff;padding-right:24px;padding-left:24px"><!-- wp:group {"style":{"dimensions":{"minHeight":"64px"},"spacing":{"blockGap":"16px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:64px"><!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":32} /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"20px","fontWeight":"400","letterSpacing":"-0.01em"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"20px","fontWeight":"400","letterSpacing":"-0.01em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:20px;font-weight:400;letter-spacing:-0.01em"><?php esc_html_e( 'Checkout', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><?php if ( \YayCheckout\Blocks\IconRegistry::has( 'lock' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/lock","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"18px"}}} /-->
<?php elseif ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/icon' ) ) : ?>
<!-- wp:icon {"icon":"core/shield","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"18px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#2f8a48"},"typography":{"fontSize":"13px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#2f8a48;margin-top:0;margin-bottom:0;font-size:13px;font-weight:500"><?php esc_html_e( 'Secure checkout', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"32px","right":"24px","bottom":"80px","left":"24px"}}},"layout":{"type":"constrained","contentSize":"1080px"}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:80px;padding-left:24px"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"24px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"style":{"color":{"background":"#ffffff"},"border":{"radius":"8px"},"spacing":{"padding":{"top":"8px","right":"24px","bottom":"8px","left":"24px"},"blockGap":"0"}}} -->
<div class="wp-block-group has-background" style="border-radius:8px;background-color:#ffffff;padding-top:8px;padding-right:24px;padding-bottom:8px;padding-left:24px"><!-- wp:yaycheckout/steps {"layout":"timeline","showHeadings":true,"style":{"spacing":{"blockGap":"12px"}}} -->
<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Contact information', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"16px","right":"0","bottom":"20px","left":"0"},"blockGap":"16px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'you@example.com', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Sign in', 'yaycheckout' ); ?>","style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"}}} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"24px","right":"24px"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php echo esc_html( $yaycheckout_step2_title ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"16px","right":"0","bottom":"20px","left":"0"},"blockGap":"16px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}}} -->
<div class="wp-block-group"><?php if ( 'shipping' === $yaycheckout_destination ) : ?>
<!-- wp:yaycheckout/shipping {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping -->
<?php else : ?>
<!-- wp:yaycheckout/billing {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php endif; ?>

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/back-button {"buttonText":"<?php esc_html_e( 'Back', 'yaycheckout' ); ?>","firstStep":"hide","style":{"color":{"background":"transparent","text":"#3858e9"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"16px","right":"16px"}}}} /-->

<!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"24px","right":"24px"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Shipping options', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"16px","right":"0","bottom":"20px","left":"0"},"blockGap":"16px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"16px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/shipping-methods {"showTitle":false,"layout":"cards","style":{"spacing":{"blockGap":"2px"},"yaycheckout":{"background":"transparent","backgroundSelected":"rgba(56,88,233,0.06)","backgroundHover":"#f0f0f1","border":"transparent","radio":"#757575","radioSelected":"#3858e9","radius":"8px","borderWidth":"0px"}}} /-->

<!-- wp:yaycheckout/order-note {"placeholder":"<?php esc_html_e( 'Notes about your order.', 'yaycheckout' ); ?>","disclosure":"checkbox","disclosureText":"<?php esc_html_e( 'Add a note to your order', 'yaycheckout' ); ?>"} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/back-button {"buttonText":"<?php esc_html_e( 'Back', 'yaycheckout' ); ?>","firstStep":"hide","style":{"color":{"background":"transparent","text":"#3858e9"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"16px","right":"16px"}}}} /-->

<!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"24px","right":"24px"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Payment options', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"16px","right":"0","bottom":"20px","left":"0"},"blockGap":"16px"}}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"16px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/payment {"showTitle":false,"layout":"cards","style":{"spacing":{"blockGap":"2px"},"color":{"background":"transparent"},"border":{"radius":"8px"},"yaycheckout":{"backgroundSelected":"rgba(56,88,233,0.06)","backgroundHover":"#f0f0f1","border":"transparent","radio":"#757575","radioSelected":"#3858e9","borderWidth":"0px"}}} /-->
<?php if ( 'billing_only' !== $yaycheckout_destination ) : ?>

<!-- wp:yaycheckout/different-address {"sameLabel":"<?php esc_html_e( 'Billing address is the same as shipping', 'yaycheckout' ); ?>","ticked":"same","sameShipLabel":"<?php esc_html_e( 'Shipping address is the same as billing', 'yaycheckout' ); ?>"} /-->
<?php if ( 'shipping' === $yaycheckout_destination ) : ?>

<!-- wp:yaycheckout/billing {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php else : ?>

<!-- wp:yaycheckout/shipping {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping -->
<?php endif; ?>
<?php endif; ?>
<?php if ( '' !== $yaycheckout_terms_sentence ) : ?>

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"left":"8px","right":"8px"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;padding-right:8px;padding-left:8px;font-size:12px"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:yaycheckout/terms {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"padding":{"left":"8px","right":"8px"}}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/back-button {"buttonText":"<?php esc_html_e( 'Back', 'yaycheckout' ); ?>","firstStep":"hide","style":{"color":{"background":"transparent","text":"#3858e9"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"16px","right":"16px"}}}} /-->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Place Order', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022 · \u0022 data-suffix=\u0022\u0022\u003e <?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"14px","fontWeight":"500","whiteSpace":"nowrap"},"border":{"radius":"9999px"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"24px","right":"24px"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->
<!-- /wp:yaycheckout/steps --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"380px"} -->
<div class="wp-block-column" style="flex-basis:380px"><!-- wp:group {"style":{"position":{"type":"sticky","top":"24px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Order summary', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"radius":"8px"},"spacing":{"padding":{"top":"20px","right":"24px","bottom":"20px","left":"24px"},"blockGap":"16px"}}} -->
<!-- wp:yaycheckout/order-summary-header -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/order-summary-toggle -->
<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"16px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h3 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:16px;font-weight:500"><?php esc_html_e( 'Order summary', 'yaycheckout' ); ?></h3>
<!-- /wp:heading -->
<!-- /wp:yaycheckout/order-summary-toggle -->

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"13px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:13px"><span class="yaycheckout-live-value" data-yaycheckout-value="cart_item_count" data-fallback="" data-prefix="" data-suffix=" <?php esc_attr_e( 'items', 'yaycheckout' ); ?>"><?php esc_html_e( 'Item count', 'yaycheckout' ); ?> <?php esc_html_e( 'items', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-header -->

<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"spacing":{"blockGap":"16px"}}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false} /-->

<!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>","style":{"border":{"top":{"color":"#e0e0e0","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"}}}} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent","text":"#3858e9"},"typography":{"fontSize":"14px","fontWeight":"500"},"border":{"radius":"4px"},"spacing":{"padding":{"left":"16px","right":"16px"}}}} /-->
<!-- /wp:yaycheckout/coupon-form -->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"#e0e0e0","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"}},"typography":{"fontSize":"14px"},"yaycheckout":{"label":{"color":{"text":"#757575"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"style":{"border":{"top":{"color":"#e0e0e0","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"}},"yaycheckout":{"label":{"typography":{"fontSize":"16px","fontWeight":"500"}},"value":{"typography":{"fontSize":"22px","fontWeight":"500"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
