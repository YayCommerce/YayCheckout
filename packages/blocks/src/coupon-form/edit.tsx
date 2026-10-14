import { InspectorControls, PanelColorSettings, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DisclosureControls, DisclosurePreview, disclosureMode, type DisclosureMode } from '../shared/disclosure';
import './editor.css';

/** The key and label render.php hands Disclosure for the coupon field. */
const COUPON_KEY = 'yaycheckout-coupon-code';
const COUPON_LABEL = __( 'Coupon code', 'yaycheckout' );

const BUTTON = 'yaycheckout/coupon-button';

interface CouponFormAttributes {
	placeholder: string;
	buttonBackgroundColor: string;
	buttonTextColor: string;
	disclosure: DisclosureMode;
	disclosureText: string;
}

interface EditProps {
	attributes: CouponFormAttributes;
	setAttributes: ( attributes: Partial< CouponFormAttributes > ) => void;
}

export default function Edit( { attributes, setAttributes }: EditProps ) {
	// The fallbacks mirror render.php, which owns the storefront defaults.
	const {
		placeholder,
		buttonBackgroundColor = '',
		buttonTextColor = '',
		disclosure = 'none',
		disclosureText = '',
	} = attributes;
	// Mirrors render.php: the Button colors are the Coupon Button's starting look (coupon-button/style.css).
	const style: Record< string, string > = {
		...( buttonBackgroundColor ? { '--yaycheckout-coupon-button-background': buttonBackgroundColor } : {} ),
		...( buttonTextColor ? { '--yaycheckout-coupon-button-text': buttonTextColor } : {} ),
	};
	const blockProps = useBlockProps( { className: 'yaycheckout-coupon-form', style } );
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
				<PanelColorSettings
					title={ __( 'Button colors', 'yaycheckout' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							label: __( 'Background', 'yaycheckout' ),
							value: buttonBackgroundColor,
							onChange: ( value?: string ) => setAttributes( { buttonBackgroundColor: value ?? '' } ),
						},
						{
							label: __( 'Text', 'yaycheckout' ),
							value: buttonTextColor,
							onChange: ( value?: string ) => setAttributes( { buttonTextColor: value ?? '' } ),
						},
					] }
				/>
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
