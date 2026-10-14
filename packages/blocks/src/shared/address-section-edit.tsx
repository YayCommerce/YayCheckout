import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addressTemplate } from './address-template';
import { isCheckoutSection, useBlocksAllowedExcept } from './allowed-blocks';
import { useBlockGapCSS, type BlockGapStyles } from './block-gap';
import {
	SectionDescription,
	SectionHeadingToolbar,
	sectionTextCSS,
	SectionTextPanels,
	SectionTitle,
} from './section-heading';

export interface AddressSectionAttributes {
	title: string;
	showTitle: boolean;
	headingLevel?: number;
	description?: string;
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
	const { title, showTitle, headingLevel, style } = attributes;
	const description = attributes.description ?? '';
	const css = useBlockGapCSS( `#block-${ clientId }`, style ) + sectionTextCSS( clientId, style );
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
			{ showTitle && (
				<SectionHeadingToolbar level={ headingLevel } onChange={ ( level ) => setAttributes( { headingLevel: level } ) } />
			) }
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
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
					<TextControl
						label={ __( 'Description', 'yaycheckout' ) }
						value={ description }
						onChange={ ( value ) => setAttributes( { description: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="styles">
				<SectionTextPanels
					style={ style }
					onChange={ ( next ) => setAttributes( { style: next as BlockGapStyles | undefined } ) }
					showTitle={ showTitle }
					showDescription={ description.trim() !== '' }
				/>
			</InspectorControls>
			<div { ...innerBlocksProps }>
				{ css && <style>{ css }</style> }
				{ showTitle && <SectionTitle level={ headingLevel } text={ title || fallbackTitle } /> }
				<SectionDescription text={ description } />
				{ children }
			</div>
		</>
	);
}
