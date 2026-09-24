import { store as blockEditorStore } from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';
import { Button, Dashicon, DropZone, Flex, FormFileUpload, Modal, Notice } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';

/** Mirrors Layout::EXPORT_FORMAT in PHP. */
const LAYOUT_EXPORT_FORMAT = 'yaycheckout-layout';

type ImportReview = { title: string; content: string; blocks: number };

/**
 * Reads the one layout out of an export file, or throws why it cannot be imported.
 * Same checks and messages as parseExportFile() + reviewExportFile() in apps/admin tools.api.ts (which this build cannot import),
 * plus LayoutImportReview's Checkout-block rule: this import never reaches PHP, so it is the only gate before the blocks land.
 */
function reviewExportFile( text: string ): ImportReview {
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

	// Same count as the `blocks` field of Layout::to_summary_array(): our blocks only, one `<!-- wp:yaycheckout/` each.
	return {
		title: layout.title,
		content: layout.content,
		blocks: layout.content.split( '<!-- wp:yaycheckout/' ).length - 1,
	};
}

const SUMMARY_LABEL_STYLE = { display: 'block', color: '#757575', marginBottom: 4 } as const;

/** Import / Export panel in the layout's document sidebar tab. */
function LayoutImportExport() {
	const [ isImportOpen, setImportOpen ] = useState( false );
	const [ importFile, setImportFile ] = useState< File | null >( null );
	const [ importReview, setImportReview ] = useState< ImportReview | null >( null );
	const [ importError, setImportError ] = useState( '' );
	const { postType, maxUploadFileSize } = useSelect(
		( select ) => ( {
			postType: select( editorStore ).getCurrentPostType(),
			maxUploadFileSize: select( blockEditorStore ).getSettings().maxUploadFileSize,
		} ),
		[]
	);
	const { resetEditorBlocks } = useDispatch( editorStore );
	const { createSuccessNotice } = useDispatch( noticesStore );

	if ( postType !== LAYOUT_POST_TYPE ) {
		return null;
	}

	const handleFiles = async ( files: FileList | File[] ) => {
		const file = files[ 0 ];
		if ( ! file ) {
			return;
		}
		setImportFile( null );
		setImportReview( null );
		// DropZone ignores `accept`, and some OSes report an empty MIME type for .json, so check the extension.
		if ( ! file.name.toLowerCase().endsWith( '.json' ) ) {
			setImportError( __( 'Only .json files can be imported.', 'yaycheckout' ) );
			return;
		}
		try {
			setImportReview( reviewExportFile( await file.text() ) );
			setImportFile( file );
			setImportError( '' );
		} catch ( error ) {
			setImportError( ( error as Error ).message );
		}
	};

	// Unlike Tools → Import (a new draft), this swaps the open layout's blocks as an unsaved, undoable edit; saving is the merchant's call.
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

	const closeImport = () => {
		setImportOpen( false );
		setImportFile( null );
		setImportReview( null );
		setImportError( '' );
	};

	return (
		<PluginDocumentSettingPanel
			name="yaycheckout-layout-import-export"
			title={ __( 'Import / Export', 'yaycheckout' ) }
		>
			<Flex direction="column" align="stretch" gap={ 4 }>
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
					<Button __next40pxDefaultSize className="editor-post-featured-image__toggle">
						{ __( 'Export', 'yaycheckout' ) }
					</Button>
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
						{ importFile && (
							<p style={ { marginTop: 24 } }>
								<strong>{ importFile.name }</strong>
								<br />
								{ importFile.size < 1024
									? `${ importFile.size } B`
									: `${ ( importFile.size / 1024 ).toFixed( 1 ) } KB` }
							</p>
						) }
						{ maxUploadFileSize > 0 && (
							<p style={ { marginTop: 24 } }>
								{ sprintf(
									/* translators: %s: max upload size, e.g. "512 MB". */
									__( 'Maximum upload file size: %s.', 'yaycheckout' ),
									`${ Math.round( maxUploadFileSize / 1024 / 1024 ) } MB`
								) }
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
										/* translators: %s: layout title */
										__( 'Ready to import "%s"', 'yaycheckout' ),
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
									<span style={ SUMMARY_LABEL_STYLE }>{ __( 'Layout', 'yaycheckout' ) }</span>
									<strong>{ importReview.title }</strong>
								</div>
								<div>
									<span style={ SUMMARY_LABEL_STYLE }>{ __( 'Checkout blocks', 'yaycheckout' ) }</span>
									<strong>{ importReview.blocks }</strong>
								</div>
							</Flex>
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Import replaces all the content of this layout with the file’s content. Nothing is saved until you click Save, and you can undo it.',
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
