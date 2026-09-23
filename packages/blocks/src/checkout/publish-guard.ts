import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';
import { duplicateKeys, extraFieldKeys } from '../shared/extra-field-keys';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';
const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const LOCK_KEY = 'yaycheckout-layout-invalid';

/**
 * Locks saving while the layout cannot take an order: no Checkout block (the
 * form, at any depth), or an extra field whose Key is empty or shared, since
 * the Key is the posted name the order meta is read back by. Core has no
 * publish-only lock, so drafts are held too. Mirrors LayoutRepository::publish().
 */
function LayoutPublishGuard() {
	const message = useSelect( ( select ) => {
		if ( select( editorStore ).getCurrentPostType() !== LAYOUT_POST_TYPE ) {
			return '';
		}

		const blockEditor = select( blockEditorStore );

		if ( blockEditor.getGlobalBlockCount( CHECKOUT_BLOCK ) === 0 ) {
			return __( 'Add a Checkout block to save this layout.', 'yaycheckout' );
		}

		const keys = extraFieldKeys( blockEditor );

		if ( keys.includes( '' ) ) {
			return __( 'Every extra field needs a Key before this layout can be saved.', 'yaycheckout' );
		}

		const duplicates = duplicateKeys( keys );

		if ( duplicates.length > 0 ) {
			/* translators: %s: comma-separated extra field keys. */
			return sprintf( __( 'Extra field Keys must be unique. Used more than once: %s.', 'yaycheckout' ), duplicates.join( ', ' ) );
		}

		return '';
	}, [] );
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );
	const { createWarningNotice, removeNotice } = useDispatch( noticesStore );

	useEffect( () => {
		if ( message === '' ) {
			return;
		}

		lockPostSaving( LOCK_KEY );
		createWarningNotice( message, { id: LOCK_KEY, isDismissible: false } );

		return () => {
			unlockPostSaving( LOCK_KEY );
			removeNotice( LOCK_KEY );
		};
	}, [ message, lockPostSaving, unlockPostSaving, createWarningNotice, removeNotice ] );

	return null;
}

registerPlugin( 'yaycheckout-layout-publish-guard', {
	render: LayoutPublishGuard,
} );
