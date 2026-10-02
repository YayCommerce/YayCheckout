import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const STEP = 'yaycheckout/accordion-step';

interface AccordionAttributes {
	layout: string;
}

interface EditProps {
	attributes: AccordionAttributes;
	setAttributes: ( attributes: Partial< AccordionAttributes > ) => void;
}

/**
 * Canvas for checkout steps. Every step stays open here so its sections
 * can be edited; locking and Continue run on the storefront.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { layout } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-accordion' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Accordion', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Layout', 'yaycheckout' ) }
						value={ layout || 'list' }
						options={ [
							{ label: __( 'List', 'yaycheckout' ), value: 'list' },
							{ label: __( 'One step', 'yaycheckout' ), value: 'single' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks allowedBlocks={ [ STEP ] } templateLock={ false } />
			</div>
		</>
	);
}
