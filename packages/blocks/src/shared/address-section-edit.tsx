import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { store as blocksStore } from '@wordpress/blocks';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export interface AddressSectionAttributes {
	title: string;
	showTitle: boolean;
	shipToDifferent?: boolean;
}

interface BlockMeta {
	name: string;
	ancestor?: string[];
}

interface RequireContext {
	keys(): string[];
	( id: string ): BlockMeta | { default: BlockMeta };
}

declare const require: {
	context( path: string, deep?: boolean, filter?: RegExp ): RequireContext;
};

const CHECKOUT = 'yaycheckout/checkout';
const blockMeta = require.context( '..', true, /\/block\.json$/ );

// ancestor Checkout also matches inside an address section. Step buttons stay.
const keptInAddress = new Set( [ 'yaycheckout/step-button', 'yaycheckout/back-button' ] );
const excludedFromAddress = new Set(
	blockMeta
		.keys()
		.map( ( key ) => {
			const loaded = blockMeta( key );

			return 'default' in loaded ? loaded.default : loaded;
		} )
		.filter( ( block ) => ( block.ancestor ?? [] ).includes( CHECKOUT ) && ! keptInAddress.has( block.name ) )
		.map( ( block ) => block.name )
);

interface AddressSectionEditProps {
	section: 'billing' | 'shipping';
	attributes: AddressSectionAttributes;
	setAttributes: ( attributes: Partial< AddressSectionAttributes > ) => void;
}

/**
 * Billing / Shipping hold fields, layout blocks, and the step buttons.
 */
export function AddressSectionEdit( {
	section,
	attributes,
	setAttributes,
}: AddressSectionEditProps ) {
	const { title, showTitle, shipToDifferent } = attributes;
	const fallbackTitle =
		section === 'shipping' ? __( 'Shipping', 'yaycheckout' ) : __( 'Billing', 'yaycheckout' );
	const blockProps = useBlockProps( { className: `yaycheckout-${ section }` } );
	const allowedBlocks = useSelect(
		( select ) =>
			select( blocksStore )
				.getBlockTypes()
				.map( ( block: { name: string } ) => block.name )
				.filter( ( name: string ) => ! excludedFromAddress.has( name ) ),
		[]
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ fallbackTitle }>
					<ToggleControl
						label={ __( 'Show title', 'yaycheckout' ) }
						checked={ showTitle }
						onChange={ ( value ) => setAttributes( { showTitle: value } ) }
					/>
					{ showTitle && (
						<TextControl
							label={ __( 'Title', 'yaycheckout' ) }
							value={ title }
							onChange={ ( value ) => setAttributes( { title: value } ) }
						/>
					) }
					{ section === 'shipping' && (
						<ToggleControl
							label={ __( 'Ship to a different address', 'yaycheckout' ) }
							checked={ shipToDifferent !== false }
							onChange={ ( value ) => setAttributes( { shipToDifferent: value } ) }
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showTitle && <h3>{ title || fallbackTitle }</h3> }
				<InnerBlocks allowedBlocks={ allowedBlocks } templateLock={ false } />
			</div>
		</>
	);
}
