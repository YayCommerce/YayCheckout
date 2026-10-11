import { InspectorControls, useBlockProps, useInnerBlocksProps, useSettings } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addressTemplate } from './address-template';
import { isCheckoutSection, useBlocksAllowedExcept } from './allowed-blocks';
import { blockGapCSS, type BlockGapStyles } from './block-gap';

export interface AddressSectionAttributes {
	title: string;
	showTitle: boolean;
	shipToDifferent?: boolean;
	style?: BlockGapStyles;
}

interface AddressSectionEditProps {
	section: 'billing' | 'shipping';
	attributes: AddressSectionAttributes;
	setAttributes: ( attributes: Partial< AddressSectionAttributes > ) => void;
	clientId: string;
}

export function AddressSectionEdit( {
	section,
	attributes,
	setAttributes,
	clientId,
}: AddressSectionEditProps ) {
	const { title, showTitle, shipToDifferent, style } = attributes;
	const [ viewport ] = useSettings( 'viewport' );
	const css = blockGapCSS( `#block-${ clientId }`, style, viewport );
	const fallbackTitle =
		section === 'shipping' ? __( 'Shipping', 'yaycheckout' ) : __( 'Billing', 'yaycheckout' );
	const blockProps = useBlockProps( { className: `yaycheckout-${ section }` } );
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks,
		// Only fills an empty section, so saved ones are untouched.
		template: addressTemplate( section ),
		templateLock: false,
	} );

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
			<div { ...innerBlocksProps }>
				{ css && <style>{ css }</style> }
				{ showTitle && <h3>{ title || fallbackTitle }</h3> }
				{ children }
			</div>
		</>
	);
}
