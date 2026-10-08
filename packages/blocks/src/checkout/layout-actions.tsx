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
import { useLayoutProblem } from './publish-guard';

/** LayoutEditorCanvas hides core's status controls only under this class, so they stay if the buttons can't mount. */
const MOUNTED_CLASS = 'yaycheckout-has-layout-actions';

/** The header's settings area: editor-header from WordPress 6.6, edit-post-header in 6.5. */
const HEADER_SELECTOR = '.editor-header__settings, .edit-post-header__settings';

type Confirm = 'activate' | 'deactivate' | null;

/** The yaycheckout/v1 layout fields this file reads. */
type LayoutWire = { is_active: boolean };

function errorMessage( error: unknown, fallback: string ): string {
	return ( error as { message?: string } )?.message || fallback;
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
 * The layout editor's own Save, Activate and Deactivate, in place of core's Publish controls:
 * Save keeps the status, and the other two call the same routes as the layouts list, so
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
	const [ isActive, setActive ] = useState< boolean | null >( null );
	const [ confirm, setConfirm ] = useState< Confirm >( null );
	const [ isBusy, setBusy ] = useState( false );
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

	const run = async ( action: 'activate' | 'deactivate' ) => {
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
			createSuccessNotice(
				action === 'activate'
					? __( 'Layout activated.', 'yaycheckout' )
					: __( 'Layout deactivated.', 'yaycheckout' ),
				{ type: 'snackbar' }
			);
		} catch ( error ) {
			createErrorNotice(
				errorMessage(
					error,
					action === 'activate'
						? __( 'The layout could not be activated.', 'yaycheckout' )
						: __( 'The layout could not be deactivated.', 'yaycheckout' )
				),
				{ type: 'snackbar' }
			);
		} finally {
			setBusy( false );
			setConfirm( null );
		}
	};

	return createPortal(
		<>
			<Flex gap={ 2 } expanded={ false }>
				{ isActive === false && (
					<Button
						__next40pxDefaultSize
						variant="secondary"
						disabled={ problem !== '' || isBusy || isSaving }
						accessibleWhenDisabled
						onClick={ () => setConfirm( 'activate' ) }
					>
						{ __( 'Activate', 'yaycheckout' ) }
					</Button>
				) }
				{ isActive === true && (
					<Button
						__next40pxDefaultSize
						variant="secondary"
						disabled={ isBusy || isSaving }
						accessibleWhenDisabled
						onClick={ () => setConfirm( 'deactivate' ) }
					>
						{ __( 'Deactivate', 'yaycheckout' ) }
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
			{ confirm === 'activate' && (
				<Modal
					/* translators: %s: layout title */
					title={ sprintf( __( 'Make "%s" your live checkout?', 'yaycheckout' ), title ) }
					onRequestClose={ () => ! isBusy && setConfirm( null ) }
					size="small"
				>
					<p>{ __( 'Shoppers will start seeing this layout as soon as you confirm.', 'yaycheckout' ) }</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button variant="tertiary" disabled={ isBusy } onClick={ () => setConfirm( null ) }>
							{ __( 'Cancel', 'yaycheckout' ) }
						</Button>
						<Button variant="primary" isBusy={ isBusy } disabled={ isBusy } onClick={ () => run( 'activate' ) }>
							{ isBusy ? __( 'Activating…', 'yaycheckout' ) : __( 'Activate', 'yaycheckout' ) }
						</Button>
					</Flex>
				</Modal>
			) }
			{ confirm === 'deactivate' && (
				<Modal
					/* translators: %s: layout title */
					title={ sprintf( __( 'Deactivate "%s"?', 'yaycheckout' ), title ) }
					onRequestClose={ () => ! isBusy && setConfirm( null ) }
					size="small"
				>
					<p>
						{ __(
							'Shoppers will stop seeing this layout. It becomes inactive, and you can activate it again at any time.',
							'yaycheckout'
						) }
					</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button variant="tertiary" disabled={ isBusy } onClick={ () => setConfirm( null ) }>
							{ __( 'Cancel', 'yaycheckout' ) }
						</Button>
						<Button variant="primary" isBusy={ isBusy } disabled={ isBusy } onClick={ () => run( 'deactivate' ) }>
							{ isBusy ? __( 'Deactivating…', 'yaycheckout' ) : __( 'Deactivate', 'yaycheckout' ) }
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
