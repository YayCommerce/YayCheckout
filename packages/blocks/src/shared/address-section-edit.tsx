import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isCheckoutSection, useBlocksAllowedExcept } from './allowed-blocks';

export interface AddressSectionAttributes {
	title: string;
	showTitle: boolean;
	shipToDifferent?: boolean;
}

interface AddressSectionEditProps {
	section: 'billing' | 'shipping';
	attributes: AddressSectionAttributes;
	setAttributes: ( attributes: Partial< AddressSectionAttributes > ) => void;
}

export function AddressSectionEdit( {
	section,
	attributes,
	setAttributes,
}: AddressSectionEditProps ) {
	const { title, showTitle, shipToDifferent } = attributes;
	const fallbackTitle =
		section === 'shipping' ? __( 'Shipping', 'yaycheckout' ) : __( 'Billing', 'yaycheckout' );
	const blockProps = useBlockProps( { className: `yaycheckout-${ section }` } );
	const allowedBlocks = useBlocksAllowedExcept( isCheckoutSection );
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		allowedBlocks,
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
				{ showTitle && <h3>{ title || fallbackTitle }</h3> }
				{ children }
			</div>
		</>
	);
}
