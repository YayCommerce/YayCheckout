<?php
/**
 * YayBoost's Frequently bought together in a layout's spot (Integrations\YayBoost::fbt_shortcode()): each product
 * FBT pairs with the cart, added or removed in one click by assets/js/checkout-yayboost-fbt.js.
 *
 * @var array<int, array{product: WC_Product, source_id: int, cart_item_key: string}> $items
 * @var string                $title   The section title.
 * @var array<string, string> $wrapper The script's data-* attributes, by name.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="yaycheckout-fbt"
<?php
foreach ( $wrapper as $yaycheckout_name => $yaycheckout_value ) {
    echo ' data-' . esc_attr( $yaycheckout_name ) . '="' . esc_attr( $yaycheckout_value ) . '"';
}
?>
>
    <div class="yaycheckout-fbt__header">
        <span class="yaycheckout-fbt__title"><?php echo esc_html( $title ); ?></span>
        <span class="yaycheckout-fbt__caption"><?php esc_html_e( 'Add in one click', 'yaycheckout' ); ?></span>
    </div>
    <div class="yaycheckout-fbt__list">
        <?php
        foreach ( $items as $yaycheckout_item ) :
            $yaycheckout_product = $yaycheckout_item['product'];
            $yaycheckout_added   = '' !== $yaycheckout_item['cart_item_key'];
            $yaycheckout_label   = $yaycheckout_added
                /* translators: %s: product name */
                ? sprintf( __( 'Remove %s', 'yaycheckout' ), $yaycheckout_product->get_name() )
                /* translators: %s: product name */
                : sprintf( __( 'Add %s', 'yaycheckout' ), $yaycheckout_product->get_name() );
            ?>
            <div class="yaycheckout-fbt__item<?php echo $yaycheckout_added ? ' is-added' : ''; ?>">
                <span class="yaycheckout-fbt__image"><?php echo $yaycheckout_product->get_image( 'woocommerce_gallery_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's escaped image markup. ?></span>
                <div class="yaycheckout-fbt__body">
                    <div class="yaycheckout-fbt__name"><?php echo esc_html( $yaycheckout_product->get_name() ); ?></div>
                    <div class="yaycheckout-fbt__price"><?php echo wp_kses_post( $yaycheckout_product->get_price_html() ); ?></div>
                </div>
                <?php if ( $yaycheckout_added ) : ?>
                    <button type="button" class="yaycheckout-fbt__button" data-cart-item-key="<?php echo esc_attr( $yaycheckout_item['cart_item_key'] ); ?>" aria-label="<?php echo esc_attr( $yaycheckout_label ); ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>
                        <?php esc_html_e( 'Added', 'yaycheckout' ); ?>
                    </button>
                <?php else : ?>
                    <button type="button" class="yaycheckout-fbt__button" data-product-id="<?php echo esc_attr( (string) $yaycheckout_product->get_id() ); ?>" data-source-product-id="<?php echo esc_attr( (string) $yaycheckout_item['source_id'] ); ?>" aria-label="<?php echo esc_attr( $yaycheckout_label ); ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        <?php esc_html_e( 'Add', 'yaycheckout' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
