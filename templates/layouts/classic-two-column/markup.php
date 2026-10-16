<?php
/**
 * Classic two-column (LayoutTemplateCatalogue::DEFAULT_SLUG): the block markup of template 01, which a
 * Default layout also starts with, printed by LayoutTemplateCatalogue::markup(). Its look is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * cart link, the lock icon's guard, the policy links and the address sections. Keep the markup as the editor saves it: a
 * hand edit that differs by one class or style makes the editor flag the block as invalid.
 *
 * The address order follows WooCommerce's Shipping destination when the layout is created: Shipping first,
 * as in the mock, when shipping is main; Billing first when billing is main; Billing alone when shipping is
 * forced to billing. Both sections print the same fields, from $yaycheckout_address_fields, whose State column
 * shows only while the State field isn't empty.
 * Billing and Shipping hold the structure packages/blocks/src/shared/address-template.ts gives a new
 * section; change both together. Their keys come from packages/blocks/src/shared/address-fields.ts.
 * Order Summary holds the tree TEMPLATE in packages/blocks/src/order-summary/edit.tsx gives a new one,
 * plus the Coupon Form and the item count.
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

// WooCommerce's Shipping destination when the layout is created: the main address comes first and the
// other follows Different Address; with shipping forced to billing there is only Billing.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();

// Shipping and Billing hold the same fields; $type is 'shipping' or 'billing'.
$yaycheckout_address_fields = static function ( string $type ): void {
    // The State column shows only while its State field isn't empty: a country without states leaves no gap.
    // One blockId per section, since a layout holds both; each matches BlockEmptiness::BLOCK_ID_PATTERN.
    $yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
    ?>
<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"12px","left":"12px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_company","label":"<?php esc_html_e( 'Company', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_2","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Apartment, suite, unit, etc. (optional)', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"top":"12px","left":"12px"}}}} -->
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

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'In case we need to contact you about your order', 'yaycheckout' ); ?>"} /-->
<?php
};
?>
<!-- wp:group {"tagName":"header","style":{"color":{"background":"#ffffff"},"border":{"bottom":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<header class="wp-block-group has-background" style="border-bottom-color:var(--wp--preset--color--yaycheckout-border);border-bottom-style:solid;border-bottom-width:1px;background-color:#ffffff;padding-right:24px;padding-left:24px"><!-- wp:group {"style":{"dimensions":{"minHeight":"64px"},"spacing":{"padding":{"top":"12px","bottom":"12px"},"blockGap":{"top":"8px","left":"16px"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:64px;padding-top:12px;padding-bottom:12px"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0"><a style="color:inherit;text-decoration:none" href="http://yaydevs.local/?page_id=8">Cart</a>   <span style="color:#cccccc">›</span>   <span style="font-weight:500">Checkout</span>   <span style="color:#cccccc">›</span>   Order received</p>
<!-- /wp:paragraph -->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><?php if ( \YayCheckout\Blocks\IconRegistry::has( 'lock' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/lock","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"16px"}}} /-->
<?php elseif ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/icon' ) ) : ?>
<!-- wp:icon {"icon":"core/shield","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"16px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#2f8a48"},"typography":{"fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#2f8a48;margin-top:0;margin-bottom:0;font-weight:500"><?php esc_html_e( 'Secure checkout', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"40px","right":"24px","bottom":"80px","left":"24px"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div class="wp-block-group" style="padding-top:40px;padding-right:24px;padding-bottom:80px;padding-left:24px"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"40px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"60%","style":{"spacing":{"blockGap":"40px"}}} -->
<div class="wp-block-column" style="flex-basis:60%"><!-- wp:yaycheckout/express-pay /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"fontSize":"20px","fontWeight":"400"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:20px;font-weight:400"><?php esc_html_e( 'Contact information', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:yaycheckout/login {"text":"<?php esc_html_e( 'Have an account? Log in', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Log in', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'you@example.com', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<?php if ( 'shipping' === $yaycheckout_destination ) : ?>
<!-- wp:yaycheckout/shipping {"title":"<?php esc_html_e( 'Shipping address', 'yaycheckout' ); ?>"} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping -->

<!-- wp:yaycheckout/different-address {"sameLabel":"<?php esc_html_e( 'Billing address is the same as shipping', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/billing {"borderColor":"yaycheckout-border","title":"<?php esc_html_e( 'Billing address', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"20px","right":"20px","bottom":"20px","left":"20px"}}}} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php else : ?>
<!-- wp:yaycheckout/billing {"title":"<?php esc_html_e( 'Billing address', 'yaycheckout' ); ?>"} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php if ( 'billing' === $yaycheckout_destination ) : ?>

<!-- wp:yaycheckout/different-address {"shipLabel":"<?php esc_html_e( 'Shipping address is the same as billing', 'yaycheckout' ); ?>"} /-->

<!-- wp:yaycheckout/shipping {"borderColor":"yaycheckout-border","title":"<?php esc_html_e( 'Shipping address', 'yaycheckout' ); ?>","style":{"color":{"background":"#ffffff"},"border":{"width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"20px","right":"20px","bottom":"20px","left":"20px"}}}} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping -->
<?php endif; ?>
<?php endif; ?>

<!-- wp:yaycheckout/shipping-methods {"label":"<?php esc_html_e( 'Shipping options', 'yaycheckout' ); ?>","layout":"box","style":{"yaycheckout":{"background":"#ffffff","radius":"4px"}}} /-->

<!-- wp:yaycheckout/payment {"label":"<?php esc_html_e( 'Payment options', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'All transactions are secure and encrypted.', 'yaycheckout' ); ?>","layout":"box","style":{"color":{"background":"#ffffff"},"border":{"radius":"4px"}}} /-->

<!-- wp:yaycheckout/order-note {"placeholder":"<?php esc_html_e( 'Notes about your order.', 'yaycheckout' ); ?>","disclosure":"checkbox","disclosureText":"<?php esc_html_e( 'Add a note to your order', 'yaycheckout' ); ?>"} /-->

<!-- wp:group {"style":{"border":{"top":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"24px"},"blockGap":"16px"}}} -->
<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--yaycheckout-border);border-top-style:solid;border-top-width:1px;padding-top:24px"><?php if ( '' !== $yaycheckout_terms_sentence ) : ?>
<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px"}}} -->
<p style="font-size:12px"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:yaycheckout/terms {"textColor":"yaycheckout-muted","style":{"typography":{"fontSize":"12px"}}} /-->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Place Order', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022 · \u0022 data-suffix=\u0022\u0022\u003e · <?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","style":{"typography":{"fontSize":"15px","textAlign":"center"},"dimensions":{"width":"var:preset|dimension|100"}}} /-->

<!-- wp:yaycheckout/return-to-cart {"textColor":"yaycheckout-muted","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:group {"style":{"position":{"type":"sticky","top":"24px"}}} -->
<div class="wp-block-group"><!-- wp:group {"borderColor":"yaycheckout-border","style":{"color":{"background":"#ffffff"},"border":{"width":"1px","style":"solid","radius":"4px"},"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}}} -->
<div class="wp-block-group has-border-color has-background has-yaycheckout-border-border-color" style="border-style:solid;border-width:1px;border-radius:4px;background-color:#ffffff;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Order summary', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/order-summary-header -->
<!-- wp:yaycheckout/order-summary-toggle -->
<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"14px","fontWeight":"600"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h3 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:14px;font-weight:600"><?php esc_html_e( 'Order summary', 'yaycheckout' ); ?></h3>
<!-- /wp:heading -->
<!-- /wp:yaycheckout/order-summary-toggle -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"13px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:13px"><span class="yaycheckout-live-value" data-yaycheckout-value="cart_item_count" data-fallback="" data-prefix="" data-suffix=" <?php esc_attr_e( 'items', 'yaycheckout' ); ?>"><?php esc_html_e( 'Item count', 'yaycheckout' ); ?> <?php esc_html_e( 'items', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph -->
<!-- /wp:yaycheckout/order-summary-header -->

<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false,"thumbnailSize":60,"thumbnailRadius":4,"quantityDisplay":"hidden","showBadge":true,"badgeBackgroundColor":"var(--yaycheckout-design-primary)"} /-->

<!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","className":"is-style-outline","style":{"typography":{"fontSize":"13px","fontWeight":"500"}}} /-->
<!-- /wp:yaycheckout/coupon-form -->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"16px"}},"typography":{"fontSize":"14px"},"yaycheckout":{"label":{"color":{"text":"var:preset|color|yaycheckout-muted"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"showCurrencyCode":true,"style":{"border":{"top":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"16px"}},"yaycheckout":{"label":{"typography":{"fontSize":"16px","fontWeight":"600"}},"value":{"typography":{"fontSize":"20px","fontWeight":"600"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary --></div>
<!-- /wp:group --><?php if ( $yaycheckout_policy_links ) : ?>

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"16px"}},"typography":{"textAlign":"center","fontSize":"12px"}}} -->
<p class="has-text-align-center" style="margin-top:16px;font-size:12px"><?php echo wp_kses_post( implode( ' &middot; ', $yaycheckout_policy_links ) ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
