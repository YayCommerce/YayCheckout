import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import itemsMeta from '../order-summary-items/block.json';
import totalsMeta from '../order-summary-totals/block.json';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';
import '../shared/section-preview.css';
import './editor.css';

interface OrderSummaryAttributes {
	label: string;
}

interface EditProps {
	attributes: OrderSummaryAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryAttributes > ) => void;
}

// templates/default-layout.php seeds the same children; change both.
const TEMPLATE: [ string ][] = [ [ itemsMeta.name ], [ totalsMeta.name ] ];

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-order-summary' } );
	const heading = label || __( 'Your order', 'yaycheckout' );
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks,
		template: TEMPLATE,
		templateLock: false,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Order summary', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps }>
				<h3 className="yaycheckout-section-preview__heading">{ heading }</h3>
				{ children }
			</div>
		</>
	);
}
