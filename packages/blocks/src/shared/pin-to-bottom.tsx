import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { useIsLayoutEditor } from './layout-editor';

/** Mirrors PinToBottom::ATTRIBUTE in PHP. */
const ATTRIBUTE = 'yaycheckoutPinToBottom';

/** Mirrors PinToBottom::ALL in PHP. */
const ALL = 'all';

/** Mirrors PinToBottom::MOBILE in PHP. */
const MOBILE = 'mobile';

const GROUP = 'core/group';

type BlockSettings = { name?: string; attributes?: Record< string, unknown > };

addFilter(
	'blocks.registerBlockType',
	'yaycheckout/pin-to-bottom/attribute',
	( settings: BlockSettings, name: string ) =>
		name === GROUP
			? {
					...settings,
					attributes: {
						...settings.attributes,
						[ ATTRIBUTE ]: { type: 'string', default: '' },
					},
			  }
			: settings
);

type PanelProps = {
	clientId: string;
	attributes: Record< string, unknown > & { style?: { position?: { type?: string } } };
	setAttributes: ( attributes: Record< string, unknown > ) => void;
};

function PinToBottomPanel( { clientId, attributes, setAttributes }: PanelProps ) {
	const value = typeof attributes[ ATTRIBUTE ] === 'string' ? ( attributes[ ATTRIBUTE ] as string ) : '';

	const otherPinned = useSelect(
		( select ) => {
			const { getBlockName, getBlockAttributes, getClientIdsWithDescendants } = select( blockEditorStore );

			return ( getClientIdsWithDescendants() as string[] ).some(
				( id ) =>
					id !== clientId &&
					getBlockName( id ) === GROUP &&
					[ ALL, MOBILE ].includes( getBlockAttributes( id )?.[ ATTRIBUTE ] )
			);
		},
		[ clientId ]
	);

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Pin to bottom', 'yaycheckout' ) } initialOpen={ value !== '' }>
				<SelectControl
					label={ __( 'Pin to the bottom of the screen', 'yaycheckout' ) }
					value={ value }
					options={ [
						{ label: __( 'Off', 'yaycheckout' ), value: '' },
						{ label: __( 'All screens', 'yaycheckout' ), value: ALL },
						{ label: __( 'Mobile only (below 782px)', 'yaycheckout' ), value: MOBILE },
					] }
					onChange={ ( next: string ) => setAttributes( { [ ATTRIBUTE ]: next } ) }
					help={ __(
						'On the checkout this block stays at the bottom of the screen. The editor shows it in place.',
						'yaycheckout'
					) }
				/>
				{ value === ALL && attributes.style?.position?.type === 'sticky' && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Pinned to the bottom, this block ignores its sticky position.', 'yaycheckout' ) }
					</Notice>
				) }
				{ value !== '' && otherPinned && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Another block is already pinned to the bottom. They will overlap.', 'yaycheckout' ) }
					</Notice>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

/** Subscribes to the store only while a Group is selected in the layout editor. */
function LayoutOnlyPanel( props: PanelProps ) {
	const isLayout = useIsLayoutEditor();

	return isLayout ? <PinToBottomPanel { ...props } /> : null;
}

const withPinToBottomPanel = createHigherOrderComponent(
	( BlockEdit ) =>
		function PinToBottomBlockEdit( props: PanelProps & { name: string; isSelected: boolean } ) {
			return (
				<>
					<BlockEdit { ...props } />
					{ props.name === GROUP && props.isSelected && <LayoutOnlyPanel { ...props } /> }
				</>
			);
		},
	'withPinToBottomPanel'
);

addFilter( 'editor.BlockEdit', 'yaycheckout/pin-to-bottom/panel', withPinToBottomPanel );
