<?php
/**
 * Live receipt: the block markup of template 07, printed by LayoutTemplateCatalogue::markup(). Its look
 * is design.json beside it.
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice, the
 * lock icon's guard, the policy links and the address sections. Keep the markup as the editor saves it: a
 * hand edit that differs by one class or style makes the editor flag the block as invalid.
 *
 * Two columns under a white header: a one-page form with numbered sections (Contact, address,
 * Shipping options, Payment, Order Note), and a sticky monospace receipt whose CONTACT and SHIP TO /
 * BILL TO lines are Field Value blocks that mirror what the shopper types.
 *
 * The address order follows WooCommerce's Shipping destination when the layout is created: one main
 * address only (no Different Address), as in the mock. Shipping when shipping is main; Billing when
 * billing is main or shipping is forced to billing. Fields come from $yaycheckout_address_fields:
 * first/last, address, apartment behind a link, city / state / ZIP, country, optional phone.
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

// WooCommerce's Shipping destination when the layout is created. One address section only; when shipping
// is main, CheckoutDifferentAddress copies it into billing because there is no Billing block.
$yaycheckout_destination = \YayCheckout\Renderer\CheckoutDifferentAddress::destination();
$yaycheckout_main        = 'shipping' === $yaycheckout_destination ? 'shipping' : 'billing';

// Main address fields; $type is 'shipping' or 'billing'. Order matches the mock: names, street, apartment
// link, city / state / ZIP, country, optional phone. No company.
$yaycheckout_address_fields = static function ( string $type ): void {
    // One blockId per section; each matches BlockEmptiness::BLOCK_ID_PATTERN (8 chars).
    $yaycheckout_state_id = 'billing' === $type ? 'bilstate' : 'shpstate';
    $yaycheckout_apt_id   = 'billing' === $type ? 'biladdr2' : 'shpaddr2';
    ?>
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"12px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_first_name","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_last_name","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_address_1","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"blockId":"<?php echo esc_attr( $yaycheckout_apt_id ); ?>","fieldKey":"<?php echo esc_attr( $type ); ?>_address_2","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","disclosure":"link","disclosureText":"<?php esc_html_e( '+ Add apartment, suite, etc.', 'yaycheckout' ); ?>"} /-->

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

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"<?php echo esc_attr( $type ); ?>_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>"} /-->
<?php
};

$yaycheckout_apt_id     = 'shipping' === $yaycheckout_main ? 'shpaddr2' : 'biladdr2';
$yaycheckout_ship_label = 'shipping' === $yaycheckout_main ? __( 'SHIP TO', 'yaycheckout' ) : __( 'BILL TO', 'yaycheckout' );
?>
<!-- wp:group {"tagName":"header","style":{"color":{"background":"#ffffff"},"border":{"bottom":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"}},"spacing":{"padding":{"left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<header class="wp-block-group has-background" style="border-bottom-color:var(--wp--preset--color--yaycheckout-border);border-bottom-style:solid;border-bottom-width:1px;background-color:#ffffff;padding-right:24px;padding-left:24px"><!-- wp:group {"style":{"dimensions":{"minHeight":"64px"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="min-height:64px"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":28} /-->
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
<p class="has-text-color" style="color:#2f8a48;margin-top:0;margin-bottom:0;font-weight:500"><?php esc_html_e( 'Secure checkout', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></header>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"40px","right":"24px","bottom":"80px","left":"24px"}}},"layout":{"type":"constrained","contentSize":"1120px"}} -->
<div class="wp-block-group" style="padding-top:40px;padding-right:24px;padding-bottom:80px;padding-left:24px"><!-- wp:yaycheckout/checkout -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"48px"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"style":{"spacing":{"blockGap":"36px"}}} -->
<div class="wp-block-column"><!-- wp:group {"style":{"spacing":{"blockGap":"6px"}}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"23px","fontWeight":"400","lineHeight":"1.3"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h1 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:23px;font-weight:400;line-height:1.3"><?php esc_html_e( 'Fill in the blanks. Your receipt writes itself.', 'yaycheckout' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Everything you type shows up on the receipt to the right, so you can check it before you pay.', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"10px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"backgroundColor":"yaycheckout-primary","align":"center","style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"11px","fontWeight":"600","lineHeight":"22px"},"border":{"radius":"9999px"},"dimensions":{"minHeight":"22px","width":"22px"},"layout":{"selfStretch":"fixed","flexSize":"22px"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<p class="has-text-align-center has-text-color has-background has-yaycheckout-primary-background-color" style="color:#ffffff;border-radius:9999px;margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;width:22px;min-height:22px;font-size:11px;font-weight:600;line-height:22px;box-sizing:border-box;flex-shrink:0">1</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontSize":"16px","fontWeight":"600","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:16px;font-weight:600;line-height:1.4"><?php esc_html_e( 'Contact information', 'yaycheckout' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/email {"placeholder":"<?php esc_html_e( 'Email address', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'You are currently checking out as a guest.', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"10px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"backgroundColor":"yaycheckout-primary","align":"center","style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"11px","fontWeight":"600","lineHeight":"22px"},"border":{"radius":"9999px"},"dimensions":{"minHeight":"22px","width":"22px"},"layout":{"selfStretch":"fixed","flexSize":"22px"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<p class="has-text-align-center has-text-color has-background has-yaycheckout-primary-background-color" style="color:#ffffff;border-radius:9999px;margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;width:22px;min-height:22px;font-size:11px;font-weight:600;line-height:22px;box-sizing:border-box;flex-shrink:0">2</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontSize":"16px","fontWeight":"600","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:16px;font-weight:600;line-height:1.4"><?php echo esc_html( 'shipping' === $yaycheckout_main ? __( 'Shipping address', 'yaycheckout' ) : __( 'Billing address', 'yaycheckout' ) ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

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

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"10px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"backgroundColor":"yaycheckout-primary","align":"center","style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"11px","fontWeight":"600","lineHeight":"22px"},"border":{"radius":"9999px"},"dimensions":{"minHeight":"22px","width":"22px"},"layout":{"selfStretch":"fixed","flexSize":"22px"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<p class="has-text-align-center has-text-color has-background has-yaycheckout-primary-background-color" style="color:#ffffff;border-radius:9999px;margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;width:22px;min-height:22px;font-size:11px;font-weight:600;line-height:22px;box-sizing:border-box;flex-shrink:0">3</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontSize":"16px","fontWeight":"600","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:16px;font-weight:600;line-height:1.4"><?php esc_html_e( 'Shipping options', 'yaycheckout' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/shipping-methods {"showTitle":false,"layout":"grid","style":{"yaycheckout":{"background":"#ffffff","radius":"4px"}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"12px"}}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"10px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"backgroundColor":"yaycheckout-primary","align":"center","style":{"color":{"text":"#ffffff"},"typography":{"fontSize":"11px","fontWeight":"600","lineHeight":"22px"},"border":{"radius":"9999px"},"dimensions":{"minHeight":"22px","width":"22px"},"layout":{"selfStretch":"fixed","flexSize":"22px"},"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
<p class="has-text-align-center has-text-color has-background has-yaycheckout-primary-background-color" style="color:#ffffff;border-radius:9999px;margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0;width:22px;min-height:22px;font-size:11px;font-weight:600;line-height:22px;box-sizing:border-box;flex-shrink:0">4</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"style":{"typography":{"fontSize":"16px","fontWeight":"600","lineHeight":"1.4"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="margin-top:0;margin-bottom:0;font-size:16px;font-weight:600;line-height:1.4"><?php esc_html_e( 'Payment options', 'yaycheckout' ); ?></h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/payment {"showTitle":false,"layout":"box","style":{"color":{"background":"#ffffff"},"border":{"radius":"4px"}}} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/order-note {"placeholder":"<?php esc_html_e( 'Notes about your order.', 'yaycheckout' ); ?>","disclosure":"checkbox","disclosureText":"<?php esc_html_e( 'Add a note to your order', 'yaycheckout' ); ?>"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"380px"} -->
<div class="wp-block-column" style="flex-basis:380px"><!-- wp:group {"style":{"position":{"type":"sticky","top":"24px"},"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-group"><!-- wp:group {"borderColor":"yaycheckout-border","style":{"color":{"background":"#ffffff"},"border":{"width":"1px","style":"solid","radius":{"topLeft":"4px","topRight":"4px","bottomLeft":"0px","bottomRight":"0px"}},"spacing":{"padding":{"top":"28px","right":"28px","bottom":"24px","left":"28px"},"blockGap":"18px"},"typography":{"fontSize":"12px","lineHeight":"1.6","fontFamily":"Menlo, Consolas, monaco, monospace"}}} -->
<div class="wp-block-group has-border-color has-background has-yaycheckout-border-border-color" style="border-style:solid;border-width:1px;border-top-left-radius:4px;border-top-right-radius:4px;border-bottom-left-radius:0;border-bottom-right-radius:0;background-color:#ffffff;padding-top:28px;padding-right:28px;padding-bottom:24px;padding-left:28px;font-size:12px;font-family:Menlo, Consolas, monaco, monospace;line-height:1.6"><!-- wp:yaycheckout/order-summary {"label":"<?php esc_html_e( 'Order summary', 'yaycheckout' ); ?>","showTitle":false,"style":{"spacing":{"blockGap":"18px"}}} -->
<!-- wp:yaycheckout/order-summary-header {"layout":{"type":"flex","justifyContent":"center","verticalAlignment":"center","flexWrap":"wrap"}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"2px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo {"width":28} /-->
<?php else : ?>
<!-- wp:site-title {"level":0,"textAlign":"center","style":{"typography":{"fontSize":"14px","fontWeight":"700","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->
<?php endif; ?>

<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p class="has-text-align-center" style="margin-top:0;margin-bottom:0;font-size:12px;text-transform:uppercase"><?php esc_html_e( 'Order preview', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-header -->

<!-- wp:yaycheckout/order-summary-details {"lock":{"remove":true},"style":{"spacing":{"blockGap":"18px"}}} -->
<!-- wp:group {"style":{"border":{"top":{"color":"#cccccc","width":"1px","style":"dashed"}},"spacing":{"padding":{"top":"18px"},"blockGap":"2px"}}} -->
<div class="wp-block-group" style="border-top-color:#cccccc;border-top-style:dashed;border-top-width:1px;padding-top:18px"><!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:12px;text-transform:uppercase"><?php esc_html_e( 'Contact', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/field-value {"fieldKeys":["billing_email"],"placeholder":"email@________","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_phone"],"placeholder":"(phone optional)","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"2px"}}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:12px;text-transform:uppercase"><?php echo esc_html( $yaycheckout_ship_label ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_first_name","<?php echo esc_attr( $yaycheckout_main ); ?>_last_name"],"separator":" ","placeholder":"FIRST LAST","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_address_1"],"placeholder":"STREET ADDRESS","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/field-value {"blockId":"rcptapt2","fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_address_2"],"yaycheckoutVisibility":{"action":"show","rules":[{"type":"block","block":"<?php echo esc_attr( $yaycheckout_apt_id ); ?>","is":"not_empty"}]},"style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_city","<?php echo esc_attr( $yaycheckout_main ); ?>_state"],"separator":", ","placeholder":"CITY, CA","style":{"layout":{"selfStretch":"fill","flexSize":null},"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_postcode"],"placeholder":"_____","style":{"layout":{"selfStretch":"fit","flexSize":null},"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group -->

<!-- wp:yaycheckout/field-value {"fieldKeys":["<?php echo esc_attr( $yaycheckout_main ); ?>_country"],"placeholder":"UNITED STATES (US)","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"top":{"color":"#cccccc","width":"1px","style":"dashed"}},"spacing":{"padding":{"top":"18px"},"blockGap":"4px"}}} -->
<div class="wp-block-group" style="border-top-color:#cccccc;border-top-style:dashed;border-top-width:1px;padding-top:18px"><!-- wp:yaycheckout/order-summary-items {"showLabels":false,"showThumbnails":false,"quantityDisplay":"inline","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"spacing":{"blockGap":"4px"}}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"border":{"top":{"color":"#cccccc","width":"1px","style":"dashed"}},"spacing":{"padding":{"top":"18px"},"blockGap":"4px"}}} -->
<div class="wp-block-group" style="border-top-color:#cccccc;border-top-style:dashed;border-top-width:1px;padding-top:18px"><!-- wp:yaycheckout/order-summary-breakdown {"subtotalLabel":"<?php esc_html_e( 'SUBTOTAL', 'yaycheckout' ); ?>","style":{"typography":{"fontSize":"12px","textTransform":"uppercase"},"yaycheckout":{"label":{"color":{"text":"var:preset|color|yaycheckout-muted"}}}}} /-->

<!-- wp:yaycheckout/order-summary-total {"label":"<?php esc_html_e( 'TOTAL', 'yaycheckout' ); ?>","style":{"spacing":{"padding":{"top":"6px"}},"typography":{"fontSize":"16px","textTransform":"uppercase"},"yaycheckout":{"label":{"typography":{"fontSize":"16px","fontWeight":"700"}},"value":{"typography":{"fontSize":"16px","fontWeight":"700"}}}}} /--></div>
<!-- /wp:group -->
<!-- /wp:yaycheckout/order-summary-details -->
<!-- /wp:yaycheckout/order-summary --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"color":{"background":"#ffffff"},"border":{"left":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"},"right":{"color":"var:preset|color|yaycheckout-border","width":"1px","style":"solid"},"bottom":{"color":"#cccccc","width":"1px","style":"dashed"}},"dimensions":{"minHeight":"10px"}}} -->
<div class="wp-block-group has-background" style="border-right-color:var(--wp--preset--color--yaycheckout-border);border-bottom-color:#cccccc;border-left-color:var(--wp--preset--color--yaycheckout-border);border-right-style:solid;border-bottom-style:dashed;border-left-style:solid;border-right-width:1px;border-bottom-width:1px;border-left-width:1px;background-color:#ffffff;min-height:10px"></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"16px"},"blockGap":"12px"}}} -->
<div class="wp-block-group" style="margin-top:16px"><!-- wp:yaycheckout/coupon-form {"placeholder":"<?php esc_html_e( 'Enter code', 'yaycheckout' ); ?>","disclosure":"collapsible","disclosureText":"<?php esc_html_e( 'Add coupons', 'yaycheckout' ); ?>"} -->
<!-- wp:yaycheckout/coupon-button {"buttonText":"<?php esc_html_e( 'Apply', 'yaycheckout' ); ?>","className":"is-style-outline","style":{"typography":{"fontSize":"13px","fontWeight":"500"}}} /-->
<!-- /wp:yaycheckout/coupon-form -->
<?php if ( '' !== $yaycheckout_terms_sentence ) : ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"12px","lineHeight":"1.5"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0;font-size:12px;line-height:1.5"><?php echo wp_kses_post( $yaycheckout_terms_sentence ); ?></p>
<!-- /wp:paragraph -->
<?php endif; ?>

<!-- wp:yaycheckout/terms {"textColor":"yaycheckout-muted","style":{"typography":{"fontSize":"12px"}}} /-->

<!-- wp:yaycheckout/place-order {"buttonText":"<?php esc_html_e( 'Place Order', 'yaycheckout' ); ?>\u003cspan class=\u0022yaycheckout-live-value\u0022 data-yaycheckout-value=\u0022cart_total\u0022 data-fallback=\u0022\u0022 data-prefix=\u0022 · \u0022 data-suffix=\u0022\u0022\u003e · <?php esc_html_e( 'Order total', 'yaycheckout' ); ?>\u003c/span\u003e","style":{"typography":{"fontSize":"15px","fontWeight":"600","textAlign":"center"},"border":{"radius":"2px"},"dimensions":{"width":"var:preset|dimension|100"}}} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout --></div>
<!-- /wp:group -->
