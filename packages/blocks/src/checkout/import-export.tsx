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
import './import-export.css';

/** Locks Import / Export outside Pro. Cosmetic only: it hides UI, it guards no data. */
const IS_PRO = editorData.isPro;

/** The file is read inside the editor tab, so a huge one would freeze it; real exports are a few KB. */
const MAX_IMPORT_BYTES = 5 * 1024 * 1024;

/** Server-reviewed layout from the file (LayoutImportReview), plus what the picker knows about the file. */
type ImportReview = {
	fileName: string;
	fileSize: number;
	title: string;
	content: string;
	blocks: number;
	/** The file carries assignment rules; this import leaves them behind. */
	has_rules: boolean;
};

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

/** The file's layout as LayoutImportReview accepts it; rejects with the server's reason. Only JSON is checked here, since it cannot be sent otherwise. */
async function reviewFile( text: string ): Promise< Pick< ImportReview, 'title' | 'content' | 'blocks' | 'has_rules' > > {
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
		<Modal
			title={ __( 'Import layout', 'yaycheckout' ) }
			size="medium"
			className="yaycheckout-import-export"
			onRequestClose={ close }
		>
			{ importError && (
				<Notice status="error" isDismissible={ false }>
					{ importError }
				</Notice>
			) }
			<div className="yaycheckout-import-export__drop">
				<DropZone onFilesDrop={ handleFiles } />
				<h2 className="yaycheckout-import-export__drop-title">{ __( 'Drop files to upload', 'yaycheckout' ) }</h2>
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
					<p className="yaycheckout-import-export__file">
						<strong>{ importReview.fileName }</strong>
						<br />
						{ formatFileSize( importReview.fileSize ) }
					</p>
				) }
			</div>
			{ importReview && (
				<Flex direction="column" align="stretch" gap={ 4 }>
					<Flex justify="flex-start" gap={ 3 }>
						<span className="yaycheckout-import-export__ready-icon">
							<Dashicon icon="yes" />
						</span>
						<h3 className="yaycheckout-import-export__heading">
							{ sprintf(
								/* translators: %s: layout title stored in the export file */
								__( 'Ready to import the blocks of "%s"', 'yaycheckout' ),
								importReview.title
							) }
						</h3>
					</Flex>
					<Flex justify="flex-start" gap={ 8 } className="yaycheckout-import-export__summary">
						<div>
							<span className="yaycheckout-import-export__summary-label">{ __( 'From layout', 'yaycheckout' ) }</span>
							<strong>{ importReview.title }</strong>
						</div>
						<div>
							<span className="yaycheckout-import-export__summary-label">{ __( 'Checkout blocks', 'yaycheckout' ) }</span>
							<strong>{ importReview.blocks }</strong>
						</div>
					</Flex>
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Import replaces all the content of this layout with the file’s content. The layout title stays the same. Nothing is saved until you click Save, and you can undo it.',
							'yaycheckout'
						) }
					</Notice>
					{ importReview.has_rules && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'The file also carries assignment rules. They are not imported here: this layout keeps its own. To bring them over, use Tools → Import, which creates a new draft.',
								'yaycheckout'
							) }
						</Notice>
					) }
				</Flex>
			) }
			<Flex justify="flex-end" gap={ 2 } className="yaycheckout-import-export__actions">
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
			<Flex direction="column" align="stretch" gap={ 4 } className="yaycheckout-import-export">
				{ ! IS_PRO && (
					<p className="yaycheckout-import-export__help">
						<span className="yaycheckout-import-export__pro-tag">
							{ __( 'Pro', 'yaycheckout' ) }
						</span>
						{ __( 'Upgrade to PRO to unlock this feature now.', 'yaycheckout' ) }{ ' ' }
						<ExternalLink href={ editorData.upgradeUrl }>{ __( 'Upgrade', 'yaycheckout' ) }</ExternalLink>
					</p>
				) }
				<Flex direction="column" align="stretch" gap={ 2 }>
					<h3 className="yaycheckout-import-export__heading">
						{ __( 'Import', 'yaycheckout' ) }
					</h3>
					<p className="yaycheckout-import-export__help">
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
					<h3 className="yaycheckout-import-export__heading">
						{ __( 'Export', 'yaycheckout' ) }
					</h3>
					<p className="yaycheckout-import-export__help">
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
						<p className="yaycheckout-import-export__help">
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
