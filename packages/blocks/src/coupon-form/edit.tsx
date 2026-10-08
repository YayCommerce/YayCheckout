import { decodeEntities } from '@wordpress/html-entities';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface CouponFormAttributes {
	placeholder: string;
	buttonText: string;
}

interface EditProps {
	attributes: CouponFormAttributes;
	setAttributes: ( attributes: Partial< CouponFormAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	// The fallbacks mirror render.php, which owns the storefront defaults.
	const { placeholder, buttonText } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-coupon-form' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Coupon form', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Placeholder', 'yaycheckout' ) }
						value={ placeholder }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
					<TextControl
						label={ __( 'Button text', 'yaycheckout' ) }
						value={ decodeEntities( buttonText ) }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="yaycheckout-coupon-form__row">
					<input
						type="text"
						disabled
						className="yaycheckout-coupon-form__code"
						placeholder={ placeholder || __( 'Coupon code', 'yaycheckout' ) }
					/>
					{ /* A div, not a button: typing Space inside a button would click it. The classes match render.php, so checkout-base.css draws it. */ }
					<RichText
						tagName="div"
						className="button wp-element-button yaycheckout-coupon-form__apply"
						value={ buttonText }
						onChange={ ( value: string ) => setAttributes( { buttonText: value } ) }
						placeholder={ __( 'Apply coupon', 'yaycheckout' ) }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				</div>
			</div>
		</>
	);
}
