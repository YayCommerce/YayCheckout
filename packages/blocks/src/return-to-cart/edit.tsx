import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ArrowControl } from '../shared/arrow-control';
import { arrowGlyph, type Arrow } from '../shared/button-arrow';
import type { StepNavButtonAttributes } from '../shared/step-nav-button-edit';

interface ReturnToCartAttributes extends StepNavButtonAttributes {
	arrow: Arrow;
}

interface EditProps {
	attributes: ReturnToCartAttributes;
	setAttributes: ( attributes: Partial< ReturnToCartAttributes > ) => void;
}

// The placeholder mirrors return-to-cart/render.php. The Arrow values mirror block.json's arrow enum, and the icons come from ButtonArrow through shared/button-arrow.ts.

/** A div, not a link: clicking the label edits it instead of leaving the editor. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-return-to-cart' } );
	const icon = arrowGlyph( attributes.arrow, 'back' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<ArrowControl direction="back" value={ attributes.arrow } onChange={ ( arrow ) => setAttributes( { arrow } ) } />
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="yaycheckout-return-to-cart__link">
					{ icon && (
						<span className="yaycheckout-return-to-cart__icon" aria-hidden="true">
							{ icon }
						</span>
					) }
					<RichText
						tagName="span"
						value={ attributes.buttonText }
						onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
						placeholder={ __( 'Return to cart', 'yaycheckout' ) }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				</div>
			</div>
		</>
	);
}
