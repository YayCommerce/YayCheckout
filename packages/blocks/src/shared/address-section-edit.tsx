import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
				<InnerBlocks templateLock={ false } />
			</div>
		</>
	);
}
