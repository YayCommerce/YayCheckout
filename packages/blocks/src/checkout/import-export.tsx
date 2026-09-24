import apiFetch from '@wordpress/api-fetch';
import { parse } from '@wordpress/blocks';
import { Button, Dashicon, DropZone, ExternalLink, Flex, FormFileUpload, Modal, Notice } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';
import { editorData } from '../shared/editor-data';
import { filenameFromHeaders, isJsonFileName, saveFile } from '../shared/export-file';

/** Locks Import / Export outside Pro. Cosmetic only: it hides UI, it guards no data. */
const IS_PRO = editorData.isPro;

/** The file is read inside the editor tab, so a huge one would freeze it; real exports are a few KB. */
const MAX_IMPORT_BYTES = 5 * 1024 * 1024;

/** Server-reviewed layout from the file (LayoutImportReview), plus what the picker knows about the file. */
type ImportReview = { fileName: string; fileSize: number; title: string; content: string; blocks: number };

/** A message for an apiFetch rejection: the raw Response when `parse: false`, else `{ message }`. Never empty. */
async function errorMessage( error: unknown, fallback: string ): Promise< string > {
	let message: string | undefined;
	if ( error instanceof Response ) {
		message = ( await error.json().catch( () => null ) )?.message;
	} else {
		message = ( error as { message?: string } )?.message;
	}
	return message || fallback;
}

/** Reads as bytes below 1 KB, so a small layout does not round up to a meaningless "1 KB". */
function formatFileSize( bytes: number ): string {
	if ( bytes < 1024 ) {
		/* translators: %d: file size in bytes */
		return sprintf( __( '%d B', 'yaycheckout' ), bytes );
	}

	/* translators: %s: file size in kilobytes */
	return sprintf( __( '%s KB', 'yaycheckout' ), ( bytes / 1024 ).toFixed( 1 ) );
}

const SUMMARY_LABEL_STYLE = { display: 'block', color: '#757575', marginBottom: 4 } as const;

/** The file's layout as LayoutImportReview accepts it; rejects with the server's reason. Only JSON is checked here, since it cannot be sent otherwise. */
async function reviewFile( text: string ): Promise< Pick< ImportReview, 'title' | 'content' | 'blocks' > > {
	let file: unknown;
	try {
		file = JSON.parse( text );
	} catch {
		throw new Error( __( 'This file is not valid JSON.', 'yaycheckout' ) );
	}

	return apiFetch( { path: '/yaycheckout/v1/layouts/import/review', method: 'POST', data: { file } } );
}

/** Picks an export file, has the server review it, and hands the reviewed markup to `onImport`. */
function ImportModal( { onClose, onImport }: { onClose: () => void; onImport: ( content: string ) => void } ) {
	const [ importReview, setImportReview ] = useState< ImportReview | null >( null );
	const [ importError, setImportError ] = useState( '' );
	const latestPick = useRef( 0 );

	const handleFiles = async ( files: FileList | File[] ) => {
		const file = files[ 0 ];
		if ( ! file ) {
			return;
		}
		// Only the latest pick may set state: a slower review of an earlier file would otherwise overwrite it.
		const pick = ++latestPick.current;
		setImportReview( null );
		if ( ! isJsonFileName( file.name ) ) {
			setImportError( __( 'Only .json files can be imported.', 'yaycheckout' ) );
			return;
		}
		if ( file.size > MAX_IMPORT_BYTES ) {
			setImportError( __( 'This file is too large to be a layout export.', 'yaycheckout' ) );
			return;
		}
		try {
			const review = await reviewFile( await file.text() );
			if ( pick === latestPick.current ) {
				setImportReview( { fileName: file.name, fileSize: file.size, ...review } );
				setImportError( '' );
			}
		} catch ( error ) {
			if ( pick === latestPick.current ) {
				setImportError( await errorMessage( error, __( 'The file could not be read.', 'yaycheckout' ) ) );
			}
		}
	};

	const close = () => {
		latestPick.current++;
		onClose();
	};

	return (
		<Modal title={ __( 'Import layout', 'yaycheckout' ) } size="medium" onRequestClose={ close }>
			{ importError && (
				<Notice status="error" isDismissible={ false }>
					{ importError }
				</Notice>
			) }
			<div style={ { position: 'relative', padding: '32px 48px', textAlign: 'center' } }>
				<DropZone onFilesDrop={ handleFiles } />
				<h2 style={ { fontWeight: 400 } }>{ __( 'Drop files to upload', 'yaycheckout' ) }</h2>
				<p>{ __( 'or', 'yaycheckout' ) }</p>
				<FormFileUpload
					__next40pxDefaultSize
					variant="secondary"
					accept=".json,application/json"
					onChange={ ( event ) => {
						void handleFiles( event.currentTarget.files ?? [] );
						// Cleared so choosing the same file again still fires change.
						event.currentTarget.value = '';
					} }
				>
					{ __( 'Select Files', 'yaycheckout' ) }
				</FormFileUpload>
				{ importReview && (
					<p style={ { marginTop: 24 } }>
						<strong>{ importReview.fileName }</strong>
						<br />
						{ formatFileSize( importReview.fileSize ) }
					</p>
				) }
			</div>
			{ importReview && (
				<Flex direction="column" align="stretch" gap={ 4 }>
					<Flex justify="flex-start" gap={ 3 }>
						<span
							style={ {
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								flexShrink: 0,
								width: 32,
								height: 32,
								borderRadius: '50%',
								background: 'rgba(0, 163, 42, 0.1)',
								color: '#00a32a',
							} }
						>
							<Dashicon icon="yes" />
						</span>
						<h3 style={ { margin: 0 } }>
							{ sprintf(
								/* translators: %s: layout title stored in the export file */
								__( 'Ready to import the blocks of "%s"', 'yaycheckout' ),
								importReview.title
							) }
						</h3>
					</Flex>
					<Flex
						justify="flex-start"
						gap={ 8 }
						style={ { padding: '10px 16px', border: '1px solid #e0e0e0', borderRadius: 4, background: '#f9f9f9' } }
					>
						<div>
							<span style={ SUMMARY_LABEL_STYLE }>{ __( 'From layout', 'yaycheckout' ) }</span>
							<strong>{ importReview.title }</strong>
						</div>
						<div>
							<span style={ SUMMARY_LABEL_STYLE }>{ __( 'Checkout blocks', 'yaycheckout' ) }</span>
							<strong>{ importReview.blocks }</strong>
						</div>
					</Flex>
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Import replaces all the content of this layout with the file’s content. The layout title stays the same. Nothing is saved until you click Save, and you can undo it.',
							'yaycheckout'
						) }
					</Notice>
				</Flex>
			) }
			<Flex justify="flex-end" gap={ 2 } style={ { marginTop: 24 } }>
				<Button __next40pxDefaultSize variant="tertiary" onClick={ close }>
					{ __( 'Cancel', 'yaycheckout' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="primary"
					disabled={ ! importReview }
					accessibleWhenDisabled
					onClick={ () => importReview && onImport( importReview.content ) }
				>
					{ __( 'Import', 'yaycheckout' ) }
				</Button>
			</Flex>
		</Modal>
	);
}

/** Import / Export panel in the layout's document sidebar tab. */
function LayoutImportExport() {
	const [ isImportOpen, setImportOpen ] = useState( false );
	const [ isExporting, setExporting ] = useState( false );
	const { postType, postId, title, hasUnsavedChanges } = useSelect(
		( select ) => ( {
			postType: select( editorStore ).getCurrentPostType(),
			postId: select( editorStore ).getCurrentPostId(),
			title: select( editorStore ).getEditedPostAttribute( 'title' ),
			hasUnsavedChanges: select( editorStore ).isEditedPostDirty() || select( editorStore ).isEditedPostNew(),
		} ),
		[]
	);
	const { resetEditorBlocks } = useDispatch( editorStore );
	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	if ( postType !== editorData.postType ) {
		return null;
	}

	// Unsaved and undoable on purpose: saving stays the merchant's call.
	const applyImport = ( content: string ) => {
		resetEditorBlocks( parse( content ) );
		createSuccessNotice(
			__( 'Layout imported. Review it, then save when ready.', 'yaycheckout' ),
			{ type: 'snackbar' }
		);
		setImportOpen( false );
	};

	const exportLayout = async () => {
		setExporting( true );
		try {
			const response = ( await apiFetch( {
				path: `/yaycheckout/v1/layouts/${ postId }/export`,
				parse: false,
			} ) ) as Response;
			saveFile( filenameFromHeaders( response.headers ), JSON.stringify( await response.json(), null, 2 ) );
			createSuccessNotice(
				sprintf(
					/* translators: %s: layout title */
					__( '"%s" exported.', 'yaycheckout' ),
					title
				),
				{ type: 'snackbar' }
			);
		} catch ( error ) {
			createErrorNotice( await errorMessage( error, __( 'The layout could not be exported.', 'yaycheckout' ) ), {
				type: 'snackbar',
			} );
		} finally {
			setExporting( false );
		}
	};

	return (
		<PluginDocumentSettingPanel
			name="yaycheckout-layout-import-export"
			title={ __( 'Import / Export', 'yaycheckout' ) }
		>
			<Flex direction="column" align="stretch" gap={ 4 }>
				{ ! IS_PRO && (
					<p style={ { margin: 0, color: '#757575' } }>
						<span
							style={ {
								marginRight: 6,
								padding: '1px 6px',
								borderRadius: 2,
								background: '#f9bd09',
								color: '#fff',
								fontSize: 11,
								fontWeight: 600,
							} }
						>
							{ __( 'Pro', 'yaycheckout' ) }
						</span>
						{ __( 'Upgrade to PRO to unlock this feature now.', 'yaycheckout' ) }{ ' ' }
						<ExternalLink href={ editorData.upgradeUrl }>{ __( 'Upgrade', 'yaycheckout' ) }</ExternalLink>
					</p>
				) }
				<Flex direction="column" align="stretch" gap={ 2 }>
					<h3 style={ { margin: 0 } }>
						{ __( 'Import', 'yaycheckout' ) }
					</h3>
					<p style={ { margin: 0, color: '#757575' } }>
						{ __( 'Replaces this layout’s blocks. Nothing is saved until you save.', 'yaycheckout' ) }
					</p>
					<Button
						__next40pxDefaultSize
						className="editor-post-featured-image__toggle"
						disabled={ ! IS_PRO }
						accessibleWhenDisabled
						onClick={ () => setImportOpen( true ) }
					>
						{ __( 'Import', 'yaycheckout' ) }
					</Button>
				</Flex>
				<Flex direction="column" align="stretch" gap={ 2 }>
					<h3 style={ { margin: 0 } }>
						{ __( 'Export', 'yaycheckout' ) }
					</h3>
					<p style={ { margin: 0, color: '#757575' } }>
						{ __( 'Downloads one layout with its tokens and slots.', 'yaycheckout' ) }
					</p>
					<Button
						__next40pxDefaultSize
						className="editor-post-featured-image__toggle"
						isBusy={ isExporting }
						// The file holds the saved layout, so unsaved edits would silently be missing from it.
						disabled={ ! IS_PRO || isExporting || hasUnsavedChanges }
						accessibleWhenDisabled
						onClick={ exportLayout }
					>
						{ __( 'Export', 'yaycheckout' ) }
					</Button>
					{ IS_PRO && hasUnsavedChanges && (
						<p style={ { margin: 0, color: '#757575' } }>
							{ __( 'Save your changes to include them in the export.', 'yaycheckout' ) }
						</p>
					) }
				</Flex>
			</Flex>
			{ isImportOpen && <ImportModal onClose={ () => setImportOpen( false ) } onImport={ applyImport } /> }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'yaycheckout-layout-import-export', {
	render: LayoutImportExport,
} );
