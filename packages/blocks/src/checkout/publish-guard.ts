import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';
const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const LOCK_KEY = 'yaycheckout-missing-checkout';

/**
 * Locks saving while the layout has no Checkout block: the Checkout block is
 * the form, so a layout without one cannot take an order. Checkout may sit at
 * any depth. Core has no publish-only lock, so drafts are held too.
 */
function LayoutPublishGuard() {
	const postType = useSelect( ( select ) => select( editorStore ).getCurrentPostType(), [] );
	const hasCheckout = useSelect(
		( select ) => select( blockEditorStore ).getGlobalBlockCount( CHECKOUT_BLOCK ) > 0,
		[]
	);
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );
	const { createWarningNotice, removeNotice } = useDispatch( noticesStore );
	const missing = postType === LAYOUT_POST_TYPE && ! hasCheckout;

	useEffect( () => {
		if ( ! missing ) {
			return;
		}

		lockPostSaving( LOCK_KEY );
		createWarningNotice( __( 'Add a Checkout block to save this layout.', 'yaycheckout' ), {
			id: LOCK_KEY,
			isDismissible: false,
		} );

		return () => {
			unlockPostSaving( LOCK_KEY );
			removeNotice( LOCK_KEY );
		};
	}, [ missing, lockPostSaving, unlockPostSaving, createWarningNotice, removeNotice ] );

	return null;
}

registerPlugin( 'yaycheckout-layout-publish-guard', {
	render: LayoutPublishGuard,
} );
