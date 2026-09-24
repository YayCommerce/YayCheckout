import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';
import { duplicateKeys, EXTRA_FIELD_KEY_PATTERN, extraFieldKeys } from '../shared/extra-field-keys';
import { editorData } from '../shared/editor-data';

const CHECKOUT_BLOCK = 'yaycheckout/checkout';
const LOCK_KEY = 'yaycheckout-layout-invalid';

/**
 * Locks saving while the layout cannot take an order: no Checkout block (the
 * form, at any depth), or an extra field whose Key is empty, malformed or
 * shared, since the Key is the posted name the order meta is read back by. Core has no
 * publish-only lock, so drafts are held too. Mirrors LayoutMarkupReader::publish_error().
 */
function LayoutPublishGuard() {
	const message = useSelect( ( select ) => {
		if ( select( editorStore ).getCurrentPostType() !== editorData.postType ) {
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

		const malformed = [ ...new Set( keys.filter( ( key ) => ! EXTRA_FIELD_KEY_PATTERN.test( key ) ) ) ];

		if ( malformed.length > 0 ) {
			return sprintf(
				/* translators: %s: comma-separated extra field keys. */
				__( 'Extra field Keys may use only letters, numbers and underscores, and must not start with an underscore: %s.', 'yaycheckout' ),
				malformed.join( ', ' )
			);
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
