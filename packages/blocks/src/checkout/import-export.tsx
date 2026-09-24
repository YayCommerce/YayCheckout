import apiFetch from '@wordpress/api-fetch';
import { parse } from '@wordpress/blocks';
import { Button, Dashicon, DropZone, ExternalLink, Flex, FormFileUpload, Modal, Notice } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';

declare global {
	interface Window {
		/** Edition flag the server prints before this script runs. */
		yayCheckoutBlocks?: { isPro: boolean };
	}
}

/** Locks Import / Export outside Pro. Cosmetic only: it hides UI, it guards no data. */
const IS_PRO = window.yayCheckoutBlocks?.isPro ?? false;

const UPGRADE_URL = 'https://yaycommerce.com/yaycheckout-for-woocommerce/';

/** Must equal Layout::EXPORT_FORMAT, or every exported file is refused here. */
const LAYOUT_EXPORT_FORMAT = 'yaycheckout-layout';

/** The file is read inside the editor tab, so a huge one would freeze it; real exports are a few KB. */
const MAX_IMPORT_BYTES = 5 * 1024 * 1024;

type ImportReview = { fileName: string; fileSize: number; title: string; content: string; blocks: number };

/**
 * The one layout in an export file, or an Error whose message says why it cannot be imported.
 * Nothing re-checks the file after this, so the Checkout-block rule is enforced here.
 */
function reviewExportFile( text: string ): Pick< ImportReview, 'title' | 'content' | 'blocks' > {
	let envelope;
	try {
		envelope = JSON.parse( text );
	} catch {
		throw new Error( __( 'This file is not valid JSON.', 'yaycheckout' ) );
	}

	if ( envelope?.format !== LAYOUT_EXPORT_FORMAT || ! Array.isArray( envelope.layouts ) ) {
		throw new Error( __( 'This file is not a YayCheckout layout export.', 'yaycheckout' ) );
	}

	const [ layout ] = envelope.layouts;
	if ( envelope.layouts.length !== 1 || typeof layout?.title !== 'string' || typeof layout?.content !== 'string' ) {
		throw new Error( __( 'The file must contain exactly one layout.', 'yaycheckout' ) );
	}

	if ( ! layout.content.includes( '<!-- wp:yaycheckout/checkout ' ) ) {
		throw new Error( __( 'The file does not contain a checkout layout.', 'yaycheckout' ) );
	}

	return {
		title: layout.title,
		content: layout.content,
		blocks: layout.content.split( '<!-- wp:yaycheckout/' ).length - 1,
	};
}

/** Download name from Content-Disposition, with a generic name when the header is missing or malformed. */
function filenameFromHeaders( headers: Headers ): string {
	return /filename="([^"]+)"/.exec( headers.get( 'content-disposition' ) ?? '' )?.[ 1 ] ?? 'yaycheckout-layout.json';
}

function saveFile( filename: string, json: string ) {
	const url = URL.createObjectURL( new Blob( [ json ], { type: 'application/json' } ) );
	const anchor = document.createElement( 'a' );
	anchor.href = url;
	anchor.download = filename;
	anchor.click();
	// Deferred: Safari can drop the download if the URL is revoked in the same tick as the click.
	setTimeout( () => URL.revokeObjectURL( url ), 0 );
}

/** A message for an apiFetch rejection: the raw Response when `parse: false`, else `{ message }`. Never empty. */
async function errorMessage( error: unknown ): Promise< string > {
	let message: string | undefined;
	if ( error instanceof Response ) {
		message = ( await error.json().catch( () => null ) )?.message;
	} else {
		message = ( error as { message?: string } )?.message;
	}
	return message || __( 'The layout could not be exported.', 'yaycheckout' );
}

function formatFileSize( bytes: number ): string {
	return bytes < 1024 ? `${ bytes } B` : `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
}

const SUMMARY_LABEL_STYLE = { display: 'block', color: '#757575', marginBottom: 4 } as const;

/** Import / Export panel in the layout's document sidebar tab. */
function LayoutImportExport() {
	const [ isImportOpen, setImportOpen ] = useState( false );
	const [ importReview, setImportReview ] = useState< ImportReview | null >( null );
	const [ importError, setImportError ] = useState( '' );
	const [ isExporting, setExporting ] = useState( false );
	const latestPick = useRef( 0 );
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

	if ( postType !== LAYOUT_POST_TYPE ) {
		return null;
	}

	const handleFiles = async ( files: FileList | File[] ) => {
		const file = files[ 0 ];
		if ( ! file ) {
			return;
		}
		// Only the latest pick may set state: a slower read of an earlier file would otherwise overwrite it.
		const pick = ++latestPick.current;
		setImportReview( null );
		// DropZone ignores `accept`, and some OSes report an empty MIME type for .json, so check the extension.
		if ( ! file.name.toLowerCase().endsWith( '.json' ) ) {
			setImportError( __( 'Only .json files can be imported.', 'yaycheckout' ) );
			return;
		}
		if ( file.size > MAX_IMPORT_BYTES ) {
			setImportError( __( 'This file is too large to be a layout export.', 'yaycheckout' ) );
			return;
		}
		try {
			const review = reviewExportFile( await file.text() );
			if ( pick === latestPick.current ) {
				setImportReview( { fileName: file.name, fileSize: file.size, ...review } );
				setImportError( '' );
			}
		} catch ( error ) {
			if ( pick === latestPick.current ) {
				setImportError( ( error as Error ).message );
			}
		}
	};

	const closeImport = () => {
		latestPick.current++;
		setImportOpen( false );
		setImportReview( null );
		setImportError( '' );
	};

	// Unsaved and undoable on purpose: saving stays the merchant's call.
	const applyImport = () => {
		if ( ! importReview ) {
			return;
		}
		resetEditorBlocks( parse( importReview.content ) );
		createSuccessNotice(
			__( 'Layout imported. Review it, then save when ready.', 'yaycheckout' ),
			{ type: 'snackbar' }
		);
		closeImport();
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
			createErrorNotice( await errorMessage( error ), { type: 'snackbar' } );
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
						<ExternalLink href={ UPGRADE_URL }>{ __( 'Upgrade', 'yaycheckout' ) }</ExternalLink>
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
			{ isImportOpen && (
				<Modal
					title={ __( 'Import layout', 'yaycheckout' ) }
					size="medium"
					onRequestClose={ closeImport }
				>
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
						<Button __next40pxDefaultSize variant="tertiary" onClick={ closeImport }>
							{ __( 'Cancel', 'yaycheckout' ) }
						</Button>
						<Button
							__next40pxDefaultSize
							variant="primary"
							disabled={ ! importReview }
							accessibleWhenDisabled
							onClick={ applyImport }
						>
							{ __( 'Import', 'yaycheckout' ) }
						</Button>
					</Flex>
				</Modal>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'yaycheckout-layout-import-export', {
	render: LayoutImportExport,
} );
