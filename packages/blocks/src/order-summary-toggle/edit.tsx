import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface ToggleAttributes {
	showChevron: boolean;
}

interface EditProps {
	attributes: ToggleAttributes;
	setAttributes: ( attributes: Partial< ToggleAttributes > ) => void;
}

// Content a role="button" element can hold: no links, controls or other buttons.
const ALLOWED_BLOCKS = [ 'core/paragraph', 'core/heading', 'core/image', 'core/group', 'core/spacer' ];

const TEMPLATE: [ string, Record< string, unknown > ][] = [ [ 'core/paragraph', { content: __( 'Order summary', 'yaycheckout' ) } ] ];

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { showChevron } = attributes;
	const innerBlocksProps = useInnerBlocksProps( useBlockProps( { className: 'yaycheckout-order-summary-toggle' } ), {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateLock: false,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Summary toggle', 'yaycheckout' ) }>
					<ToggleControl
						label={ __( 'Show chevron', 'yaycheckout' ) }
						checked={ showChevron }
						onChange={ ( value ) => setAttributes( { showChevron: value } ) }
					/>
					<p>{ __( 'Opens and closes the Order Summary while it is collapsible.', 'yaycheckout' ) }</p>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}
