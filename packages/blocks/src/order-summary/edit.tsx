import { store as blockEditorStore, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import detailsMeta from '../order-summary-details/block.json';
import headerMeta from '../order-summary-header/block.json';
import itemsMeta from '../order-summary-items/block.json';
import totalsMeta from '../order-summary-totals/block.json';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { SectionLabel } from '../shared/section-label';
import { isNewShape } from './shape';

interface OrderSummaryAttributes {
	label: string;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryAttributes > ) => void;
	clientId: string;
}

/** A block template: [ name, attributes?, inner template? ], nested. */
type TemplateArray = ( string | object | TemplateArray )[];

// A Header holding a Toggle holding a Heading, then Details holding Items and Totals. templates/layouts/classic-two-column/markup.php
// seeds the same tree, with Coupon Form between Items and Totals; change both.
const TEMPLATE: TemplateArray[] = [
	[
		headerMeta.name,
		{},
		[ [ 'yaycheckout/order-summary-toggle', {}, [ [ 'core/heading', { level: 3, content: __( 'Order summary', 'yaycheckout' ) } ] ] ] ],
	],
	[ detailsMeta.name, { lock: { remove: true } }, [ [ itemsMeta.name ], [ totalsMeta.name ] ] ],
];

const NEW_SHAPE_CHILDREN = [ headerMeta.name, detailsMeta.name ];

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { label, style } = attributes;
	const css = useBlockGapCSS( `#block-${ clientId }`, style );
	const blockProps = useBlockProps( { className: 'yaycheckout-order-summary' } );
	const newShape = useSelect(
		( select ) => isNewShape( select( blockEditorStore ).getBlocks( clientId ).map( ( block ) => block.name ) ),
		[ clientId ]
	);
	const oldShapeAllowed = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks: newShape ? NEW_SHAPE_CHILDREN : oldShapeAllowed,
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
				showHeading={ ! newShape }
				help={
					newShape
						? __( "Read by screen readers as the name of the header's open/close button.", 'yaycheckout' )
						: undefined
				}
			/>
			{ children }
		</div>
	);
}
