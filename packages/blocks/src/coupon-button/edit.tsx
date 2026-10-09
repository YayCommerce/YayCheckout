import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

interface CouponButtonAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: CouponButtonAttributes;
	setAttributes: ( attributes: Partial< CouponButtonAttributes > ) => void;
}

/** The button is the block's root element, so its style supports land on it. Mirrors the classes and default label render.php prints. */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'button wp-element-button yaycheckout-coupon-form__apply' } );

	return (
		// A div, not a button: typing Space inside a button would click it. checkout-base.css draws it from its classes.
		<RichText
			{ ...blockProps }
			tagName="div"
			value={ attributes.buttonText }
			onChange={ ( buttonText: string ) => setAttributes( { buttonText } ) }
			placeholder={ __( 'Apply coupon', 'yaycheckout' ) }
			allowedFormats={ [] }
			withoutInteractiveFormatting
		/>
	);
}
