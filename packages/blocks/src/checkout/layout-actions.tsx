import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Modal } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { createPortal, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';
import { errorMessage } from '../shared/error-message';
import { layoutStateStore } from './layout-state';
import { useLayoutProblem } from './publish-guard';

/** LayoutEditorCanvas hides core's status controls only under this class, so they stay if the buttons can't mount. */
const MOUNTED_CLASS = 'yaycheckout-has-layout-actions';

/** The header's settings area: editor-header from WordPress 6.6, edit-post-header in 6.5. */
const HEADER_SELECTOR = '.editor-header__settings, .edit-post-header__settings';

type Action = 'activate' | 'deactivate';

/** Copy of the `is_active` field of Layout::to_array(), the yaycheckout/v1 layout shape. */
type LayoutWire = { is_active: boolean };

/** Copy of LayoutRestController::create_preview_link(); previewLinkWireSchema in the SPA. */
type PreviewLinkWire = { url: string; expires_at: string };

/** Everything the two actions say; the copy matches SetActiveModal and DeactivateModal in the SPA. */
function copyFor( action: Action, title: string ) {
	return action === 'activate'
		? {
				label: __( 'Activate', 'yaycheckout' ),
				busy: __( 'Activating…', 'yaycheckout' ),
				/* translators: %s: layout title */
				title: sprintf( __( 'Make "%s" your live checkout?', 'yaycheckout' ), title ),
				body: __( 'Shoppers will start seeing this layout as soon as you confirm.', 'yaycheckout' ),
				done: __( 'Layout activated.', 'yaycheckout' ),
				failed: __( 'The layout could not be activated.', 'yaycheckout' ),
		  }
		: {
				label: __( 'Deactivate', 'yaycheckout' ),
				busy: __( 'Deactivating…', 'yaycheckout' ),
				/* translators: %s: layout title */
				title: sprintf( __( 'Deactivate "%s"?', 'yaycheckout' ), title ),
				body: __(
					'Shoppers will stop seeing this layout. It becomes inactive, and you can activate it again at any time.',
					'yaycheckout'
				),
				done: __( 'Layout deactivated.', 'yaycheckout' ),
				failed: __( 'The layout could not be deactivated.', 'yaycheckout' ),
		  };
}

/** A container prepended to the header once it renders; the header can appear after the plugin. */
function useHeaderSlot( enabled: boolean ): HTMLElement | null {
	const [ slot, setSlot ] = useState< HTMLElement | null >( null );

	useEffect( () => {
		if ( ! enabled ) {
			return;
		}

		const container = document.createElement( 'div' );
		container.className = 'yaycheckout-layout-actions';

		const mount = () => {
			const header = document.querySelector( HEADER_SELECTOR );

			if ( ! header ) {
				return false;
			}

			header.prepend( container );
			document.body.classList.add( MOUNTED_CLASS );
			setSlot( container );
			return true;
		};

		const observer = new MutationObserver( () => mount() && observer.disconnect() );

		if ( ! mount() ) {
			observer.observe( document.body, { childList: true, subtree: true } );
		}

		return () => {
			observer.disconnect();
			container.remove();
			document.body.classList.remove( MOUNTED_CLASS );
		};
	}, [ enabled ] );

	return slot;
}

/**
 * The layout editor's own Preview, Save, Activate and Deactivate, in place of core's Publish
 * controls: Save keeps the status, and the others call the same routes as the layouts list, so
 * LayoutRepository's rules decide. Merchants see Active or Inactive, never a post status.
 */
function LayoutActions() {
	const { postType, postId, title, restBase, isDirty, isSaving, isLocked } = useSelect( ( selectStore ) => {
		const editor = selectStore( editorStore );
		const type = editor.getCurrentPostType();

		return {
			postType: type,
			postId: editor.getCurrentPostId() as number,
			title: editor.getEditedPostAttribute( 'title' ) as string,
			restBase: selectStore( coreStore ).getPostType( type )?.rest_base as string | undefined,
			isDirty: editor.isEditedPostDirty(),
			isSaving: editor.isSavingPost(),
			isLocked: editor.isPostSavingLocked(),
		};
	}, [] );
	const isLayout = postType === editorData.postType;
	const { message: problem } = useLayoutProblem();
	const { savePost } = useDispatch( editorStore );
	const { receiveEntityRecords } = useDispatch( coreStore );
	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );
	const isActive = useSelect( ( selectStore ) => selectStore( layoutStateStore ).isActive(), [] );
	const { setActive } = useDispatch( layoutStateStore );
	const [ confirm, setConfirm ] = useState< Action | null >( null );
	const [ isBusy, setBusy ] = useState( false );
	const [ isPreviewing, setPreviewing ] = useState( false );
	const slot = useHeaderSlot( isLayout );

	useEffect( () => {
		if ( ! isLayout || ! postId ) {
			return;
		}

		apiFetch< LayoutWire >( { path: `/yaycheckout/v1/layouts/${ postId }` } )
			.then( ( layout ) => setActive( layout.is_active ) )
			// Unknown state: Save stays, Activate and Deactivate stay hidden.
			.catch( () => setActive( null ) );
	}, [ isLayout, postId ] );

	if ( ! isLayout || ! slot ) {
		return null;
	}

	/** The route moved post_status behind the editor's back; without this the next Save would send the old one. */
	const refreshPost = async () => {
		const record = await apiFetch< Record< string, unknown > >( {
			path: `/wp/v2/${ restBase ?? postType }/${ postId }?context=edit`,
		} );
		receiveEntityRecords( 'postType', postType, [ record ] );
	};

	const run = async ( action: Action ) => {
		const copy = copyFor( action, title );
		setBusy( true );
		try {
			// The server checks the saved markup, so unsaved edits go first, and a failed save stops here.
			if ( action === 'activate' && isDirty ) {
				await savePost();

				if ( select( editorStore ).didPostSaveRequestFail() ) {
					return;
				}
			}

			const layout = await apiFetch< LayoutWire >( {
				path: `/yaycheckout/v1/layouts/${ postId }/${ action }`,
				method: 'POST',
			} );

			await refreshPost();
			setActive( layout.is_active );
			createSuccessNotice( copy.done, { type: 'snackbar' } );
		} catch ( error ) {
			createErrorNotice( await errorMessage( error, copy.failed ), { type: 'snackbar' } );
		} finally {
			setBusy( false );
			setConfirm( null );
		}
	};

	/** Previews the saved markup; unsaved edits are left alone and don't show. */
	const preview = async () => {
		// Opened inside the click: a tab opened after the await is a popup the browser blocks.
		const tab = window.open( '', '_blank' );
		setPreviewing( true );
		try {
			const link = await apiFetch< PreviewLinkWire >( {
				path: `/yaycheckout/v1/layouts/${ postId }/preview-link`,
				method: 'POST',
			} );

			if ( tab ) {
				tab.opener = null;
				tab.location.href = link.url;
			} else {
				window.open( link.url, '_blank', 'noopener' );
			}
		} catch ( error ) {
			tab?.close();
			createErrorNotice( await errorMessage( error, __( 'The preview could not be opened.', 'yaycheckout' ) ), {
				type: 'snackbar',
			} );
		} finally {
			setPreviewing( false );
		}
	};

	// Unknown state (null) offers neither: Save alone.
	const toggle: Action | null = isActive === null ? null : isActive ? 'deactivate' : 'activate';
	const modal = confirm && copyFor( confirm, title );

	return createPortal(
		<>
			<Flex gap={ 2 } expanded={ false }>
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					isBusy={ isPreviewing }
					disabled={ isPreviewing || isBusy || isSaving }
					accessibleWhenDisabled
					onClick={ preview }
				>
					{ __( 'Preview', 'yaycheckout' ) }
				</Button>
				{ toggle && (
					<Button
						__next40pxDefaultSize
						variant="secondary"
						disabled={ ( toggle === 'activate' && problem !== '' ) || isBusy || isSaving }
						accessibleWhenDisabled
						onClick={ () => setConfirm( toggle ) }
					>
						{ copyFor( toggle, title ).label }
					</Button>
				) }
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ isSaving }
					disabled={ ! isDirty || isSaving || isLocked || isBusy }
					accessibleWhenDisabled
					onClick={ () => savePost() }
				>
					{ isSaving ? __( 'Saving…', 'yaycheckout' ) : __( 'Save', 'yaycheckout' ) }
				</Button>
			</Flex>
			{ confirm && modal && (
				<Modal title={ modal.title } onRequestClose={ () => ! isBusy && setConfirm( null ) } size="small">
					<p>{ modal.body }</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button variant="tertiary" disabled={ isBusy } onClick={ () => setConfirm( null ) }>
							{ __( 'Cancel', 'yaycheckout' ) }
						</Button>
						<Button variant="primary" isBusy={ isBusy } disabled={ isBusy } onClick={ () => run( confirm ) }>
							{ isBusy ? modal.busy : modal.label }
						</Button>
					</Flex>
				</Modal>
			) }
		</>,
		slot
	);
}

registerPlugin( 'yaycheckout-layout-actions', {
	render: LayoutActions,
} );
