<?php
/**
 * Editorial fashion: the block markup of template 08, printed by LayoutTemplateCatalogue::markup().
 * Its look is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * cart link, the lock icon's guard, the policy links and the address sections. Keep the markup as the
 * editor saves it: a hand edit that differs by one class or style makes the editor flag the
 * block as invalid.
 *
 * Left column is the 560px terracotta bag panel, whose sticky content spans the screen's height with the
 * Coupon Form at its foot; right column is the form on the page fill. Delivery is the
 * main address, chosen from WooCommerce's Shipping destination when the layout is created: Shipping
 * when shipping is main, Billing when billing is main or shipping is forced to billing. Different
 * Address follows Payment, then the other section; forced-to-billing has only Delivery. Both sections
 * print the same fields from $yaycheckout_address_fields (no company; State hidden when empty). Order
 * Summary holds the tree TEMPLATE in packages/blocks/src/order-summary/edit.tsx gives a new one, plus
 * the Coupon Form at the foot of the bag.
 */

defined( 'ABSPATH' ) || exit;

// The block markup prints as the editor saves it, so a PHP tag inside a branch or a function can't be indented.
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

// Each link prints only when its page exists, and the sentence reads correctly without it.
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

// WooCommerce's Shipping destination when the layout is created: Delivery is the main address;
// Different Address and the other section follow Payment. Forced-to-billing has only Delivery.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();

// Delivery and the secondary address share these fields; $type is 'shipping' or 'billing'.
$yaycheckout_address_fields = static function ( string $type ): void {
    // The State column shows only while its State field isn't empty: a country without states leaves no gap.
    // One blockId per section, since a layout holds both; each matches BlockEmptiness::BLOCK_ID_PATTERN.
    $yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
    ?>
<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"12px","left":"20px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_2","label":"<?php esc_html_e( 'Apartment, suite, etc.', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Apartment, suite, etc.', 'yaycheckout' ); ?>","disclosure":"link","disclosureText":"<?php esc_html_e( '+ Add apartment, suite, etc.', 'yaycheckout' ); ?>"} /-->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"12px","left":"20px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"12px","left":"20px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"yaycheckoutVisibility":{"action":"show","rules":[{"type":"block","block":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","is":"not_empty"}]}} -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"blockId":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","fieldKey":"<?php echo esc_attr( $type ); ?>_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Phone (optional)', 'yaycheckout' ); ?>"} /-->
<?php
};

$yaycheckout_address_heading = 'shipping' === $yaycheckout_destination
    ? __( 'Shipping address', 'yaycheckout' )
    : __( 'Billing address', 'yaycheckout' );
?>
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"0px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"480px","backgroundColor":"yaycheckout-primary","style":{"color":{"text":"#ffffff"},"spacing":{"padding":{"top":"32px","right":"40px","bottom":"48px","left":"40px"}}}} -->
<div class="wp-block-column has-yaycheckout-primary-background-color has-text-color has-background" style="color:#ffffff;padding-top:32px;padding-right:40px;padding-bottom:48px;padding-left:40px;flex-basis:480px"><!-- wp:group {"style":{"position":{"type":"sticky","top":"0px"},"spacing":{"blockGap":"32px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","verticalAlignment":"space-between"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"32px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"elements":{"link":{"color":{"text":"#ffffff"}}},"color":{"text":"#ffffff"},"typography":{"fontSize":"22px","fontStyle":"italic","fontFamily":"Georgia, \"Times New Roman\", serif","letterSpacing":"-0.01em"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"11px","letterSpacing":"0.16em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#ffffff;margin-top:0;margin-bottom:0;font-size:11px;letter-spacing:0.16em;text-transform:uppercase"><?php esc_html_e( 'SS 26', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"11px","letterSpacing":"0.16em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#ffffff;margin-top:0;margin-bottom:0;font-size:11px;letter-spacing:0.16em;text-transform:uppercase;opacity:0.85"><?php esc_html_e( 'Your bag', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

 <!-- wp:heading {"style":{"typography":{"fontSize":"56px","fontWeight":"400","lineHeight":"0.95","fontFamily":"Georgia, \u0022Times New Roman\u0022, serif","letterSpacing":"-0.02em"},"color":{"text":"#ffffff"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading has-text-color" style="color:#ffffff;margin-top:0;margin-bottom:0;font-family:Georgia, &quot;Times New Roman&quot;, serif;font-size:56px;font-weight:400;letter-spacing:-0.02em;line-height:0.95"><span data-yaycheckout-value="cart_item_count" data-fallback="" data-prefix="" data-suffix="" class="yaycheckout-live-value">Item count</span> <?php esc_html_e( 'pieces', 'yaycheckout' ); ?><br><?php esc_html_e( 'one look.', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->
</div>
<!-- /wp:group -->

<!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Your bag', 'yaycheckout' ); ?>","style":{"color":{"text":"#ffffff"},"spacing":{"blockGap":"14px"}}} -->
<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"spacing":{"blockGap":"16px"}}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false,"thumbnailRatio":"portrait","thumbnailSize":72,"thumbnailRadius":0,"itemData":"inline","quantityDisplay":"stepper","stepperStyle":"round","stepperBorderColor":"#ffffff99","style":{"color":{"text":"#ffffff"},"border":{"top":{"color":"#ffffff59","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"14px","bottom":"14px"}},"typography":{"fontSize":"15px","fontFamily":"Georgia, \"Times New Roman\", serif"}}} /-->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"#ffffff59","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"16px"}},"typography":{"fontSize":"14px"},"color":{"text":"#ffffff"},"yaycheckout":{"label":{"color":{"text":"#ffffffd9"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"style":{"border":{"top":{"color":"#ffffff59","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"},"margin":{"top":"4px"}},"color":{"text":"#ffffff"},"yaycheckout":{"label":{"typography":{"fontSize":"11px","letterSpacing":"0.16em","textTransform":"uppercase"},"color":{"text":"#ffffff"}},"value":{"typography":{"fontSize":"36px","fontWeight":"400","fontFamily":"Georgia, \"Times New Roman\", serif","letterSpacing":"-0.02em","lineHeight":"1"},"color":{"text":"#ffffff"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>","style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"13px"}}} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","textColor":"yaycheckout-primary","style":{"color":{"background":"#ffffff"},"border":{"color":"#ffffff","width":"1px","style":"solid","radius":"0px"},"typography":{"fontSize":"13px","fontWeight":"500","textTransform":"none"},"spacing":{"padding":{"top":"0px","right":"18px","bottom":"0px","left":"18px"}}}} /-->
<!-- /wp:yaycheckout/coupon-form --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:group {"style":{"spacing":{"padding":{"top":"40px","right":"48px","bottom":"96px","left":"48px"},"blockGap":"44px"}},"layout":{"type":"constrained","contentSize":"620px"}} -->
<div class="wp-block-group" style="padding-top:40px;padding-right:48px;padding-bottom:96px;padding-left:48px"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/return-to-cart {"textColor":"yaycheckout-muted","buttonText":"<?php esc_html_e( 'Back to bag', 'yaycheckout' ); ?>","arrow":"chevron","style":{"typography":{"fontSize":"11px","letterSpacing":"0.16em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><?php if ( \YayCheckout\Blocks\IconRegistry::has( 'lock' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/lock","style":{"dimensions":{"width":"12px"}}} /-->
<?php elseif ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/icon' ) ) : ?>
<!-- wp:icon {"icon":"core/shield","style":{"dimensions":{"width":"12px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"11px","letterSpacing":"0.16em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:11px;letter-spacing:0.16em;text-transform:uppercase"><?php esc_html_e( 'Secure checkout', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_before_checkout_form"} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"fontSize":"28px","fontWeight":"400","lineHeight":"1.1","fontFamily":"Georgia, \"Times New Roman\", serif","letterSpacing":"-0.01em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:400;font-family:Georgia, &quot;Times New Roman&quot;, serif;letter-spacing:-0.01em;line-height:1.1"><span style="color:var(--wp--preset--color--yaycheckout-primary);font-style:italic;margin-right:10px">01</span><?php esc_html_e( 'Contact information', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:yaycheckout/login {"textColor":"yaycheckout-muted","text":"<?php esc_html_e( 'Have an account? Log in', 'yaycheckout' ); ?>","linkText":"<?php esc_html_e( 'Log in', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"13px"}}} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'Email address', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"fontSize":"28px","fontWeight":"400","lineHeight":"1.1","fontFamily":"Georgia, \"Times New Roman\", serif","letterSpacing":"-0.01em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:400;font-family:Georgia, &quot;Times New Roman&quot;, serif;letter-spacing:-0.01em;line-height:1.1"><span style="color:var(--wp--preset--color--yaycheckout-primary);font-style:italic;margin-right:10px">02</span><?php echo esc_html( $yaycheckout_address_heading ); ?></h2>
<!-- /wp:heading -->

<?php if ( 'shipping' === $yaycheckout_destination ) : ?>
<!-- wp:yaycheckout/shipping {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'shipping' ); ?>
<!-- /wp:yaycheckout/shipping -->
<?php else : ?>
<!-- wp:yaycheckout/billing {"showTitle":false} -->
<?php $yaycheckout_address_fields( 'billing' ); ?>
<!-- /wp:yaycheckout/billing -->
<?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"fontSize":"28px","fontWeight":"400","lineHeight":"1.1","fontFamily":"Georgia, \"Times New Roman\", serif","letterSpacing":"-0.01em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:400;font-family:Georgia, &quot;Times New Roman&quot;, serif;letter-spacing:-0.01em;line-height:1.1"><span style="color:var(--wp--preset--color--yaycheckout-primary);font-style:italic;margin-right:10px">03</span><?php esc_html_e( 'Shipping options', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:yaycheckout/shipping-methods {"showTitle":false,"layout":"lines","style":{"yaycheckout":{"border":"var(--yaycheckout-design-border)"}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"fontSize":"28px","fontWeight":"400","lineHeight":"1.1","fontFamily":"Georgia, \"Times New Roman\", serif","letterSpacing":"-0.01em"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:28px;font-weight:400;font-family:Georgia, &quot;Times New Roman&quot;, serif;letter-spacing:-0.01em;line-height:1.1"><span style="color:var(--wp--preset--color--yaycheckout-primary);font-style:italic;margin-right:10px">04</span><?php esc_html_e( 'Payment options', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:yaycheckout/payment {"showTitle":false,"layout":"lines","style":{"yaycheckout":{"border":"var(--yaycheckout-design-border)"}}} /-->

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
<?php endif; ?></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/order-note {"placeholder":"<?php esc_html_e( 'Notes about your order.', 'yaycheckout' ); ?>","disclosure":"checkbox","disclosureText":"<?php esc_html_e( 'Add a note to your order', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"13px"}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"16px"}}} -->
<div class="wp-block-group"><?php if ( '' !== $yaycheckout_terms_sentence ) : ?>
<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","lineHeight":"1.5"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:12px;line-height:1.5"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:yaycheckout/terms {"textColor":"yaycheckout-muted","style":{"typography":{"fontSize":"12px"}}} /-->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Place Order', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022\u0022 data-suffix=\u0022\u0022\u003e<?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","backgroundColor":"yaycheckout-text","style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"13px","letterSpacing":"0.16em"},"dimensions":{"width":"var:preset|dimension|100"},"spacing":{"padding":{"top":"18px","right":"24px","bottom":"18px","left":"24px"}}},"spread":true} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"20px"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"11px","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase"><?php esc_html_e( 'Free returns · 30 days', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"11px","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:11px;letter-spacing:0.12em;text-transform:uppercase"><?php esc_html_e( 'Ships in 24h', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
