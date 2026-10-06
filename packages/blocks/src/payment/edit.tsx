import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.css';

interface PaymentAttributes {
	label: string;
}

interface EditProps {
	attributes: PaymentAttributes;
	setAttributes: ( attributes: Partial< PaymentAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	const { label } = attributes;
	const blockProps = useBlockProps( { className: 'yaycheckout-payment' } );
	const heading = label || __( 'Payment', 'yaycheckout' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Payment', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Label', 'yaycheckout' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3 className="yaycheckout-payment__heading">{ heading }</h3>
				<label className="yaycheckout-payment__option">
					<input type="radio" disabled defaultChecked />{ ' ' }
					{ __( 'Direct bank transfer', 'yaycheckout' ) }
				</label>
				<label className="yaycheckout-payment__option">
					<input type="radio" disabled />{ ' ' }
					{ __( 'Cash on delivery', 'yaycheckout' ) }
				</label>
			</div>
		</>
	);
}
