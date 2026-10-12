import { InspectorControls, store as blockEditorStore, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import detailsMeta from '../order-summary-details/block.json';
import headerMeta from '../order-summary-header/block.json';
import itemsMeta from '../order-summary-items/block.json';
import toggleMeta from '../order-summary-toggle/block.json';
import totalsMeta from '../order-summary-totals/block.json';
import { isCheckoutSection, useBlocksAllowedExcept } from '../shared/allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from '../shared/block-gap';
import { SectionLabel } from '../shared/section-label';
import { isNewShape, useSummaryCollapse } from './shape';
import './editor.css';

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

/** A block template: [ name, attributes?, inner template? ], nested. */
type TemplateArray = ( string | object | TemplateArray )[];

// A Header holding a Toggle holding a Heading, then Details holding Items and Totals. templates/layouts/classic-two-column/markup.php
// seeds the same tree, with Coupon Form between Items and Totals; change both.
const TEMPLATE: TemplateArray[] = [
	[
		headerMeta.name,
		{},
		[ [ toggleMeta.name, {}, [ [ 'core/heading', { level: 3, content: __( 'Order summary', 'yaycheckout' ) } ] ] ] ],
	],
	[ detailsMeta.name, { lock: { remove: true } }, [ [ itemsMeta.name ], [ totalsMeta.name ] ] ],
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
	const { moveBlockToPosition } = useDispatch( blockEditorStore );
	const blockProps = useBlockProps( {
		className: [ 'yaycheckout-order-summary', collapsible && initialState === 'collapsed' ? 'is-preview-collapsed' : '' ]
			.filter( Boolean )
			.join( ' ' ),
	} );
	const oldShapeAllowed = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks: newShape ? NEW_SHAPE_CHILDREN : oldShapeAllowed,
		template: TEMPLATE,
		templateLock: false,
	} );
	// The block order is the direction: a Header after Details sits below them, so they open upward.
	const opensUp = headerIndex !== -1 && detailsIndex !== -1 && headerIndex > detailsIndex;

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
			{ newShape && (
				<InspectorControls>
					<PanelBody title={ __( 'Collapsing', 'yaycheckout' ) }>
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
						{ headerId && (
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
				</InspectorControls>
			) }
			{ children }
		</div>
	);
}
