import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface CouponFormAttributes {
	placeholder: string;
	buttonText: string;
}

interface EditProps {
	attributes: CouponFormAttributes;
	setAttributes: ( attributes: Partial< CouponFormAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
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
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div style={ { display: 'flex', gap: '8px' } }>
					<input
						type="text"
						disabled
						placeholder={ placeholder || __( 'Coupon code', 'yaycheckout' ) }
						style={ { flex: 1, padding: '10px 12px' } }
					/>
					<button
						type="button"
						disabled
						style={ {
							padding: '10px 16px',
							border: 0,
							borderRadius: '4px',
							background: '#1e1e1e',
							color: '#fff',
							fontSize: '14px',
						} }
					>
						{ buttonText || __( 'Apply coupon', 'yaycheckout' ) }
					</button>
				</div>
			</div>
		</>
	);
}
