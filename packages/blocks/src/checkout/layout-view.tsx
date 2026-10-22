import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Flex,
	Modal,
	Spinner,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { editorData, type EditorView } from '../shared/editor-data';
import { errorMessage } from '../shared/error-message';

type View = EditorView[ 'current' ];


/** True while the editor edits the logged-out view: LayoutEditorEmbed serves that markup as the post content. */
export function isLoggedOutView(): boolean {
	return editorData.view.current === 'logged-out';
}

/** True while the editor edits the checkout itself (post_content), not a view kept in meta. */
export function isMainView(): boolean {
	return editorData.view.current === 'main';
}

const LOADING_MESSAGES: Record< View, string > = {
	main: __( 'Loading the checkout…', 'yaycheckout' ),
	'logged-out': __( 'Loading the logged-out view…', 'yaycheckout' ),
	'thank-you': __( 'Loading the thank-you page…', 'yaycheckout' ),
};

/**
 * Checkout / Logged out / Thank you. Logged out shows while WooCommerce makes guests log in, Thank you
 * while the layout has a thank-you page, and each while it is the open view, so the merchant can get back.
 * A layout without a thank-you page gets an Add thank-you page button instead, which writes its template's
 * (POST /layouts/{id}/thank-you, LayoutRepository::add_thank_you()) and opens it.
 * Each view is its own page load; unsaved edits are saved first, or dropped when the merchant chooses,
 * never carried across.
 */
export default function LayoutViewSwitch() {
	const { isLocked, isSaving } = useSelect(
		( selectStore ) => ( {
			isLocked: selectStore( editorStore ).isPostSavingLocked(),
			isSaving: selectStore( editorStore ).isSavingPost(),
		} ),
		[]
	);
	const { savePost } = useDispatch( editorStore );
	const { clearEntityRecordEdits } = useDispatch( coreStore );
	const { createErrorNotice } = useDispatch( noticesStore );
	// The view picked but not yet open: asking to save first, or loading it.
	const [ pending, setPending ] = useState< { view: View; step: 'ask' | 'load' } | null >( null );
	const { current, loginRequired, hasThankYou } = editorData.view;
	const showLoggedOut = loginRequired || current === 'logged-out';
	const showThankYou = hasThankYou || current === 'thank-you';
	// With no view besides Checkout, a toggle of one option would show; the Add button stands alone then.
	const showToggle = showLoggedOut || showThankYou;

	const go = async ( view: View ) => {
		setPending( { view, step: 'load' } );

		if ( view === 'thank-you' && ! hasThankYou ) {
			try {
				await apiFetch( {
					path: `/yaycheckout/v1/layouts/${ select( editorStore ).getCurrentPostId() }/thank-you`,
					method: 'POST',
				} );
			} catch ( error ) {
				setPending( null );
				createErrorNotice(
					await errorMessage( error, __( 'The thank-you page could not be added.', 'yaycheckout' ) ),
					{ type: 'snackbar' }
				);
				return;
			}
		}

		// replace(), not assign(): the iframe's history is the manager's, and a pushed entry makes its
		// close button (navigate(-1) in LayoutsPage.tsx) step back to the previous view instead of closing.
		window.location.replace( editorData.view.urls[ view ] );
	};

	// Dropping the edits first keeps core's "unsaved changes" prompt from stopping the reload.
	const discardAndGo = ( view: View ) => {
		const editor = select( editorStore );
		clearEntityRecordEdits( 'postType', editor.getCurrentPostType(), editor.getCurrentPostId() );
		go( view );
	};

	const saveAndGo = async ( view: View ) => {
		await savePost();

		const editor = select( editorStore );

		// A failed save leaves core's error notice up; stay so nothing is lost.
		if ( editor.didPostSaveRequestSucceed() && ! editor.isEditedPostDirty() ) {
			go( view );
		} else {
			setPending( null );
		}
	};

	const pick = ( view: View ) => {
		if ( view === current || pending?.step === 'load' ) {
			return;
		}

		// Read now, not from the last render, so an edit made just before the click still asks.
		if ( select( editorStore ).isEditedPostDirty() ) {
			setPending( { view, step: 'ask' } );
		} else {
			go( view );
		}
	};

	return (
		<>
			{ showToggle && (
				<ToggleGroupControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					hideLabelFromVision
					isBlock={ false }
					label={ __( 'Layout view', 'yaycheckout' ) }
					// The pending pick too, so Cancel moves the control back: it keeps its own selection while value stays put.
					value={ pending?.view ?? current }
					onChange={ ( value ) => pick( value as View ) }
				>
					<ToggleGroupControlOption value="main" label={ __( 'Checkout', 'yaycheckout' ) } />
					{ showLoggedOut && (
						<ToggleGroupControlOption value="logged-out" label={ __( 'Logged out', 'yaycheckout' ) } />
					) }
					{ showThankYou && (
						<ToggleGroupControlOption value="thank-you" label={ __( 'Thank you', 'yaycheckout' ) } />
					) }
				</ToggleGroupControl>
			) }
			{ ! showThankYou && (
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					disabled={ pending?.step === 'load' }
					onClick={ () => pick( 'thank-you' ) }
				>
					{ __( 'Add thank-you page', 'yaycheckout' ) }
				</Button>
			) }
			{ pending?.step === 'ask' && (
				<Modal
					title={ __( 'Save changes before switching?', 'yaycheckout' ) }
					onRequestClose={ () => ! isSaving && setPending( null ) }
					size="medium"
				>
					<p>{ __( 'Each view is saved on its own. Save this view to keep your edits, or switch without saving to drop them.', 'yaycheckout' ) }</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button variant="tertiary" disabled={ isSaving } onClick={ () => setPending( null ) }>
							{ __( 'Cancel', 'yaycheckout' ) }
						</Button>
						<Button variant="secondary" isDestructive disabled={ isSaving } onClick={ () => discardAndGo( pending.view ) }>
							{ __( 'Switch without saving', 'yaycheckout' ) }
						</Button>
						<Button
							variant="primary"
							isBusy={ isSaving }
							disabled={ isSaving || isLocked }
							accessibleWhenDisabled
							onClick={ () => saveAndGo( pending.view ) }
						>
							{ isSaving ? __( 'Saving…', 'yaycheckout' ) : __( 'Save and switch', 'yaycheckout' ) }
						</Button>
					</Flex>
				</Modal>
			) }
			{ pending?.step === 'load' && (
				<Modal
					title={ __( 'Switching view', 'yaycheckout' ) }
					__experimentalHideHeader
					isDismissible={ false }
					shouldCloseOnClickOutside={ false }
					shouldCloseOnEsc={ false }
					onRequestClose={ () => {} }
					size="small"
				>
					<Flex justify="center" gap={ 3 }>
						<Spinner />
						<span>
							{ pending.view === 'thank-you' && ! hasThankYou
								? __( 'Adding the thank-you page…', 'yaycheckout' )
								: LOADING_MESSAGES[ pending.view ] }
						</span>
					</Flex>
				</Modal>
			) }
		</>
	);
}
