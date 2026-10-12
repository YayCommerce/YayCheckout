import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { addLayoutPanel, type LayoutPanelProps } from './layout-panel';

/** Mirrors PinToBottom::ATTRIBUTE in PHP. */
const ATTRIBUTE = 'yaycheckoutPinToBottom';

/** Mirrors PinToBottom::ALL in PHP. */
const ALL = 'all';

/** Mirrors PinToBottom::MOBILE in PHP. */
const MOBILE = 'mobile';

const GROUP = 'core/group';

/** Mirrors PinToBottom::filter_group() in PHP: any other value is off. */
const isPinned = ( value: unknown ): boolean => value === ALL || value === MOBILE;

function PinToBottomPanel( { clientId, attributes, setAttributes }: LayoutPanelProps ) {
	const value = typeof attributes[ ATTRIBUTE ] === 'string' ? ( attributes[ ATTRIBUTE ] as string ) : '';

	const otherPinned = useSelect(
		( select ) => {
			const { getBlockAttributes, getBlocksByName } = select( blockEditorStore );

			return ( getBlocksByName( GROUP ) as string[] ).some(
				( id ) => id !== clientId && isPinned( getBlockAttributes( id )?.[ ATTRIBUTE ] )
			);
		},
		[ clientId ]
	);

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Pin to bottom', 'yaycheckout' ) } initialOpen={ isPinned( value ) }>
				<SelectControl
					label={ __( 'Pin to the bottom of the screen', 'yaycheckout' ) }
					value={ value }
					options={ [
						{ label: __( 'Off', 'yaycheckout' ), value: '' },
						{ label: __( 'All screens', 'yaycheckout' ), value: ALL },
						// The breakpoint matches checkout-base.css's @media (max-width: 781px).
						{ label: __( 'Mobile only (below 782px)', 'yaycheckout' ), value: MOBILE },
					] }
					onChange={ ( next: string ) => setAttributes( { [ ATTRIBUTE ]: next } ) }
					help={ __(
						'On the checkout this block stays at the bottom of the screen. The editor shows it in place.',
						'yaycheckout'
					) }
				/>
				{ value === ALL && ( attributes.style as { position?: { type?: string } } | undefined )?.position?.type === 'sticky' && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Pinned to the bottom, this block ignores its sticky position.', 'yaycheckout' ) }
					</Notice>
				) }
				{ isPinned( value ) && otherPinned && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'Another block is already pinned to the bottom. They will overlap.', 'yaycheckout' ) }
					</Notice>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

addLayoutPanel( 'yaycheckout/pin-to-bottom', [ GROUP ], ATTRIBUTE, { type: 'string', default: '' }, PinToBottomPanel );
