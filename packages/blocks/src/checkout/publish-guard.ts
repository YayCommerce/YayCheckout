import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';
import {
	duplicateKeys,
	EXTRA_FIELD_KEY_CONTROL_CLASS,
	EXTRA_FIELD_KEY_PATTERN,
	extraFields,
} from '../shared/extra-field-keys';
import { editorData } from '../shared/editor-data';
import checkoutMeta from './block.json';
import { layoutStateStore } from './layout-state';

const CHECKOUT_BLOCK = checkoutMeta.name;
export const LAYOUT_INVALID_LOCK = 'yaycheckout-layout-invalid';

/** Copy of LayoutStatus::live(): statuses shoppers may see; only these hold the save lock, as the server only refuses these. */
const LIVE_STATUSES = [ 'publish', 'future' ];

export interface LayoutProblem {
	message: string;
	// The first extra field at fault, for the notice's "Go to field" action; '' when none.
	clientId: string;
}

const NO_PROBLEM: LayoutProblem = { message: '', clientId: '' };

/**
 * Focuses the Key control once the inspector has rendered the selected block. Waits a
 * tick first, so a Key control left from the previously selected field isn't the one found.
 */
function focusKeyControl( attempts = 20 ) {
	window.setTimeout( () => {
		const input = document.querySelector< HTMLInputElement >( `.${ EXTRA_FIELD_KEY_CONTROL_CLASS } input` );

		if ( input ) {
			input.focus();
		} else if ( attempts > 1 ) {
			focusKeyControl( attempts - 1 );
		}
	}, 50 );
}

/**
 * Why the layout cannot take an order: no Checkout block at any depth, or an extra field
 * Key that is empty, malformed or shared. Mirrors LayoutMarkupReader::publish_error().
 * layout-actions.tsx reads it too, to hold Activate.
 */
export function useLayoutProblem(): LayoutProblem {
	return useSelect( ( select ): LayoutProblem => {
		if ( select( editorStore ).getCurrentPostType() !== editorData.postType ) {
			return NO_PROBLEM;
		}

		const blockEditor = select( blockEditorStore );

		if ( blockEditor.getGlobalBlockCount( CHECKOUT_BLOCK ) === 0 ) {
			return { message: __( 'Add a Checkout block to this layout before activating it.', 'yaycheckout' ), clientId: '' };
		}

		const fields = extraFields( blockEditor );
		const keys = fields.map( ( field ) => field.key );
		const firstWith = ( isAtFault: ( key: string ) => boolean ) =>
			fields.find( ( field ) => isAtFault( field.key ) )?.clientId ?? '';

		if ( keys.includes( '' ) ) {
			return {
				message: __( 'Every extra field needs a Key before this layout can be activated.', 'yaycheckout' ),
				clientId: firstWith( ( key ) => key === '' ),
			};
		}

		const malformed = [ ...new Set( keys.filter( ( key ) => ! EXTRA_FIELD_KEY_PATTERN.test( key ) ) ) ];

		if ( malformed.length > 0 ) {
			return {
				message: sprintf(
					/* translators: %s: comma-separated extra field keys. */
					__( 'Extra field Keys may use only letters, numbers and underscores, and must not start with an underscore: %s.', 'yaycheckout' ),
					malformed.join( ', ' )
				),
				clientId: firstWith( ( key ) => malformed.includes( key ) ),
			};
		}

		const duplicates = duplicateKeys( keys );

		if ( duplicates.length > 0 ) {
			return {
				/* translators: %s: comma-separated extra field keys. */
				message: sprintf( __( 'Extra field Keys must be unique. Used more than once: %s.', 'yaycheckout' ), duplicates.join( ', ' ) ),
				clientId: firstWith( ( key ) => duplicates.includes( key ) ),
			};
		}

		return NO_PROBLEM;
	}, [] );
}

/**
 * Shows the problem as a notice, and locks saving only while the layout is live: active, or
 * published or scheduled, matching the server. A draft is inactive,
 * so it saves freely and the rules wait for Activate.
 */
function LayoutPublishGuard() {
	const { message, clientId } = useLayoutProblem();
	const isLive = useSelect(
		( select ) =>
			select( layoutStateStore ).isActive() === true ||
			LIVE_STATUSES.includes( select( editorStore ).getEditedPostAttribute( 'status' ) as string ),
		[]
	);
	const { lockPostSaving, unlockPostSaving } = useDispatch( editorStore );
	const { createWarningNotice, removeNotice } = useDispatch( noticesStore );
	const { selectBlock } = useDispatch( blockEditorStore );
	const { openGeneralSidebar } = useDispatch( 'core/edit-post' );

	useEffect( () => {
		if ( message === '' ) {
			return;
		}

		if ( isLive ) {
			lockPostSaving( LAYOUT_INVALID_LOCK );
		}

		createWarningNotice( message, {
			id: LAYOUT_INVALID_LOCK,
			isDismissible: false,
			actions:
				clientId === ''
					? []
					: [
							{
								label: __( 'Go to field', 'yaycheckout' ),
								onClick: () => {
									selectBlock( clientId );
									openGeneralSidebar( 'edit-post/block' );
									focusKeyControl();
								},
							},
					  ],
		} );

		return () => {
			unlockPostSaving( LAYOUT_INVALID_LOCK );
			removeNotice( LAYOUT_INVALID_LOCK );
		};
	}, [
		message,
		clientId,
		isLive,
		lockPostSaving,
		unlockPostSaving,
		createWarningNotice,
		removeNotice,
		selectBlock,
		openGeneralSidebar,
	] );

	return null;
}

registerPlugin( 'yaycheckout-layout-publish-guard', {
	render: LayoutPublishGuard,
} );
