import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

interface OrderAgainButtonAttributes {
	buttonText: string;
}

interface EditProps {
	attributes: OrderAgainButtonAttributes;
	setAttributes: ( attributes: Partial< OrderAgainButtonAttributes > ) => void;
}

/**
 * The button's label, edited in place: a div, not a <button>, so clicking edits the label.
 * Mirrors the class and default label order-again-button/render.php prints on the <button>.
 */
export default function Edit( { attributes, setAttributes }: EditProps ) {
	const blockProps = useBlockProps( { className: 'yaycheckout-order-again wp-element-button' } );

	return (
		<RichText
			{ ...blockProps }
			tagName="div"
			value={ attributes.buttonText }
			onChange={ ( buttonText: string ) => setAttributes( { buttonText } ) }
			placeholder={ __( 'Order again', 'yaycheckout' ) }
			allowedFormats={ [] }
			withoutInteractiveFormatting
			disableLineBreaks
		/>
	);
}
