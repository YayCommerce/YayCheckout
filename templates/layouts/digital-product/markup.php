<?php
/**
 * Digital product: the block markup of template 12, printed by LayoutTemplateCatalogue::markup().
 * Its look is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the lock icon's guard, the policy
 * links and the legal sentence. Keep the markup as the editor saves it: a hand edit that
 * differs by one class or style makes the editor flag the block as invalid.
 *
 * A centred white card, at most 920px wide, on a dark page. The left column is the product
 * summary (Return to cart, Items, then Coupon Form, Breakdown and Total at its foot); the
 * right is the form (Secure badge, Express Pay, Email, Payment, Billing country and
 * postcode, a VAT Extra Field, Place Order and the terms sentence). No shipping, shipping
 * methods or Different Address: the same markup for every Shipping destination. The mock's
 * license picker, product hero and drawn card form have no block yet and are left out.
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

$yaycheckout_digital_note = esc_html__( 'Digital goods are non-refundable after download.', 'yaycheckout' );
$yaycheckout_legal        = '' !== $yaycheckout_terms_sentence
    ? $yaycheckout_terms_sentence . ' ' . $yaycheckout_digital_note
    : $yaycheckout_digital_note;
?>
<!-- wp:group {"style":{"dimensions":{"minHeight":"100vh"},"spacing":{"padding":{"top":"40px","right":"24px","bottom":"40px","left":"24px"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:100vh;padding-top:40px;padding-right:24px;padding-bottom:40px;padding-left:24px"><!-- wp:group {"layout":{"type":"constrained","contentSize":"920px"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"border":{"radius":"8px"},"color":{"background":"#ffffff","text":"#1e1e1e"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","right":"0","bottom":"0","left":"0"}},"shadow":"0 9px 30px #00000033"},"layout":{"type":"default"}} -->
<div class="wp-block-group has-text-color has-background" style="border-radius:8px;color:#1e1e1e;background-color:#ffffff;margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;box-shadow:0 9px 30px #00000033"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"top":"0px","left":"0px"}}}} -->
<div class="wp-block-columns is-stacked-on-mobile"><!-- wp:column {"width":"42%","style":{"border":{"radius":{"topLeft":"8px","bottomLeft":"8px"}},"color":{"background":"#f0f0f1","text":"#1e1e1e"},"spacing":{"padding":{"top":"40px","right":"36px","bottom":"40px","left":"36px"},"blockGap":"28px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-column has-text-color has-background" style="border-top-left-radius:8px;border-bottom-left-radius:8px;color:#1e1e1e;background-color:#f0f0f1;padding-top:40px;padding-right:36px;padding-bottom:40px;padding-left:36px;flex-basis:42%"><!-- wp:yaycheckout/return-to-cart {"buttonText":"<?php esc_html_e( 'Back to Storefront', 'yaycheckout' ); ?>","arrow":"chevron","style":{"color":{"text":"#757575"},"typography":{"fontSize":"13px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Your order', 'yaycheckout' ); ?>","style":{"spacing":{"blockGap":"28px"},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"spacing":{"blockGap":"28px"}}} -->
<!-- wp:yaycheckout/order-summary-items {"showLabels":false,"showThumbnails":true,"showShortDescription":true,"itemData":"list","quantityDisplay":"inline","style":{"typography":{"fontSize":"14px"}}} /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"auto"},"blockGap":"8px"}}} -->
<div class="wp-block-group" style="margin-top:auto"><!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"13px"}}} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","style":{"color":{"background":"transparent","text":"#3858e9"},"border":{"color":"#3858e9","width":"1px","style":"solid","radius":"2px"},"typography":{"fontSize":"12px","fontWeight":"500"},"spacing":{"padding":{"top":"0","bottom":"0","left":"12px","right":"12px"}}}} /-->
<!-- /wp:yaycheckout/coupon-form -->

<!-- wp:yaycheckout/order-summary-breakdown {"style":{"border":{"top":{"color":"#dddddd","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"12px"}},"typography":{"fontSize":"13px"},"yaycheckout":{"label":{"color":{"text":"#757575"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"label":"<?php esc_html_e( 'Total due today', 'yaycheckout' ); ?>","style":{"border":{"top":{"color":"#dddddd","width":"1px","style":"solid"}},"spacing":{"padding":{"top":"8px"}},"yaycheckout":{"label":{"typography":{"fontSize":"14px","fontWeight":"600"},"color":{"text":"#1e1e1e"}},"value":{"typography":{"fontSize":"20px","fontWeight":"600"},"color":{"text":"#1e1e1e"}}}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"58%","style":{"border":{"radius":{"topRight":"8px","bottomRight":"8px"}},"color":{"background":"#ffffff"},"spacing":{"padding":{"top":"40px","right":"36px","bottom":"40px","left":"36px"},"blockGap":"28px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<div class="wp-block-column has-background" style="border-top-right-radius:8px;border-bottom-right-radius:8px;background-color:#ffffff;padding-top:40px;padding-right:36px;padding-bottom:40px;padding-left:36px;flex-basis:58%"><!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"18px","fontWeight":"600","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:18px;font-weight:600;line-height:1.3"><?php esc_html_e( 'Checkout', 'yaycheckout' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><?php if ( \YayCheckout\Blocks\IconRegistry::has( 'lock' ) ) : ?>
<!-- wp:icon {"icon":"yaycheckout/lock","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"14px"}}} /-->
<?php elseif ( WP_Block_Type_Registry::get_instance()->is_registered( 'core/icon' ) ) : ?>
<!-- wp:icon {"icon":"core/shield","style":{"color":{"text":"#2f8a48"},"dimensions":{"width":"14px"}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"color":{"text":"#2f8a48"},"typography":{"fontSize":"12px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-color" style="color:#2f8a48;margin-top:0;margin-bottom:0;font-size:12px;font-weight:500"><?php esc_html_e( 'Secure', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"10px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/express-pay {"blockId":"exprspay"} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"yaycheckoutVisibility":{"action":"hide","rules":[{"type":"block","block":"exprspay","is":"empty"}]}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"color":{"background":"#dddddd"},"spacing":{"margin":{"top":"0","bottom":"0"}},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<hr class="wp-block-separator has-text-color has-alpha-channel-opacity has-background" style="margin-top:0;margin-bottom:0;background-color:#dddddd;color:#dddddd"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px"><?php esc_html_e( 'or pay with card', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:separator {"style":{"color":{"background":"#dddddd"},"spacing":{"margin":{"top":"0","bottom":"0"}},"layout":{"selfStretch":"fill","flexSize":null}}} -->
<hr class="wp-block-separator has-text-color has-alpha-channel-opacity has-background" style="margin-top:0;margin-bottom:0;background-color:#dddddd;color:#dddddd"/>
<!-- /wp:separator --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"6px"}}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"13px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:13px;font-weight:500"><?php esc_html_e( 'Email address', 'yaycheckout' ); ?> <span style="color:#cc1818">*</span></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/email {"label":"<?php esc_html_e( 'Email address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'you@example.com', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'Your download link and license key will be sent here.', 'yaycheckout' ); ?>","requiredMark":"*"} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/payment {"label":"<?php esc_html_e( 'Card information', 'yaycheckout' ); ?>","headingLevel":3,"layout":"joined","style":{"color":{"background":"#ffffff","text":"#1e1e1e"},"yaycheckout":{"title":{"typography":{"fontSize":"13px","fontWeight":"500"}},"backgroundSelected":"rgba(56,88,233,0.04)","backgroundHover":"#f0f0f0","border":"#757575","radio":"#757575","radioSelected":"#3858e9","radius":"4px"}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"13px","fontWeight":"500"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:13px;font-weight:500"><?php esc_html_e( 'Billing country', 'yaycheckout' ); ?> <span style="color:#757575;font-weight:400"><?php esc_html_e( '(for tax)', 'yaycheckout' ); ?></span></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/billing {"showTitle":false,"style":{"spacing":{"blockGap":"0px"}}} -->
<!-- wp:yaycheckout/field {"fieldKey":"billing_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"billing_postcode","label":"<?php esc_html_e( 'ZIP / Postal code', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'ZIP / Postal code', 'yaycheckout' ); ?>","required":true} /-->
<!-- /wp:yaycheckout/billing -->

<!-- wp:yaycheckout/extra-field {"fieldKey":"vat_number","label":"<?php esc_html_e( 'VAT number', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'VAT number, e.g. DE123456789', 'yaycheckout' ); ?>","disclosure":"link","disclosureText":"<?php esc_html_e( '+ Add VAT number (business)', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px","margin":{"top":"16px"}}}} -->
<div class="wp-block-group" style="margin-top:16px"><!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Pay', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022 \u0022 data-suffix=\u0022\u0022\u003e <?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","style":{"color":{"background":"#3858e9","text":"#ffffff"},"typography":{"fontSize":"15px","fontWeight":"600","textAlign":"center"},"border":{"radius":"4px"},"spacing":{"padding":{"top":"12px","bottom":"12px"}},"dimensions":{"width":"var:preset|dimension|100"}}} /-->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px","lineHeight":"1.5"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center has-text-color" style="color:#757575;margin-top:0;margin-bottom:0;font-size:12px;line-height:1.5"><?php echo wp_kses_post( $yaycheckout_legal ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/terms {"style":{"color":{"text":"#757575"},"typography":{"fontSize":"12px","textAlign":"center"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
