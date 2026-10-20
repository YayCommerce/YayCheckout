import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useScopeClass } from '../shared/checkout-scope';
import { DisclosureControls, DisclosurePreview, disclosureMode, type DisclosureMode } from '../shared/disclosure';
import './editor.css';

/** The key and label render.php hands Disclosure for the coupon field. */
const COUPON_KEY = 'yaycheckout-coupon-code';
const COUPON_LABEL = __( 'Coupon code', 'yaycheckout' );

const BUTTON = 'yaycheckout/coupon-button';

interface CouponFormAttributes {
	placeholder: string;
	disclosure: DisclosureMode;
	disclosureText: string;
}

interface EditProps {
	attributes: CouponFormAttributes;
	setAttributes: ( attributes: Partial< CouponFormAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	// The fallbacks mirror render.php, which owns the storefront defaults.
	const { placeholder, disclosure = 'none', disclosureText = '' } = attributes;
	// It may sit outside Checkout (CheckoutScope).
	const blockProps = useBlockProps( { className: 'yaycheckout-coupon-form ' + useScopeClass() } );
	// The form always holds exactly one Coupon Button, which the merchant styles from its own Styles tab.
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{ className: 'yaycheckout-coupon-form__row' },
		{ allowedBlocks: [ BUTTON ], template: [ [ BUTTON ] ], templateLock: 'all' }
	);
	const mode = disclosureMode( COUPON_KEY, false, disclosure );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'yaycheckout' ) }>
					<TextControl
						label={ __( 'Placeholder', 'yaycheckout' ) }
						value={ placeholder }
						onChange={ ( value ) => setAttributes( { placeholder: value } ) }
					/>
					<DisclosureControls
						fieldKey={ COUPON_KEY }
						label={ COUPON_LABEL }
						required={ false }
						disclosure={ disclosure }
						disclosureText={ disclosureText }
						setAttributes={ setAttributes }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<DisclosurePreview mode={ mode } fieldKey={ COUPON_KEY } label={ COUPON_LABEL } disclosureText={ disclosureText }>
					<div { ...innerBlocksProps }>
						<span className="yaycheckout-coupon-form__field">
							<label className="yaycheckout-coupon-form__label">{ placeholder || COUPON_LABEL }</label>
							<input
								type="text"
								disabled
								className="yaycheckout-coupon-form__code"
								placeholder={ placeholder || COUPON_LABEL }
							/>
						</span>
						{ children }
					</div>
				</DisclosurePreview>
			</div>
		</>
	);
}
