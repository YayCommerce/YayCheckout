import { store as blockEditorStore } from '@wordpress/block-editor';
import { Button, DropZone, Flex, FormFileUpload, Modal } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

const LAYOUT_POST_TYPE = 'yay_checkout_layout';

/** Import / Export panel in the layout's document sidebar tab. */
function LayoutImportExport() {
	const [ isImportOpen, setImportOpen ] = useState( false );
	const { postType, maxUploadFileSize } = useSelect(
		( select ) => ( {
			postType: select( editorStore ).getCurrentPostType(),
			maxUploadFileSize: select( blockEditorStore ).getSettings().maxUploadFileSize,
		} ),
		[]
	);

	if ( postType !== LAYOUT_POST_TYPE ) {
		return null;
	}

	// ponytail: file is picked but not processed yet — wire parsing/import once the file format is defined.
	const handleFiles = ( files: FileList | File[] ) => {
		if ( files.length ) {
			setImportOpen( false );
		}
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
						{ __( 'Creates a draft. Never touches the live layout.', 'yaycheckout' ) }
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
					onRequestClose={ () => setImportOpen( false ) }
				>
					<div style={ { position: 'relative', padding: '32px 48px', textAlign: 'center' } }>
						<DropZone onFilesDrop={ handleFiles } />
						<h2 style={ { fontWeight: 400 } }>{ __( 'Drop files to upload', 'yaycheckout' ) }</h2>
						<p>{ __( 'or', 'yaycheckout' ) }</p>
						<FormFileUpload
							__next40pxDefaultSize
							variant="secondary"
							accept="application/json"
							onChange={ ( event ) => handleFiles( event.currentTarget.files ?? [] ) }
						>
							{ __( 'Select Files', 'yaycheckout' ) }
						</FormFileUpload>
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
				</Modal>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'yaycheckout-layout-import-export', {
	render: LayoutImportExport,
} );
