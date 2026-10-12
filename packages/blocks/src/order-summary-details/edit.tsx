import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';

interface EditProps {
	attributes: { style?: BlockGapStyles };
	clientId: string;
}

// A Toggle in here would hide itself with the panel it closes. Module-level, so useBlocksAllowedExcept's memo holds.
const isCheckoutSectionOrToggle = ( type: { name: string; ancestor?: string[] } ) =>
	isCheckoutSection( type ) || type.name === 'yaycheckout/order-summary-toggle';

export default function Edit( { attributes, clientId }: EditProps ) {
	const css = useBlockGapCSS( `#block-${ clientId }`, attributes.style );
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSectionOrToggle );
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		useBlockProps( { className: 'yaycheckout-order-summary-details' } ),
		{ allowedBlocks, templateLock: false }
	);

	return (
		<div { ...innerBlocksProps }>
			{ css && <style>{ css }</style> }
			{ children }
		</div>
	);
}
