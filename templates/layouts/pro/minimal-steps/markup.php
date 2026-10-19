<?php
/**
 * Minimal steps: the block markup of template 06, printed by LayoutTemplateCatalogue::markup(). Its look
 * is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * lock icon's guard, the policy links and the address sections. Keep the markup as the editor saves it: a
 * hand edit that differs by one class or style makes the editor flag the block as invalid.
 *
 * One 440px column under a header outside the Checkout block (a full-width progress bar, then logo with
 * "Step N of 3"); inside Checkout, a collapsible Order Summary, then one step at a time (Address, Delivery, Payment). Continue / Back / Pay
 * and Secure checkout sit in a Group pinned to the bottom of the screen.
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

// WooCommerce's Shipping destination when the layout is created. Step 1 asks for the main address only;
// there is no Different Address block. Shipping copies into billing when shipping is main; billing alone
// when billing is main or shipping is forced to billing.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();
$yaycheckout_main        = 'shipping' === $yaycheckout_destination ? 'shipping' : 'billing';

// Step 1's address fields; $type is 'shipping' or 'billing'. No company, apartment or phone.
// City fills the row beside ZIP, two to one, and State takes a third of it when the country has states.
$yaycheckout_address_fields = static function ( string $type ): void {
    // The State column shows only while its State field isn't empty: a country without states leaves no gap.
    // One blockId per section; each matches BlockEmptiness::BLOCK_ID_PATTERN.
    $yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
    ?>
<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"12px","left":"12px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"top":"12px","left":"12px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom"} -->
<div class="wp-block-column is-vertically-aligned-bottom"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_city","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"33.33%","yaycheckoutVisibility":{"action":"show","rules":[{"type":"block","block":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","is":"not_empty"}]}} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:33.33%"><!-- wp:yaycheckout/field {"blockId":"<?php echo esc_attr( $yaycheckout_state_id ); ?>","fieldKey":"<?php echo esc_attr( $type ); ?>_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"33.33%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:33.33%"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_postcode","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'Country', 'yaycheckout' ); ?>","required":true} /-->
<?php
};
?>
<!-- wp:group {"tagName":"header","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<header class="wp-block-group"><!-- wp:yaycheckout/steps-progress {"variant":"bar","showCount":false,"trackColor":"var(--yaycheckout-design-border)"} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"40px","right":"24px","left":"24px"}}},"layout":{"type":"constrained","contentSize":"440px"}} -->
<div class="wp-block-group" style="padding-top:40px;padding-right:24px;padding-left:24px"><!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":24} /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"style":{"typography":{"fontSize":"15px","fontWeight":"600"}}} /-->
<?php endif; ?>

<!-- wp:yaycheckout/steps-progress {"textColor":"yaycheckout-muted","variant":"text","style":{"typography":{"fontSize":"12px"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:yaycheckout/checkout -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"32px","right":"24px","bottom":"24px","left":"24px"},"blockGap":"32px"}},"layout":{"type":"constrained","contentSize":"440px"}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Order summary', 'yaycheckout' ); ?>","showTitle":false,"collapsible":true,"initialState":"collapsed"} -->
<!-- wp:yaycheckout/order-summary-header -->
<!-- wp:yaycheckout/order-summary-toggle {"textColor":"yaycheckout-muted","style":{"spacing":{"blockGap":"8px"},"typography":{"fontSize":"14px"}}} -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"14px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:14px"><span class="yaycheckout-live-value" data-yaycheckout-value="cart_item_count" data-fallback="" data-prefix="" data-suffix=" <?php esc_attr_e( 'items', 'yaycheckout' ); ?>"><?php esc_html_e( 'Item count', 'yaycheckout' ); ?> <?php esc_html_e( 'items', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph -->
<!-- /wp:yaycheckout/order-summary-toggle -->

<!-- wp:yaycheckout/order-summary-total {"showLabel":false,"style":{"yaycheckout":{"value":{"typography":{"fontSize":"20px","fontWeight":"600"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-header -->

<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"spacing":{"blockGap":"10px","padding":{"top":"12px","bottom":"12px"}},"border":{"top":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"},"bottom":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}}}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false,"showThumbnails":false,"quantityDisplay":"inline"} /-->

<!-- wp:yaycheckout/order-summary-breakdown {"showSubtotal":false,"style":{"typography":{"fontSize":"13px"},"yaycheckout":{"label":{"color":{"text":"var:preset|color|yaycheckout-muted"}}}}} /-->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary -->

<!-- wp:yaycheckout/wc-hook {"hook":"woocommerce_before_checkout_form"} /-->

<!-- wp:yaycheckout/steps {"layout":"one-at-a-time","showHeadings":false} -->
<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"},"blockGap":"20px"}}} -->
<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"23px","fontWeight":"400","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:23px;font-weight:400;line-height:1.3"><?php esc_html_e( 'Where should we send it?', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'Email', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /-->

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
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Delivery', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"},"blockGap":"20px"}}} -->
<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"23px","fontWeight":"400","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:23px;font-weight:400;line-height:1.3"><?php esc_html_e( 'How fast?', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:yaycheckout/shipping-methods {"showTitle":false,"layout":"cards","style":{"yaycheckout":{"radius":"4px"}}} /-->

<!-- wp:group {"style":{"color":{"background":"#f0f0f1"},"border":{"radius":"4px"},"spacing":{"padding":{"top":"14px","right":"16px","bottom":"14px","left":"16px"},"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group has-background" style="border-radius:4px;background-color:#f0f0f1;padding-top:14px;padding-right:16px;padding-bottom:14px;padding-left:16px"><!-- wp:paragraph {"style":{"layout":{"selfStretch":"fit","flexSize":null},"typography":{"fontSize":"13px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:13px"><?php esc_html_e( 'To', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_first_name","<?php echo esc_attr( $yaycheckout_main ); ?>_last_name","<?php echo esc_attr( $yaycheckout_main ); ?>_address_1","<?php echo esc_attr( $yaycheckout_main ); ?>_city","<?php echo esc_attr( $yaycheckout_main ); ?>_state","<?php echo esc_attr( $yaycheckout_main ); ?>_postcode","<?php echo esc_attr( $yaycheckout_main ); ?>_country"],"separator":" · ","style":{"layout":{"selfStretch":"fill","flexSize":null},"typography":{"fontSize":"13px"}}} /-->

<!-- wp:yaycheckout/go-to-step {"step":1,"buttonText":"<?php esc_html_e( 'Edit', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"12px","fontWeight":"400"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/step -->

<!-- wp:yaycheckout/step {"title":"<?php esc_html_e( 'Payment', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent"},"border":{"width":"0px"},"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"},"blockGap":"20px"}}} -->
<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"23px","fontWeight":"400","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:23px;font-weight:400;line-height:1.3"><?php esc_html_e( 'How would you like to pay?', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:yaycheckout/payment {"showTitle":false,"layout":"grid","style":{"border":{"radius":"4px"}}} /-->
<?php if ( '' !== $yaycheckout_terms_sentence ) : ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:12px"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:yaycheckout/terms {"textColor":"yaycheckout-muted","style":{"typography":{"fontSize":"12px"}}} /-->
<!-- /wp:yaycheckout/step -->
<!-- /wp:yaycheckout/steps --></div>
<!-- /wp:group -->

<!-- wp:group {"backgroundColor":"yaycheckout-background","yaycheckoutPinToBottom":"all"} -->
<div class="wp-block-group has-background has-yaycheckout-background-background-color"><!-- wp:group {"style":{"spacing":{"padding":{"top":"16px","right":"24px","bottom":"24px","left":"24px"},"blockGap":"14px"}},"layout":{"type":"constrained","contentSize":"440px"}} -->
<div class="wp-block-group" style="padding-top:16px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:group {"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":1}]},"style":{"spacing":{"blockGap":"14px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue to delivery', 'yaycheckout' ); ?>","style":{"dimensions":{"width":"var:preset|dimension|100"},"typography":{"textAlign":"center","fontSize":"15px"}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":2}]},"style":{"spacing":{"blockGap":"14px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/step-button {"buttonText":"<?php esc_html_e( 'Continue to payment', 'yaycheckout' ); ?>","style":{"dimensions":{"width":"var:preset|dimension|100"},"typography":{"textAlign":"center","fontSize":"15px"}}} /-->

<!-- wp:yaycheckout/back-button {"backgroundColor":"yaycheckout-background","textColor":"yaycheckout-text","borderColor":"yaycheckout-border","buttonText":"<?php esc_html_e( 'Back to address', 'yaycheckout' ); ?>","firstStep":"hide","arrow":"chevron","style":{"dimensions":{"width":"var:preset|dimension|100"},"border":{"width":"1px","style":"solid","radius":"2px"},"typography":{"textAlign":"center","fontSize":"14px","fontWeight":"500"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"18px","right":"18px"}}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"yaycheckoutVisibility":{"action":"show","rules":[{"type":"step","step":"last"}]},"style":{"spacing":{"blockGap":"14px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Pay', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022 \u0022 data-suffix=\u0022\u0022\u003e <?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","style":{"typography":{"fontSize":"15px","textAlign":"center"},"dimensions":{"width":"var:preset|dimension|100"}}} /-->

<!-- wp:yaycheckout/back-button {"backgroundColor":"yaycheckout-background","textColor":"yaycheckout-text","borderColor":"yaycheckout-border","buttonText":"<?php esc_html_e( 'Back to delivery', 'yaycheckout' ); ?>","firstStep":"hide","arrow":"chevron","style":{"dimensions":{"width":"var:preset|dimension|100"},"border":{"width":"1px","style":"solid","radius":"2px"},"typography":{"textAlign":"center","fontSize":"14px","fontWeight":"500"},"spacing":{"padding":{"top":"10px","bottom":"10px","left":"18px","right":"18px"}}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px","padding":{"top":"4px"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="padding-top:4px"><?php if ( \YayCheckout\Blocks\IconRegistry::has( 'lock' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/lock","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"13px"}}} /-->
<?php elseif ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/icon' ) ) : ?>
<!-- wp:icon {"icon":"core/shield","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"13px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:12px"><?php esc_html_e( 'Secure checkout', 'yaycheckout' ); ?> ·</p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/return-to-cart {"textColor":"yaycheckout-muted","buttonText":"<?php esc_html_e( 'Return to cart', 'yaycheckout' ); ?>","arrow":"none","style":{"typography":{"fontSize":"12px","textDecoration":"none"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/checkout -->
