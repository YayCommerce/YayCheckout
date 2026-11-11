import apiFetch from '@wordpress/api-fetch';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { Button, ExternalLink, Flex, Modal, Notice, Spinner } from '@wordpress/components';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { editorData } from '../shared/editor-data';
import { errorMessage } from '../shared/error-message';
import { runLayoutTest, type BrowserFinding } from '../shared/layout-test-runner';
import { ProUpgradeNotice } from '../shared/pro-upgrade-notice';
import { isLoggedOutView, openView } from './layout-view';
import './layout-check.css';

/** Copy of a finding from Services\LayoutCheck::run(), as GET /layouts/{id}/check sends it; LayoutFinding in the SPA. */
type Finding = {
	code: string;
	severity: 'error' | 'warning' | 'info';
	message: string;
	block: { view: 'main' | 'logged_out'; name: string; index: number } | null;
	link: string;
};

function fetchFindings( postId: number ): Promise< Finding[] > {
	return apiFetch< { findings: Finding[] } >( { path: `/yaycheckout/v1/layouts/${ postId }/check` } ).then(
		( response ) => response.findings
	);
}

type FindingView = NonNullable< Finding[ 'block' ] >[ 'view' ];

/** The view the editor has open, as a finding names it. Run test is hidden on the thank-you view. */
function viewHere(): FindingView {
	return isLoggedOutView() ? 'logged_out' : 'main';
}

/** A finding about a block shows only in that block's view; one about the store shows in both. */
function inViewHere( finding: Finding ): boolean {
	return ! finding.block || finding.block.view === viewHere();
}

/**
 * The block a finding points at, as LayoutCheck numbers it: the index-th block of that name in document order,
 * parents first, which is getClientIdsWithDescendants()'s order. '' when the block isn't in this view, or when
 * the finding is about a block that's missing (its ref only names the view).
 */
function clientIdFor( finding: Finding ): string {
	const block = finding.block;

	if ( ! block || block.view !== viewHere() || finding.code === 'logged_out_without_login' ) {
		return '';
	}

	const { getClientIdsWithDescendants, getBlockName } = select( blockEditorStore );
	const ids = ( getClientIdsWithDescendants() as string[] ).filter( ( id ) => getBlockName( id ) === block.name );

	return ids[ block.index ] ?? '';
}

/** Run test in the editor header: saves unsaved edits, checks the saved layout, and lists what it found. */
export function LayoutCheckButton( { postId, disabled }: { postId: number; disabled: boolean } ) {
	const { isDirty, isLocked } = useSelect(
		( selectStore ) => ( {
			isDirty: selectStore( editorStore ).isEditedPostDirty(),
			isLocked: selectStore( editorStore ).isPostSavingLocked(),
		} ),
		[]
	);
	const { savePost } = useDispatch( editorStore );
	const { selectBlock } = useDispatch( blockEditorStore );
	const [ isOpen, setOpen ] = useState( false );
	const [ isRunning, setRunning ] = useState( false );
	const [ findings, setFindings ] = useState< Finding[] | null >( null );
	const [ error, setError ] = useState( '' );
	const [ checkedSaved, setCheckedSaved ] = useState( false );
	// Bumped per run, so the browser checks start again with the static ones.
	const [ runKey, setRunKey ] = useState( 0 );

	const run = async () => {
		setOpen( true );
		setRunning( true );
		setError( '' );
		setFindings( null );
		// The server checks the saved markup. A locked save (a live layout the publish guard refuses) can't go
		// first, so the saved version is checked and the modal says so.
		setCheckedSaved( isDirty && isLocked );

		try {
			if ( isDirty && ! isLocked ) {
				await savePost();

				if ( select( editorStore ).didPostSaveRequestFail() ) {
					setError( __( 'The layout could not be saved, so it was not tested.', 'yaycheckout' ) );
					return;
				}
			}

			setFindings( await fetchFindings( postId ) );
			setRunKey( ( key ) => key + 1 );
		} catch ( caught ) {
			setError( await errorMessage( caught, __( 'The test could not run. Try again.', 'yaycheckout' ) ) );
		} finally {
			setRunning( false );
		}
	};

	const goTo = ( clientId: string ) => {
		setOpen( false );
		selectBlock( clientId );
	};

	const shown = ( findings ?? [] ).filter( inViewHere );
	const errors = shown.filter( ( finding ) => finding.severity === 'error' );
	const warnings = shown.filter( ( finding ) => finding.severity === 'warning' );
	const elsewhere = ( findings ?? [] ).filter(
		( finding ) => ! inViewHere( finding ) && finding.severity !== 'info'
	).length;
	const otherView = viewHere() === 'main' ? 'logged-out' : 'main';

	return (
		<>
			<Button
				__next40pxDefaultSize
				variant="tertiary"
				isBusy={ isRunning }
				disabled={ disabled || isRunning }
				accessibleWhenDisabled
				onClick={ run }
			>
				{ __( 'Run test', 'yaycheckout' ) }
			</Button>
			{ isOpen && (
				<Modal
					title={ __( 'Run test', 'yaycheckout' ) }
					onRequestClose={ () => ! isRunning && setOpen( false ) }
					className="yaycheckout-layout-check"
					size="medium"
				>
					<p className="yaycheckout-layout-check__intro">
						{ __( 'Checks the saved layout against your store settings for what would stop or break a checkout.', 'yaycheckout' ) }
					</p>
					{ checkedSaved && (
						<Notice status="info" isDismissible={ false }>
							{ __( 'Checked the saved layout; unsaved edits are not included.', 'yaycheckout' ) }
						</Notice>
					) }
					{ isRunning && (
						<Flex justify="center" className="yaycheckout-layout-check__loading">
							<Spinner />
						</Flex>
					) }
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					{ findings && shown.length === 0 && (
						<Notice status="success" isDismissible={ false }>
							{ __( 'No problems found in this view.', 'yaycheckout' ) }
						</Notice>
					) }
					{ elsewhere > 0 && (
						<Notice status="info" isDismissible={ false }>
							<p>
								{ sprintf(
									otherView === 'main'
										? /* translators: %d: number of problems Run test found in the Logged in view */
										  _n(
												'%d more problem in the Logged in view.',
												'%d more problems in the Logged in view.',
												elsewhere,
												'yaycheckout'
										  )
										: /* translators: %d: number of problems Run test found in the Guest view */
										  _n(
												'%d more problem in the Guest view.',
												'%d more problems in the Guest view.',
												elsewhere,
												'yaycheckout'
										  ),
									elsewhere
								) }
							</p>
							<Button variant="link" onClick={ () => openView( otherView ) }>
								{ otherView === 'main'
									? __( 'Switch to Logged in', 'yaycheckout' )
									: __( 'Switch to Guest', 'yaycheckout' ) }
							</Button>
						</Notice>
					) }
					<FindingGroup title={ __( 'Errors', 'yaycheckout' ) } findings={ errors } status="error" onGoTo={ goTo } />
					<FindingGroup title={ __( 'Warnings', 'yaycheckout' ) } findings={ warnings } status="warning" onGoTo={ goTo } />
					{ findings &&
						( editorData.isPro ? (
							<BrowserChecks key={ runKey } postId={ postId } />
						) : (
							<section className="yaycheckout-layout-check__group">
								<h3>{ __( 'Browser checks', 'yaycheckout' ) }</h3>
								<ProUpgradeNotice
									className="yaycheckout-layout-check__locked"
									text={ __( 'Open the checkout in a real browser to catch JavaScript errors, a broken form or refresh, and steps that won\'t continue.', 'yaycheckout' ) }
								/>
							</section>
						) ) }
					<Flex justify="flex-end" gap={ 2 } className="yaycheckout-layout-check__footer">
						<Button variant="tertiary" disabled={ isRunning } onClick={ () => setOpen( false ) }>
							{ __( 'Close', 'yaycheckout' ) }
						</Button>
						<Button variant="secondary" isBusy={ isRunning } disabled={ isRunning } onClick={ run }>
							{ __( 'Run again', 'yaycheckout' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</>
	);
}

/** The test-run route's answer: Pro\Controllers\LayoutTestRestController::create_test_run(). */
type TestRunWire = { url: string; expires_at: string; refusal_message: string };

/**
 * Pro: opens the saved layout's checkout in an off-screen iframe and lists what broke there. Starts on mount,
 * so a new key runs it again; closing the modal unmounts it, which aborts the run and removes the iframe.
 */
function BrowserChecks( { postId }: { postId: number } ) {
	const [ progress, setProgress ] = useState( '' );
	const [ result, setResult ] = useState< { findings: BrowserFinding[]; timedOut: boolean } | null >( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		const controller = new AbortController();
		let live = true;

		( async () => {
			try {
				const wire = await apiFetch< TestRunWire >( {
					path: `/yaycheckout/v1/layouts/${ postId }/test-run`,
					method: 'POST',
					signal: controller.signal,
				} );
				const done = await runLayoutTest(
					{ url: wire.url, expiresAt: wire.expires_at, refusalMessage: wire.refusal_message },
					{ phases: [ 'render' ], onProgress: ( label ) => live && setProgress( label ), signal: controller.signal }
				);

				if ( live ) {
					setResult( done );
				}
			} catch ( caught ) {
				if ( live && ! controller.signal.aborted ) {
					setError( await errorMessage( caught, __( 'The browser checks could not run. Try again.', 'yaycheckout' ) ) );
				}
			}
		} )();

		return () => {
			live = false;
			controller.abort();
		};
	}, [ postId ] );

	return (
		<section className="yaycheckout-layout-check__group">
			<h3>{ __( 'Browser checks', 'yaycheckout' ) }</h3>
			{ ! result && ! error && (
				<Flex justify="flex-start" gap={ 2 } className="yaycheckout-layout-check__progress">
					<Spinner />
					<span>{ progress }</span>
				</Flex>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ result?.findings.map( ( finding ) => (
				<Notice key={ finding.code } status="error" isDismissible={ false }>
					<p>{ finding.message }</p>
				</Notice>
			) ) }
			{ result?.timedOut && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'The page took too long to load, so some browser checks didn\'t run.', 'yaycheckout' ) }
				</Notice>
			) }
			{ result && result.findings.length === 0 && ! result.timedOut && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'No problems found in the browser.', 'yaycheckout' ) }
				</Notice>
			) }
		</section>
	);
}

/** The settings link's text, worded like the Payment and Shipping methods blocks' setup notices. */
function linkText( code: string ): string {
	if ( code === 'payment_needs_setup' ) {
		return __( 'Payments', 'yaycheckout' );
	}

	if ( code === 'shipping_needs_setup' ) {
		return __( 'Shipping settings', 'yaycheckout' );
	}

	return __( 'Open settings', 'yaycheckout' );
}

function FindingGroup( {
	title,
	findings,
	status,
	onGoTo,
}: {
	title: string;
	findings: Finding[];
	status: 'error' | 'warning';
	onGoTo: ( clientId: string ) => void;
} ) {
	if ( findings.length === 0 ) {
		return null;
	}

	return (
		<section className="yaycheckout-layout-check__group">
			<h3>{ `${ title } (${ findings.length })` }</h3>
			{ findings.map( ( finding, i ) => {
				const clientId = clientIdFor( finding );

				return (
					<Notice key={ `${ finding.code }-${ i }` } status={ status } isDismissible={ false }>
						<p>{ finding.message }</p>
						<Flex justify="flex-start" gap={ 3 } className="yaycheckout-layout-check__actions">
							{ clientId && (
								<Button variant="link" onClick={ () => onGoTo( clientId ) }>
									{ __( 'Go to block', 'yaycheckout' ) }
								</Button>
							) }
							{ finding.link && (
								<ExternalLink href={ finding.link }>{ linkText( finding.code ) }</ExternalLink>
							) }
						</Flex>
					</Notice>
				);
			} ) }
		</section>
	);
}

/**
 * How many problems Run test finds in the saved layout, for the Activate confirmation; null while unknown.
 * It never blocks Activate: LayoutMarkupReader::publish_error() does, on the server.
 */
export function useLayoutCheckCount( postId: number, enabled: boolean ): number | null {
	const [ count, setCount ] = useState< number | null >( null );

	useEffect( () => {
		if ( ! enabled || ! postId ) {
			setCount( null );
			return;
		}

		let live = true;
		fetchFindings( postId )
			.then( ( found ) => live && setCount( found.filter( ( finding ) => finding.severity !== 'info' ).length ) )
			.catch( () => live && setCount( null ) );

		return () => {
			live = false;
		};
	}, [ postId, enabled ] );

	return count;
}

/** The Activate confirmation's line about Run test's findings; nothing when there are none. */
export function LayoutCheckSummary( { count }: { count: number | null } ) {
	if ( ! count ) {
		return null;
	}

	return (
		<Notice status="warning" isDismissible={ false }>
			{ sprintf(
				/* translators: %d: number of problems Run test found */
				_n(
					'Run test found %d problem in this layout. Use Run test to see it.',
					'Run test found %d problems in this layout. Use Run test to see them.',
					count,
					'yaycheckout'
				),
				count
			) }
		</Notice>
	);
}
