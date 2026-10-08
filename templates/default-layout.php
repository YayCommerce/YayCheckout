<?php
/**
 * Default layout: the block markup a new or reset layout starts with, printed into
 * LayoutRepository::default_markup().
 *
 * To update it, build the layout in the block editor, copy it from the Code editor and
 * paste it here, then put back the PHP: translated text, the logo-or-title choice and the
 * footer links. Keep the markup as the editor saves it: a hand edit that differs by one
 * class or style makes the editor flag the block as invalid.
 *
 * Billing and Shipping hold the structure packages/blocks/src/shared/address-template.ts gives a new
 * section; change both together. Their keys come from packages/blocks/src/shared/address-fields.ts.
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"style":{"spacing":{"padding":{"left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group" style="padding-right:24px;padding-left:24px"><!-- wp:group {"tagName":"header","style":{"spacing":{"padding":{"top":"16px","bottom":"16px"},"margin":{"bottom":"32px"}},"border":{"bottom":{"color":"#e0e0e0","width":"1px","style":"solid"}}},"layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"center","flexWrap":"wrap"}} -->
<header class="wp-block-group" style="border-bottom-color:#e0e0e0;border-bottom-style:solid;border-bottom-width:1px;margin-bottom:32px;padding-top:16px;padding-bottom:16px"><?php if ( has_custom_logo() ) : ?>
<!-- wp:site-logo /-->
<?php else : ?>
<!-- wp:site-title {"level":0} /-->
<?php endif; ?>

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<p style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Secure checkout', 'yaycheckout' ); ?></p>
<!-- /wp:paragraph --></header>
<!-- /wp:group -->

<!-- wp:yaycheckout/checkout -->
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%"><!-- wp:yaycheckout/express-pay /-->

<!-- wp:yaycheckout/email /-->

<!-- wp:yaycheckout/billing -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"24px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"billing_first_name","type":"text","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"billing_last_name","type":"text","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"billing_company","type":"text","label":"<?php esc_html_e( 'Company', 'yaycheckout' ); ?>","required":false} /-->

<!-- wp:yaycheckout/field {"fieldKey":"billing_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"billing_address_1","type":"text","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"billing_address_2","type":"text","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","required":false} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"left":"24px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:50%"><!-- wp:yaycheckout/field {"fieldKey":"billing_city","type":"text","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"billing_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"billing_postcode","type":"text","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"billing_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'In case we need to contact you about your order', 'yaycheckout' ); ?>","required":false} /-->
<!-- /wp:yaycheckout/billing -->

<!-- wp:yaycheckout/shipping -->
<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"24px"}}}} -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"shipping_first_name","type":"text","label":"<?php esc_html_e( 'First name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:yaycheckout/field {"fieldKey":"shipping_last_name","type":"text","label":"<?php esc_html_e( 'Last name', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_company","type":"text","label":"<?php esc_html_e( 'Company', 'yaycheckout' ); ?>","required":false} /-->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_country","type":"country","label":"<?php esc_html_e( 'Country/Region', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_address_1","type":"text","label":"<?php esc_html_e( 'Address', 'yaycheckout' ); ?>","placeholder":"<?php esc_html_e( 'House number and street name', 'yaycheckout' ); ?>","required":true} /-->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_address_2","type":"text","label":"<?php esc_html_e( 'Apartment, suite, unit, etc.', 'yaycheckout' ); ?>","required":false} /-->

<!-- wp:columns {"verticalAlignment":"bottom","style":{"spacing":{"blockGap":{"left":"24px"}}}} -->
<div class="wp-block-columns are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"50%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:50%"><!-- wp:yaycheckout/field {"fieldKey":"shipping_city","type":"text","label":"<?php esc_html_e( 'City', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"shipping_state","type":"state","label":"<?php esc_html_e( 'State', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"25%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:25%"><!-- wp:yaycheckout/field {"fieldKey":"shipping_postcode","type":"text","label":"<?php esc_html_e( 'ZIP Code', 'yaycheckout' ); ?>","required":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:yaycheckout/field {"fieldKey":"shipping_phone","type":"tel","label":"<?php esc_html_e( 'Phone', 'yaycheckout' ); ?>","description":"<?php esc_html_e( 'In case we need to contact you about your order', 'yaycheckout' ); ?>","required":false} /-->
<!-- /wp:yaycheckout/shipping -->

<!-- wp:yaycheckout/shipping-methods /-->

<!-- wp:yaycheckout/payment /-->

<!-- wp:yaycheckout/place-order {"style":{"typography":{"textAlign":"center"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:group {"style":{"position":{"type":"sticky","top":"0px"}}} -->
<div class="wp-block-group"><!-- wp:yaycheckout/order-summary -->
<!-- wp:yaycheckout/order-summary-items {"lock":{"move":true,"remove":true}} /-->

<!-- wp:yaycheckout/order-summary-totals {"lock":{"move":true,"remove":true}} /-->
<!-- /wp:yaycheckout/order-summary -->

<!-- wp:yaycheckout/coupon-form /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:yaycheckout/checkout -->

<!-- wp:group {"tagName":"footer","style":{"spacing":{"padding":{"top":"16px","bottom":"16px"},"margin":{"top":"48px"}},"border":{"top":{"color":"#e0e0e0","width":"1px","style":"solid"}}},"fontSize":"small","layout":{"type":"flex","justifyContent":"space-between","verticalAlignment":"center","flexWrap":"wrap"}} -->
<footer class="wp-block-group has-small-font-size" style="border-top-color:#e0e0e0;border-top-style:solid;border-top-width:1px;margin-top:48px;padding-top:16px;padding-bottom:16px"><!-- wp:paragraph -->
<p>&copy; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Return to cart', 'yaycheckout' ); ?></a> <?php if ( '' !== get_privacy_policy_url() ) : ?>
&middot; <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy policy', 'yaycheckout' ); ?></a> <?php endif; ?><?php if ( wc_terms_and_conditions_page_id() > 0 ) : ?>
&middot; <a href="<?php echo esc_url( wc_get_page_permalink( 'terms' ) ); ?>"><?php esc_html_e( 'Terms and conditions', 'yaycheckout' ); ?></a><?php endif; ?></p>
<!-- /wp:paragraph --></footer>
<!-- /wp:group --></div>
<!-- /wp:group -->
