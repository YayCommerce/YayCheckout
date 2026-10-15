import { Button, Flex, Modal, Spinner } from '@wordpress/components';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { createPortal, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { editorData } from '../shared/editor-data';
import './layout-view.css';

/** PreviewRequest::VIEW_ARG: a preview link with `=guest` shows the layout's Guest view. */
export const GUEST_VIEW_ARG = 'yaycheckout_view';

/** Whether this editor is a layout's guest view rather than the layout itself. */
export function useIsGuestView(): boolean {
	return useSelect(
		( selectStore ) => selectStore( editorStore ).getCurrentPostId() === editorData.guestView?.guestId,
		[]
	);
}

/**
 * Checkout form | Guest view. The two are separate posts, each in core's editor, so switching opens the other one
 * in this frame; unsaved changes are saved first, or the switch is cancelled.
 */
export function ViewSwitch() {
	const isGuest = useIsGuestView();
	const isDirty = useSelect( ( selectStore ) => selectStore( editorStore ).isEditedPostDirty(), [] );
	const { savePost } = useDispatch( editorStore );
	const { createInfoNotice } = useDispatch( noticesStore );
	const [ asking, setAsking ] = useState( false );
	const [ isSaving, setSaving ] = useState( false );
	// The other editor takes a few seconds to load; until it replaces this page, the switch shows it is on its way.
	const [ isSwitching, setSwitching ] = useState( false );
	const posts = editorData.guestView;

	useEffect( () => {
		if ( isGuest ) {
			createInfoNotice(
				__(
					'Shown to guests only when guest checkout and account creation during checkout are both off in WooCommerce.',
					'yaycheckout'
				),
				{ id: 'yaycheckout-guest-view' }
			);
		}
	}, [ isGuest ] );

	if ( ! posts ) {
		return null;
	}

	const open = () => {
		setAsking( false );
		setSwitching( true );
		// replace(), not href: in the manager's iframe each entry joins the window's history, and closing the editor
		// goes back one entry (LayoutsPage's navigate( -1 )), so every switch would cost the merchant one more click.
		window.location.replace( isGuest ? posts.layoutUrl : posts.guestUrl );
	};

	const saveAndOpen = async () => {
		setSaving( true );
		await savePost();

		if ( select( editorStore ).didPostSaveRequestFail() ) {
			setSaving( false );
			setAsking( false );
			return;
		}

		open();
	};

	const choose = ( guest: boolean ) => {
		if ( guest === isGuest || isSwitching ) {
			return;
		}

		if ( isDirty ) {
			setAsking( true );
		} else {
			open();
		}
	};

	return (
		<>
			<Flex gap={ 0 } expanded={ false } role="group" aria-label={ __( 'View', 'yaycheckout' ) }>
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					isPressed={ ! isGuest }
					isBusy={ isSwitching && isGuest }
					disabled={ isSwitching }
					accessibleWhenDisabled
					onClick={ () => choose( false ) }
				>
					{ __( 'Checkout form', 'yaycheckout' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					isPressed={ isGuest }
					isBusy={ isSwitching && ! isGuest }
					disabled={ isSwitching }
					accessibleWhenDisabled
					onClick={ () => choose( true ) }
				>
					{ __( 'Guest view', 'yaycheckout' ) }
				</Button>
			</Flex>
			{ isSwitching &&
				createPortal(
					<div className="yaycheckout-view-switching" role="status">
						<Spinner />
						<span>
							{ isGuest
								? __( 'Opening the checkout form…', 'yaycheckout' )
								: __( 'Opening the guest view…', 'yaycheckout' ) }
						</span>
					</div>,
					document.body
				) }
			{ asking && (
				<Modal
					title={ __( 'Save your changes first?', 'yaycheckout' ) }
					onRequestClose={ () => ! isSaving && setAsking( false ) }
					size="small"
				>
					<p>
						{ isGuest
							? __( 'The guest view has unsaved changes. Save them before opening the checkout form.', 'yaycheckout' )
							: __( 'The checkout form has unsaved changes. Save them before opening the guest view.', 'yaycheckout' ) }
					</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button variant="tertiary" disabled={ isSaving } onClick={ () => setAsking( false ) }>
							{ __( 'Cancel', 'yaycheckout' ) }
						</Button>
						<Button variant="primary" isBusy={ isSaving } disabled={ isSaving } onClick={ saveAndOpen }>
							{ isSaving ? __( 'Saving…', 'yaycheckout' ) : __( 'Save and switch', 'yaycheckout' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</>
	);
}
