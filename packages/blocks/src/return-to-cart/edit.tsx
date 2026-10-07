import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { StepNavButtonAttributes } from '../shared/step-nav-button-edit';

type Arrow = 'none' | 'arrow' | 'chevron';

interface ReturnToCartAttributes extends StepNavButtonAttributes {
	arrow: Arrow;
}

interface EditProps {
	attributes: ReturnToCartAttributes;
	setAttributes: ( attributes: Partial< ReturnToCartAttributes > ) => void;
}

// The icons, the Arrow values and the placeholder mirror block.json's arrow enum and return-to-cart/render.php.
const ICONS: Record< Arrow, string > = { none: '', arrow: '←', chevron: '‹' };

/** A div, not a link: clicking the label edits it instead of leaving the editor. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-return-to-cart' } );
	const icon = ICONS[ attributes.arrow ];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Return to cart', 'yaycheckout' ) }>
					<SelectControl
						label={ __( 'Icon', 'yaycheckout' ) }
						value={ attributes.arrow }
						options={ [
							{ label: __( 'None', 'yaycheckout' ), value: 'none' },
							{ label: __( 'Arrow (←)', 'yaycheckout' ), value: 'arrow' },
							{ label: __( 'Chevron (‹)', 'yaycheckout' ), value: 'chevron' },
						] }
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
