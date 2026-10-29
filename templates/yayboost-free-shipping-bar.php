<?php
/**
 * YayBoost's free shipping bar in a layout's spot (Integrations\YayBoost::free_shipping_bar_shortcode()), drawn
 * like the templates' progress card: YayBoost's message, how far the cart is, and a bar in the layout's Primary.
 *
 * @var string $message  YayBoost's message, its amounts already formatted.
 * @var string $amount   Current / threshold, as prices or item counts.
 * @var float  $progress 0 to 100.
 * @var bool   $achieved Whether the cart has free shipping.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="yaycheckout-shipping-bar<?php echo $achieved ? ' is-achieved' : ''; ?>">
    <div class="yaycheckout-shipping-bar__head">
        <span class="yaycheckout-shipping-bar__message"><?php echo wp_kses_post( $message ); ?></span>
        <?php if ( ! $achieved ) : ?>
            <span class="yaycheckout-shipping-bar__amount"><?php echo wp_kses_post( $amount ); ?></span>
        <?php endif; ?>
    </div>
    <div class="yaycheckout-shipping-bar__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) round( $progress ) ); ?>">
        <div class="yaycheckout-shipping-bar__fill" style="width:<?php echo esc_attr( (string) round( $progress, 2 ) ); ?>%"></div>
    </div>
</div>
