import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { arrowGlyph, arrowOptions, type Arrow } from '../shared/button-arrow';
import type { StepNavButtonAttributes } from '../shared/step-nav-button-edit';

interface ReturnToCartAttributes extends StepNavButtonAttributes {
	arrow: Arrow;
}

interface EditProps {
	attributes: ReturnToCartAttributes;
	setAttributes: ( attributes: Partial< ReturnToCartAttributes > ) => void;
}

// The placeholder mirrors return-to-cart/render.php; the icons come from ButtonArrow through shared/button-arrow.ts.

/** A div, not a link: clicking the label edits it instead of leaving the editor. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-return-to-cart' } );
	const icon = arrowGlyph( attributes.arrow, 'back' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Return to cart', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Icon', 'yaycheckout' ) }
						value={ attributes.arrow }
						options={ arrowOptions( 'back' ) }
						onChange={ ( value: string ) => setAttributes( { arrow: value as Arrow } ) }
					/>
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
