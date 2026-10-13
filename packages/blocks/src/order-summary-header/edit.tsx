import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';

interface EditProps {
	context: {
		'yaycheckout/orderSummaryDescription'?: string;
	};
}

export default function Edit( { context }: EditProps ) {
	const description = ( context[ 'yaycheckout/orderSummaryDescription' ] ?? '' ).trim();
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		useBlockProps( { className: 'yaycheckout-order-summary-header' } ),
		{
			allowedBlocks,
			templateLock: false,
		}
	);

	return (
		<div { ...innerBlocksProps }>
			{ children }
			{ description !== '' && <p className="yaycheckout-section-description">{ description }</p> }
		</div>
	);
}
