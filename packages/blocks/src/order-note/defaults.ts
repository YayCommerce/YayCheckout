import { __ } from '@wordpress/i18n';

/** Mirrors LayoutMarkupReader::ORDER_NOTE_KEY: WooCommerce's posted name for the order note. */
export const ORDER_NOTE_KEY = 'order_comments';

/** WooCommerce's defaults, as order-note/render.php prints them when the merchant leaves them empty. */
export const ORDER_NOTE_LABEL = __( 'Order notes', 'yaycheckout' );
export const ORDER_NOTE_PLACEHOLDER = __( 'Notes about your order, e.g. special notes for delivery.', 'yaycheckout' );
