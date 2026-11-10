import apiFetch from '@wordpress/api-fetch';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { Button, ExternalLink, Flex, Modal, Notice, Spinner } from '@wordpress/components';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { errorMessage } from '../shared/error-message';
import { isMainView } from './layout-view';
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

/**
 * The block a finding points at, as LayoutCheck numbers it: the index-th block of that name in document order,
 * parents first, which is getClientIdsWithDescendants()'s order. '' when the block isn't in this view.
 */
function clientIdFor( block: NonNullable< Finding[ 'block' ] > ): string {
	if ( block.view !== 'main' || ! isMainView() ) {
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

	const errors = ( findings ?? [] ).filter( ( finding ) => finding.severity === 'error' );
	const warnings = ( findings ?? [] ).filter( ( finding ) => finding.severity === 'warning' );

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
					{ findings && findings.length === 0 && (
						<Notice status="success" isDismissible={ false }>
							{ __( 'No problems found.', 'yaycheckout' ) }
						</Notice>
					) }
					<FindingGroup title={ __( 'Errors', 'yaycheckout' ) } findings={ errors } status="error" onGoTo={ goTo } />
					<FindingGroup title={ __( 'Warnings', 'yaycheckout' ) } findings={ warnings } status="warning" onGoTo={ goTo } />
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
				const clientId = finding.block ? clientIdFor( finding.block ) : '';

				return (
					<Notice key={ `${ finding.code }-${ i }` } status={ status } isDismissible={ false }>
						<p>{ finding.message }</p>
						<Flex justify="flex-start" gap={ 3 } className="yaycheckout-layout-check__actions">
							{ clientId && (
								<Button variant="link" onClick={ () => onGoTo( clientId ) }>
									{ __( 'Go to block', 'yaycheckout' ) }
								</Button>
							) }
							{ finding.block?.view === 'logged_out' && <span>{ __( 'In the Guest view', 'yaycheckout' ) }</span> }
							{ finding.link && (
								<ExternalLink href={ finding.link }>{ __( 'Open settings', 'yaycheckout' ) }</ExternalLink>
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
