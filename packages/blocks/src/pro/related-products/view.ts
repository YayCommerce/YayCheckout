/**
 * Pro: on the checkout, refreshes the order review when the Related products collection's Add to cart button
 * changes the cart. WooCommerce's button adds through the Store API and announces it with
 * wc-blocks_store_sync_required, which only the Cart and Checkout blocks hear; this checkout is WooCommerce's
 * classic form, which refreshes on update_checkout. RelatedProductsCollection enqueues it on the checkout only.
 */
import { jquery } from '../../shared/jquery';

window.addEventListener( 'wc-blocks_store_sync_required', ( event ) => {
	// from_iAPI: a change made by an Interactivity API store, such as the button's. Other types come from
	// WooCommerce's data store, which this page doesn't run.
	if ( ( event as CustomEvent< { type?: string } > ).detail?.type === 'from_iAPI' ) {
		jquery( document.body )?.trigger( 'update_checkout' );
	}
} );
