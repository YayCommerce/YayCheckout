<?php
/**
 * Accordion one-page: the block markup of template 04, printed by LayoutTemplateCatalogue::markup(). Its look
 * is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * lock icon's guard, the policy links and the address sections. Keep the markup as the editor saves it: a
 * hand edit that differs by one class or style makes the editor flag the block as invalid.
 *
 * One 640px column: a header (Back, logo or site title, Secure), "Checkout" with a text Steps Progress,
 * four accordion step cards (Contact, Delivery address, Shipping options, Payment), and a pinned bottom
 * bar holding a collapsible Order Summary that opens upward beside Continue / Place Order.
 */

defined( 'ABSPATH' ) || exit;

// The block markup prints as the editor saves it, so a PHP tag inside a branch or a function can't be indented.
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

// Each link prints only when its page exists, and the sentences read correctly without it.
$yaycheckout_terms_url   = wc_terms_and_conditions_page_id() > 0 ? (string) wc_get_page_permalink( 'terms' ) : '';
$yaycheckout_privacy_url = get_privacy_policy_url();
// WooCommerce creates the refund page as a draft, so it counts only once published.
$yaycheckout_refund_id  = wc_get_page_id( 'refund_returns' );
$yaycheckout_refund_url = $yaycheckout_refund_id > 0 && 'publish' === get_post_status( $yaycheckout_refund_id ) ? (string) get_permalink( $yaycheckout_refund_id ) : '';

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

$yaycheckout_policy_links = array_filter(
    [
        $yaycheckout_link( $yaycheckout_refund_url, __( 'Refund policy', 'yaycheckout' ) ),
        $yaycheckout_link( $yaycheckout_privacy_url, __( 'Privacy', 'yaycheckout' ) ),
        $yaycheckout_link( $yaycheckout_terms_url, __( 'Terms', 'yaycheckout' ) ),
    ]
);

// WooCommerce's Shipping destination when the layout is created: Delivery is the main address
// (Shipping fields when shipping is main, Billing when billing is main or forced); Different Address
// and the other section follow it. Forced-to-billing has only Delivery.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();

// Shipping and Billing hold the same fields; $type is 'shipping' or 'billing'. No Company. Phone is required.
$yaycheckout_address_fields = static function ( string $type ): void {
    // The State column shows only while its State field isn't empty: a country without states leaves no gap.
    // One blockId per section, since a layout holds both; each matches BlockEmptiness::BLOCK_ID_PATTERN.
    $yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
    ?>
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_2","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Apartment, suite, unit, etc. (optional)', 'yaycheckout' ); ?>","disclosure":"link","disclosureText":"<?php esc_html_e( '+ Add apartment, suite, etc.', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:50%"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%","yaycheckoutVisibility":{"action":"show","rules":[{"type":"block","block":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","is":"not_empty"}]}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"blockId":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","fieldKey":"<?php echo esc_attr( $type ); ?>_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>","required":true} /-->
<?php
};
?>
<!-- wp:group {"tagName":"header","style":{"color":{"background":"#ffffff"},"border":{"bottom":{"color":"#dddddd","width":"1px","style":"solid"}},"spacing":{"padding":{"left":"20px","right":"20px"}}},"layout":{"type":"constrained","contentSize":"640px"}} -->
<header class="wp-block-group has-background" style="border-bottom-color:#dddddd;border-bottom-style:solid;border-bottom-width:1px;background-color:#ffffff;padding-right:20px;padding-left:20px"><!-- wp:group {"style":{"dimensions":{"minHeight":"56px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:56px"><!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Back', 'yaycheckout' ); ?>","arrow":"chevron","style":{"color":{"text":"#3858e9"},"typography":{"fontSize":"13px"}}} /-->
<?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"15px","fontWeight":"600"}}} /-->
<?php endif; ?>

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><?php if ( \YayCheckout\Blocks\IconRegistry::has( 'lock' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/lock","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"16px"}}} /-->
<?php elseif ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/icon' ) ) : ?>
<!-- wp:icon {"icon":"core/shield","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"16px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#2f8a48"},"typography":{"fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#2f8a48;margin-top:0;margin-bottom:0;font-weight:500"><?php esc_html_e( 'Secure', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"24px","right":"20px","bottom":"24px","left":"20px"}}},"layout":{"type":"constrained","contentSize":"640px"}} -->
<div class="wp-block-group" style="padding-top:24px;padding-right:20px;padding-bottom:24px;padding-left:20px"><!-- wp:yaycheckout/checkout -->
<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"padding":{"top":"0","right":"4px","bottom":"8px","left":"4px"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:4px;padding-bottom:8px;padding-left:4px"><!-- wp:heading {"style":{"typography":{"fontSize":"23px","fontWeight":"400","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:23px;font-weight:400;line-height:1.3"><?php esc_html_e( 'Checkout', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:yaycheckout/steps-progress {"variant":"text","style":{"color":{"text":"#757575"}}} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/steps {"layout":"accordion"} -->
<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Contact', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"color":"#dddddd","width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"16px","right":"20px","bottom":"16px","left":"20px"},"blockGap":"12px"}}} -->
<!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'you@example.com', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"13px","fontWeight":"600"},"border":{"radius":"2px"}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Delivery address', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"color":"#dddddd","width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"16px","right":"20px","bottom":"16px","left":"20px"},"blockGap":"12px"}}} -->
<?php if ( 'shipping' === $yaycheckout_destination ) : ?>
<!-- wp:yaycheckout/shipping {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping -->
<?php else : ?>
<!-- wp:yaycheckout/billing {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php endif; ?>
<?php if ( 'billing_only' !== $yaycheckout_destination ) : ?>

<!-- wp:yaycheckout/different-address {"sameLabel":"<?php esc_html_e( 'Billing address is the same as delivery', 'yaycheckout' ); ?>","shipLabel":"<?php esc_html_e( 'Billing address is the same as delivery', 'yaycheckout' ); ?>"} /-->
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

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"13px","fontWeight":"600"},"border":{"radius":"2px"}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Shipping options', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"color":"#dddddd","width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"16px","right":"20px","bottom":"16px","left":"20px"},"blockGap":"12px"}}} -->
<!-- wp:yaycheckout/shipping-methods {"showTitle":false,"layout":"box","style":{"yaycheckout":{"background":"#ffffff","backgroundSelected":"rgba(56,88,233,0.04)","backgroundHover":"#f0f0f0","border":"#dddddd","radio":"#757575","radioSelected":"#3858e9","radius":"4px"}}} /-->

<!-- wp:yaycheckout/order-note {"placeholder":"<?php esc_html_e( 'Notes about your order.', 'yaycheckout' ); ?>","disclosure":"checkbox","disclosureText":"<?php esc_html_e( 'Add a note to your order', 'yaycheckout' ); ?>"} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"right"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"13px","fontWeight":"600"},"border":{"radius":"2px"}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Payment', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"color":"#dddddd","width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"16px","right":"20px","bottom":"16px","left":"20px"},"blockGap":"12px"}}} -->
<!-- wp:yaycheckout/payment {"showTitle":false,"layout":"box","style":{"color":{"background":"#ffffff"},"border":{"radius":"4px"},"yaycheckout":{"backgroundSelected":"rgba(56,88,233,0.04)","backgroundHover":"#f0f0f0","border":"#dddddd","radio":"#757575","radioSelected":"#3858e9"}}} /-->
<?php if ( '' !== $yaycheckout_terms_sentence ) : ?>

<!-- wp:paragraph {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>
<!-- /wp:yaycheckout/step -->
<!-- /wp:yaycheckout/steps --></div>
<!-- /wp:group -->

<!-- wp:group {"yaycheckoutPinToBottom":"all","style":{"color":{"background":"#ffffff"},"border":{"top":{"color":"#dddddd","width":"1px","style":"solid"}}}} -->
<div class="wp-block-group has-background" style="border-top-color:#dddddd;border-top-style:solid;border-top-width:1px;background-color:#ffffff"><!-- wp:group {"style":{"spacing":{"padding":{"top":"12px","right":"20px","bottom":"12px","left":"20px"}}},"layout":{"type":"constrained","contentSize":"640px"}} -->
<div class="wp-block-group" style="padding-top:12px;padding-right:20px;padding-bottom:12px;padding-left:20px"><!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Order summary', 'yaycheckout' ); ?>","showTitle":false,"collapsible":true,"initialState":"collapsed"} -->
<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"spacing":{"padding":{"top":"4px","bottom":"4px"},"blockGap":"12px"}}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false,"quantityDisplay":"badge","badgeBackgroundColor":"#3858e9","badgeTextColor":"#ffffff"} /-->

<!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent","text":"#3858e9"},"border":{"color":"#3858e9","width":"1px","style":"solid"},"typography":{"fontSize":"13px","fontWeight":"500"}}} /-->
<!-- /wp:yaycheckout/coupon-form -->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"#dddddd","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"}},"yaycheckout":{"label":{"color":{"text":"#757575"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-details -->

<!-- wp:yaycheckout/order-summary-header -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"2px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"left","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/order-summary-toggle {"style":{"spacing":{"blockGap":"4px"},"color":{"text":"#3858e9"},"typography":{"fontSize":"12px","fontWeight":"500"}}} -->
<!-- wp:paragraph {"style":{"color":{"text":"#3858e9"},"typography":{"fontSize":"12px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#3858e9;margin-top:0;margin-bottom:0;font-size:12px;font-weight:500"><?php esc_html_e( 'Show summary', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:yaycheckout/order-summary-toggle -->

<!-- wp:yaycheckout/order-summary-total {"showLabel":false,"style":{"yaycheckout":{"value":{"typography":{"fontSize":"18px","fontWeight":"600"}}}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue', 'yaycheckout' ); ?>","yaycheckoutVisibility":{"action":"hide","rules":[{"type":"step","step":"last"}]},"style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"14px","fontWeight":"600","whiteSpace":"nowrap"},"border":{"radius":"2px"},"spacing":{"padding":{"left":"24px","right":"24px"}}}} /-->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Place Order', 'yaycheckout' ); ?>","yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":"last"}]},"style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"14px","fontWeight":"600","whiteSpace":"nowrap"},"border":{"radius":"2px"},"spacing":{"padding":{"left":"24px","right":"24px"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-header -->
<!-- /wp:yaycheckout/order-summary --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
