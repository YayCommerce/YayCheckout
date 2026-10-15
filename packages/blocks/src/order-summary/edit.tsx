import { InspectorControls, store as blockEditorStore, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { cloneBlock, createBlock } from '@wordpress/blocks';
import {
	Notice,
	PanelBody,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { escapeHTML } from '@wordpress/escape-html';
import { __ } from '@wordpress/i18n';
import breakdownMeta from '../order-summary-breakdown/block.json';
import detailsMeta from '../order-summary-details/block.json';
import headerMeta from '../order-summary-header/block.json';
import itemsMeta from '../order-summary-items/block.json';
import toggleMeta from '../order-summary-toggle/block.json';
import totalMeta from '../order-summary-total/block.json';
import type { TemplateArray } from '../shared/address-template';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { SectionLabel } from '../shared/section-label';
import { isNewShape, useSummaryCollapse } from './shape';

type InitialState = 'collapsed' | 'open';

interface OrderSummaryAttributes {
	label: string;
	collapsible?: boolean;
	initialState?: InitialState;
	style?: BlockGapStyles;
}

interface EditProps {
	attributes: OrderSummaryAttributes;
	setAttributes: ( attributes: Partial< OrderSummaryAttributes > ) => void;
	clientId: string;
}

// A Header holding a Toggle holding a Heading, then Details holding Items, Order Summary Breakdown and Order Summary Total. templates/layouts/classic-two-column/markup.php
// seeds the same tree, with Coupon Form between Items and Order Summary Breakdown; change both.
const TEMPLATE: TemplateArray[] = [
	[
		headerMeta.name,
		{},
		[ [ toggleMeta.name, {}, [ [ 'core/heading', { level: 3, content: __( 'Order summary', 'yaycheckout' ) } ] ] ] ],
	],
	[ detailsMeta.name, { lock: { remove: true } }, [ [ itemsMeta.name ], [ breakdownMeta.name ], [ totalMeta.name ] ] ],
];

const NEW_SHAPE_CHILDREN = [ headerMeta.name, detailsMeta.name ];

export default function Edit( { attributes, setAttributes, clientId }: EditProps ) {
	const { label, initialState = 'collapsed', style } = attributes;
	const css = useBlockGapCSS( `#block-${ clientId }`, style );
	const { collapsible, hasTrigger } = useSummaryCollapse();
	const { newShape, headerId, headerIndex, detailsIndex, childCount } = useSelect(
		( select ) => {
			const children = select( blockEditorStore ).getBlocks( clientId );
			const names = children.map( ( block ) => block.name );
			const index = names.indexOf( headerMeta.name );

			return {
				newShape: isNewShape( names ),
				headerId: index === -1 ? null : children[ index ].clientId,
				headerIndex: index,
				detailsIndex: names.indexOf( detailsMeta.name ),
				childCount: names.length,
			};
		},
		[ clientId ]
	);
	const { moveBlockToPosition, replaceInnerBlocks } = useDispatch( blockEditorStore );
	const { getBlocks } = useSelect( blockEditorStore );
	const blockProps = useBlockProps( { className: 'yaycheckout-order-summary' } );
	const oldShapeAllowed = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks: newShape ? NEW_SHAPE_CHILDREN : oldShapeAllowed,
		template: TEMPLATE,
		templateLock: false,
	} );
	// The block order is the direction: a Header after Details sits below them, so they open upward.
	const opensUp = headerIndex !== -1 && detailsIndex !== -1 && headerIndex > detailsIndex;

	// Opt-in, never on load: converting markup the merchant didn't touch would reach the post on the merchant's next save.
	// The heading keeps the old one's text (plain, so escaped into rich text) and id, so a theme styling #order_review_heading still finds it.
	const convert = () =>
		replaceInnerBlocks( clientId, [
			createBlock( headerMeta.name, {}, [
				createBlock( toggleMeta.name, {}, [
					createBlock( 'core/heading', {
						level: 3,
						content: escapeHTML( label || __( 'Your order', 'yaycheckout' ) ),
						anchor: 'order_review_heading',
					} ),
				] ),
			] ),
			createBlock( detailsMeta.name, { lock: { remove: true } }, getBlocks( clientId ).map( ( block ) => cloneBlock( block ) ) ),
		] );

	return (
		<div { ...innerBlocksProps }>
			{ css && <style>{ css }</style> }
			{ ! newShape && (
				<SectionLabel label={ label } defaultLabel={ __( 'Your order', 'yaycheckout' ) } setAttributes={ setAttributes } />
			) }
			<InspectorControls>
				{ newShape ? (
					<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
						<ToggleControl
							label={ __( 'Collapsible', 'yaycheckout' ) }
							checked={ collapsible }
							disabled={ ! hasTrigger }
							help={
								hasTrigger
									? undefined
									: __( 'Add a Summary Header or a Summary Toggle to make the summary collapsible.', 'yaycheckout' )
							}
							onChange={ ( value ) => setAttributes( { collapsible: value } ) }
						/>
						{ collapsible && (
							<ToggleGroupControl
								label={ __( 'Starts', 'yaycheckout' ) }
								value={ initialState }
								isBlock
								__nextHasNoMarginBottom
								onChange={ ( value ) => setAttributes( { initialState: value as InitialState } ) }
							>
								<ToggleGroupControlOption value="collapsed" label={ __( 'Collapsed', 'yaycheckout' ) } />
								<ToggleGroupControlOption value="open" label={ __( 'Open', 'yaycheckout' ) } />
							</ToggleGroupControl>
						) }
						{ collapsible && headerId && (
							<ToggleGroupControl
								label={ __( 'Opens', 'yaycheckout' ) }
								value={ opensUp ? 'up' : 'down' }
								isBlock
								__nextHasNoMarginBottom
								onChange={ ( value ) =>
									moveBlockToPosition( headerId, clientId, clientId, value === 'up' ? childCount - 1 : 0 )
								}
							>
								<ToggleGroupControlOption value="down" label={ __( 'Down', 'yaycheckout' ) } />
								<ToggleGroupControlOption value="up" label={ __( 'Up', 'yaycheckout' ) } />
							</ToggleGroupControl>
						) }
					</PanelBody>
				) : (
					<PanelBody>
						<Notice
							status="info"
							isDismissible={ false }
							actions={ [ { label: __( 'Use header and details', 'yaycheckout' ), onClick: convert, variant: 'primary' } ] }
						>
							{ __(
								'This summary uses the old layout. Switch to a header and a details part to make it collapsible.',
								'yaycheckout'
							) }
						</Notice>
					</PanelBody>
				) }
			</InspectorControls>
			{ children }
		</div>
	);
}
