import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import itemsMeta from '../order-summary-items/block.json';
import totalsMeta from '../order-summary-totals/block.json';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { SectionLabel } from '../shared/section-label';

interface OrderSummaryAttributes {
	label: string;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryAttributes > ) => void;
	clientId: string;
}

// templates/layouts/classic-two-column/markup.php seeds the same children, with Coupon Form between them; change both.
const TEMPLATE: [ string ][] = [ [ itemsMeta.name ], [ totalsMeta.name ] ];

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { label, style } = attributes;
	const css = useBlockGapCSS( `#block-${ clientId }`, style );
	const blockProps = useBlockProps( { className: 'yaycheckout-order-summary' } );
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks,
		template: TEMPLATE,
		templateLock: false,
	} );

	return (
		<div { ...innerBlocksProps }>
			{ css && <style>{ css }</style> }
			<SectionLabel
				panelTitle={ __( 'Order summary', 'yaycheckout' ) }
				label={ label }
				defaultLabel={ __( 'Your order', 'yaycheckout' ) }
				setAttributes={ setAttributes }
			/>
			{ children }
		</div>
	);
}
